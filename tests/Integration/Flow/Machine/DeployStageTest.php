<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Machine;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\FlowPhase;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;
use Bga\Games\projectedgeworth\Data\SatelliteRepository;
use Bga\Games\projectedgeworth\Effects\Runtime\EffectRuntime;
use Bga\Games\projectedgeworth\Flow\Action\ActionServices;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Machine\DeployStageFlow;
use Bga\Games\projectedgeworth\Flow\Machine\FlowContext;
use Bga\Games\projectedgeworth\Flow\Machine\Machine;
use Bga\Games\projectedgeworth\Flow\Machine\PublicView;
use Bga\Games\projectedgeworth\Stats\StatsRecorder;
use ProjectEdgeworthTests\Support\ArrayStatStore;
use Bga\Games\projectedgeworth\Flow\Machine\StageResult;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\SeededRandom;
use ProjectEdgeworthTests\Support\StateWalker;

/**
 * II 配備の段階確定（009 追補 2026-11-12。作者回答 Q-20〜Q-25）の通しテスト: 1 枚目を [Deploy another card] で先に確定（ステージ）し、
 * 2 枚目は実盤面で見積もる（011 T03: P010 -2・P012 -3〈稼働中のみ〉・P026 の枚数・4 枚上限）。ステージ前のスナップショットへの
 * [Reset] の往復、山札から引いたあとの [Reset] 拒否、窓 DE の二重発動なし、zombie の S1、ステージの通知が本人宛てだけであること。
 */
final class DeployStageTest extends MachineTestCase
{
    private EffectRuntime $effects;

    private function startStage(): void
    {
        $this->runSetup(2);
        $this->effects = new EffectRuntime($this->db, $this->globals);
        $this->machine = new Machine(new FlowContext(
            $this->db,
            $this->globals,
            new SeededRandom(5),
            static fn(int $id): string => "Player$id",
            effects: $this->effects,
        ));
        $this->walker = new StateWalker($this->machine);
        $this->playerValues = new PlayerValues($this->db);
        $this->cards = new ProjectCardRepository($this->db, new SeededRandom(1));
        $this->globals->setRoundNo(1);
    }

    private function satId(int $playerId, SatelliteShape $shape): int
    {
        foreach ((new SatelliteRepository($this->db))->forPlayer($playerId) as $sat) {
            if ($sat->shape === $shape) {
                return $sat->id;
            }
        }
        throw new \LogicException('no satellite');
    }

    private function toHand(int $playerId, int ...$cardNos): void
    {
        foreach ($cardNos as $no) {
            $this->db->execute("UPDATE `project_card` SET `card_location` = 'hand', `card_location_arg` = $playerId, `card_order` = 900 + $no WHERE `card_id` = $no");
        }
    }

    private function deployTo(int $satId, int ...$cardNos): void
    {
        foreach ($cardNos as $i => $no) {
            $this->db->execute("UPDATE `project_card` SET `card_location` = 'satellite', `card_location_arg` = $satId, `card_order` = " . ($i + 1) . " WHERE `card_id` = $no");
        }
    }

    private function launch(int $satId, int $cell = 14): void
    {
        (new SatelliteRepository($this->db))->launch($satId, $cell, Direction::NE);
    }

    /** 配備フェイズ（ボーナス保持者は 101）に入り、状態 61 で止まる。マヤは 60 */
    private function enterDeploy(array $holders = [101]): int
    {
        $this->globals->setFlowPhase(FlowPhase::Deploy);
        $this->globals->setFlowStep(0);
        $this->globals->setFlowContext([]);
        $this->globals->setBonusHolders([2 => $holders]);
        $this->playerValues->setMaya(101, 60);
        $this->playerValues->setMaya(102, 60);
        $waiting = $this->walker->run(StateId::DEPLOY_DRIVER);
        self::assertSame(StateId::DEPLOY_MAIN_CHOOSE, $waiting);

        return $waiting;
    }

    private function stage(int $playerId, array $choice): StageResult
    {
        $result = $this->machine->deployStage()->stage($playerId, $choice);
        $this->walker->notices = array_merge($this->walker->notices, $result->notices);

        return $result;
    }

    private function cost(int $card): int
    {
        return ActionServices::project($card)->cost;
    }

    /** @return array<string, mixed> 61 の本人分の args */
    private function mine(int $playerId = 101): array
    {
        return $this->machine->choice(StateId::DEPLOY_MAIN_CHOOSE)->args()['_private'][$playerId];
    }

    /** @return array<string, mixed> 2 枚目の候補（カード・衛星） */
    private function candidate(int $card, int $satId, int $playerId = 101): array
    {
        foreach ($this->mine($playerId)['targets'][$card] as $entry) {
            if ($entry['satId'] === $satId) {
                return $entry;
            }
        }
        throw new \LogicException('no candidate');
    }

    /** 1 人分の状態（手札・衛星のカード・燃料・マヤ・予算・捨て札）。往復で同値に戻ることの検査用 */
    private function dump(): array
    {
        return [
            'cards' => $this->db->rows('SELECT * FROM `project_card` ORDER BY `card_id`'),
            'sats' => $this->db->rows('SELECT * FROM `satellite` ORDER BY `sat_id`'),
            'players' => $this->db->rows('SELECT `player_id`, `maya`, `budget` FROM `player` ORDER BY `player_id`'),
        ];
    }

    private function finish(int $waiting, array $choice101 = ['op' => 'staged'], array $stopAt = [StateId::PLANNING_PLOT]): int
    {
        return $this->walker->play(
            $waiting,
            [StateId::DEPLOY_MAIN_CHOOSE => fn(int $p): array => $p === 101 ? $choice101 : ['op' => 'pass']],
            $stopAt,
        );
    }

    /** T03: 1 枚目の P010 の -2 が、同じ衛星へ配備する 2 枚目に効く。自身の配備と他の衛星には効かない */
    public function testP010OfTheStagedCardDiscountsTheSecondCardOnTheSameSatellite(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $b = $this->satId(101, SatelliteShape::Retro);
        $this->toHand(101, 10, 1);
        $waiting = $this->enterDeploy();
        self::assertSame($this->cost(1), $this->candidate(1, $a)['cost'], 'ステージ前は割引なし');

        $this->stage(101, ['op' => 'deploy', 'card' => 10, 'sat' => $a]);
        self::assertSame(60 - $this->cost(10), $this->playerValues->maya(101), 'P010 自身は割引されない（自身の配備には効かない）');
        self::assertSame(max(0, $this->cost(1) - 2), $this->candidate(1, $a)['cost']);
        self::assertSame($this->cost(1), $this->candidate(1, $b)['cost'], '他の衛星には効かない');

        $this->finish($waiting, ['op' => 'staged', 'extra' => ['op' => 'deploy', 'card' => 1, 'sat' => $a]]);
        self::assertSame([10, 1], $this->cards->deployedIds($a));
        self::assertSame(60 - $this->cost(10) - max(0, $this->cost(1) - 2), $this->playerValues->maya(101));
    }

    /** T03: P012 の -3 は稼働中の衛星だけ。稼働中への +5 は 1 枚ごと（Q-19） */
    public function testP012OfTheStagedCardDiscountsOnlyWhileTheSatelliteIsActive(): void
    {
        $this->startStage();
        $active = $this->satId(101, SatelliteShape::Hammer);
        $inactive = $this->satId(101, SatelliteShape::Retro);
        $this->launch($active);
        $this->toHand(101, 12, 1);
        $waiting = $this->enterDeploy();

        $this->stage(101, ['op' => 'deploy', 'card' => 12, 'sat' => $active]);
        self::assertSame(60 - $this->cost(12) - 5, $this->playerValues->maya(101), '稼働中は 1 枚ごとに +5');
        self::assertSame(max(0, $this->cost(1) + 5 - 3), $this->candidate(1, $active)['cost']);
        self::assertSame($this->cost(1), $this->candidate(1, $inactive)['cost']);

        // [Reset] して非稼働の衛星に配備し直すと、P012 は働かない
        $this->machine->deployStage()->reset(101);
        $this->stage(101, ['op' => 'deploy', 'card' => 12, 'sat' => $inactive]);
        self::assertSame($this->cost(1), $this->candidate(1, $inactive)['cost'], '非稼働の衛星の P012 は効かない');
        $this->finish($waiting);
    }

    /** P026 は 1 枚目を含む枚数を数える。4 枚上限も 1 枚目込み */
    public function testP026CountsTheStagedFirstCardAndTheFourCardLimitIncludesIt(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $b = $this->satId(101, SatelliteShape::Retro);
        $this->deployTo($a, 5);
        $this->deployTo($b, 6, 7, 8);
        $this->toHand(101, 4, 26, 1);
        $waiting = $this->enterDeploy();
        self::assertSame(max(0, $this->cost(26) - 2), $this->candidate(26, $a)['cost'], '既に 1 枚付いている');

        $this->stage(101, ['op' => 'deploy', 'card' => 4, 'sat' => $a]);
        self::assertSame(max(0, $this->cost(26) - 4), $this->candidate(26, $a)['cost'], '1 枚目を含めて 2 枚');
        self::assertSame($this->cost(26) - 6 < 0 ? 0 : $this->cost(26) - 6, $this->candidate(26, $b)['cost'], '3 枚付いている衛星');

        $this->machine->deployStage()->reset(101);
        $this->stage(101, ['op' => 'deploy', 'card' => 4, 'sat' => $b]);
        self::assertSame('full', $this->candidate(1, $b)['blocked'], '1 枚目で 4 枚になった');
        $flow = $this->machine->choice($waiting);
        $this->expectException(RuleViolation::class);
        $flow->confirm(101, ['op' => 'staged', 'extra' => ['op' => 'deploy', 'card' => 1, 'sat' => $b]]);
    }

    /** ステージ → [Reset] で、手札・衛星・マヤ・予算・捨て札・起動の使用済みがすべて元に戻る（売却を同梱したステージも） */
    public function testResetRestoresEverythingTheStageChanged(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->launch($a);
        $this->deployTo($a, 13);
        $this->toHand(101, 1, 4, 10, 2);
        $waiting = $this->enterDeploy();
        $this->playerValues->setMaya(101, 9);
        $before = $this->dump();

        // 売却を同梱（稼働中は +5 なのでコスト 10、所持 9 + 売却 6）。CREW を配備すると P013（◎ 予算 +1）がステージで解決して使用済みになる
        $budget = $this->playerValues->budget(101);
        $result = $this->stage(101, ['op' => 'deploy', 'card' => 1, 'sat' => $a, 'sell' => [4, 10]]);
        self::assertSame($budget + 1, $this->playerValues->budget(101));
        self::assertFalse($result->wasConfirmed);
        self::assertSame(1, $this->cards->activation(13), 'P013 はステージで解決した');
        self::assertSame(1, $this->mine()['stage']);
        self::assertTrue($this->mine()['canReset']);
        self::assertNotSame($before, $this->dump());
        self::assertNotContains(4, $this->hand(101));
        self::assertContains(2, $this->hand(101));

        $reset = $this->machine->deployStage()->reset(101);
        self::assertSame($before, $this->dump(), 'ステージ前と同値に戻る');
        self::assertSame(['deployStageReset'], array_map(static fn($n): string => $n->name, $reset->notices));
        self::assertSame(101, $reset->notices[0]->to);
        self::assertSame(0, $this->mine()['stage']);
        self::assertSame(0, $this->db->value('SELECT COUNT(*) FROM `deploy_stage`') + 0);

        // 取り消したあとは普通に選び直せる
        $this->stage(101, ['op' => 'deploy', 'card' => 4, 'sat' => $a]);
        $this->finish($waiting);
        self::assertSame([13, 4], $this->cards->deployedIds($a));
    }

    /** 山札から引いたら [Reset] は不可（Q-22）。拒否は状態を変えず、2 枚目の選択・取りやめはできる */
    public function testResetIsRefusedOnceCardsWereDrawn(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->toHand(101, 9, 1);
        $waiting = $this->enterDeploy();
        $handBefore = count($this->hand(101));

        $this->stage(101, ['op' => 'deploy', 'card' => 9, 'sat' => $a]);
        self::assertCount($handBefore, $this->hand(101), 'P009 で 1 枚引いた');
        $args = $this->mine();
        self::assertFalse($args['canReset']);
        self::assertSame(DeployStageFlow::REASON_IRREVERSIBLE, $args['resetBlockedReason']);
        $after = $this->dump();
        try {
            $this->machine->deployStage()->reset(101);
            self::fail('reset must be refused after a draw');
        } catch (RuleViolation $violation) {
            self::assertSame(DeployStageFlow::REASON_IRREVERSIBLE, $violation->getMessage());
        }
        self::assertSame($after, $this->dump());

        // 引いたカードも 2 枚目に使える（Q-24）
        $drawn = array_values(array_diff($this->hand(101), [1]))[0];
        self::assertArrayHasKey($drawn, $this->mine()['targets']);
        $this->finish($waiting);
        self::assertSame([9], $this->cards->deployedIds($a));
    }

    /** 窓 DE は、ステージで解決済みの効果を二重に発動しない（P009: 自分の配備時に 1 枚引く。起動効果ではないので使用済みでは防げない） */
    public function testWindowDoesNotFireTheStageResolvedEffectAgain(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->toHand(101, 9, 1);
        $waiting = $this->enterDeploy();
        $handBefore = count($this->hand(101));

        $this->stage(101, ['op' => 'deploy', 'card' => 9, 'sat' => $a]);
        self::assertCount($handBefore, $this->hand(101), 'P009 は 1 枚目のステージで引く');
        $resolved = (string) $this->db->value('SELECT `ds_resolved` FROM `deploy_stage` WHERE `ds_player` = 101');
        self::assertStringContainsString('"resolved":["P:9"]', $resolved);

        $this->finish($waiting, ['op' => 'staged', 'extra' => ['op' => 'deploy', 'card' => 1, 'sat' => $a]]);
        self::assertSame([9, 1], $this->cards->deployedIds($a));
        self::assertCount($handBefore - 1, $this->hand(101), '2 枚目を配備しても、1 枚目の P009 は二重に引かない');
    }

    /** 起動効果（◎）の使用済みはステージの解決で消費する: 1 枚目で P023 が引いたら、2 枚目では引かない */
    public function testActivatedEffectUsedAtStageIsNotAvailableAtTheWindow(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->deployTo($a, 23);
        $this->toHand(101, 4, 1);
        $waiting = $this->enterDeploy();
        $handBefore = count($this->hand(101));

        $this->stage(101, ['op' => 'deploy', 'card' => 4, 'sat' => $a]);
        self::assertCount($handBefore, $this->hand(101), 'P023 は 1 枚目のステージで引く');
        self::assertSame(1, $this->cards->activation(23));

        $this->finish($waiting, ['op' => 'staged', 'extra' => ['op' => 'deploy', 'card' => 1, 'sat' => $a]]);
        self::assertCount($handBefore - 1, $this->hand(101), '2 枚目では引かない（各ラウンド 1 回）');
    }

    /** 選択が要る効果（P011 の追加配備）は、ステージでは解決せず、バリアのあとの窓 DE に残る（Q-21） */
    public function testEffectsThatNeedAChoiceAreLeftForTheWindow(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->launch($a);
        $this->deployTo($a, 11);
        $this->toHand(101, 34);
        $waiting = $this->enterDeploy();
        $mayaBefore = $this->playerValues->maya(101);

        $this->stage(101, ['op' => 'deploy', 'card' => 34, 'sat' => $a]);
        self::assertSame($mayaBefore - $this->cost(34) - 5, $this->playerValues->maya(101), '稼働中は +5');
        self::assertSame(0, $this->cards->activation(11), 'P011 はまだ解決していない');
        self::assertStringNotContainsString('P:11', (string) $this->db->value('SELECT `ds_resolved` FROM `deploy_stage` WHERE `ds_player` = 101'));
        self::assertTrue($this->mine()['canReset'], '選択が要る効果は未解決なので、まだ取り消せる');

        $waiting = $this->finish($waiting, ['op' => 'staged'], [StateId::EFFECT_WINDOW_RESOLVE, StateId::PLANNING_PLOT]);
        self::assertSame(StateId::EFFECT_WINDOW_RESOLVE, $waiting);
        self::assertSame('DE', $this->globals->windowId());
        $questions = $this->machine->windows()->resolveArgs()['_private'][101]['questions'];
        self::assertSame('twin_deploy_technician', $questions[0]['cardKey']);
    }

    /** zombie の S1: ステージ済みの 1 枚目を残したまま 2 枚目なしで確定する（取り消さない） */
    public function testZombieKeepsTheStagedFirstCard(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->toHand(101, 4, 1);
        $waiting = $this->enterDeploy();
        $this->stage(101, ['op' => 'deploy', 'card' => 4, 'sat' => $a]);

        // 全員 zombie（確定内容なし）で進める
        $waiting = $this->walker->play($waiting, [], [StateId::PLANNING_PLOT]);
        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        self::assertSame([4], $this->cards->deployedIds($a));
        self::assertSame(60 - $this->cost(4), $this->playerValues->maya(101));
        self::assertContains(1, $this->hand(101));
        self::assertNotContains(4, $this->hand(101));
        self::assertSame(0, $this->db->value('SELECT COUNT(*) FROM `deploy_stage`') + 0);
    }

    /** ステージの通知は本人宛てだけ（バリアまで他人に見せない）。公開の通知は増えない */
    public function testStageNotificationsGoOnlyToTheStagingPlayer(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->toHand(101, 9, 1);
        $this->enterDeploy();

        $result = $this->stage(101, ['op' => 'deploy', 'card' => 9, 'sat' => $a]);
        self::assertNotEmpty($result->notices);
        foreach ($result->notices as $notice) {
            self::assertSame(101, $notice->to, $notice->name);
        }
        $names = array_map(static fn($n): string => $n->name, $result->notices);
        self::assertContains('cardDeployed', $names);
        self::assertContains('deployStaged', $names);
        self::assertContains('handCardsAdded', $names);
    }

    /** ボーナス保持者だけがステージできる。確定済みのステージは確定を取り消して未確定に戻す。ステージの二重化・参加者以外は拒否 */
    public function testStagePreconditionsAndUnconfirmation(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->toHand(101, 4, 1);
        $this->toHand(102, 34);
        $waiting = $this->enterDeploy();
        $flow = $this->machine->choice($waiting);

        try {
            $this->machine->deployStage()->stage(102, ['op' => 'deploy', 'card' => 34, 'sat' => $this->satId(102, SatelliteShape::Hammer)]);
            self::fail('only the bonus holder may stage');
        } catch (RuleViolation) {
            self::assertTrue(true);
        }

        $flow->confirm(101, ['op' => 'pass']);
        self::assertTrue($this->effects->game->s()->choices->isConfirmed(101, StateId::DEPLOY_MAIN_CHOOSE));
        $result = $this->stage(101, ['op' => 'deploy', 'card' => 4, 'sat' => $a]);
        self::assertTrue($result->wasConfirmed);
        self::assertFalse($this->effects->game->s()->choices->isConfirmed(101, StateId::DEPLOY_MAIN_CHOOSE));

        try {
            $this->stage(101, ['op' => 'deploy', 'card' => 1, 'sat' => $a]);
            self::fail('staging twice is refused');
        } catch (RuleViolation) {
            self::assertTrue(true);
        }

        // S1 の確定は extra だけが意味を持つ（外側の op・1 枚目の引数は捨てる）
        $flow->confirm(101, ['op' => 'deploy', 'card' => 1, 'sat' => $a, 'extra' => ['op' => 'deploy', 'card' => 1, 'sat' => $a]]);
        self::assertSame(
            ['op' => 'staged', 'extra' => ['op' => 'deploy', 'card' => 1, 'sat' => $a]],
            $this->effects->game->s()->choices->ownChoice(101, StateId::DEPLOY_MAIN_CHOOSE),
        );
        // Reset すると確定も消える
        $reset = $this->machine->deployStage()->reset(101);
        self::assertTrue($reset->wasConfirmed);
        self::assertNull($this->effects->game->s()->choices->ownChoice(101, StateId::DEPLOY_MAIN_CHOOSE));
        self::assertSame([CardLocation::Hand], [$this->cards->locationOf(4)['location']]);
    }

    /** @return array<string, mixed> 閲覧者から見た getAllDatas の公開部分（観戦者は存在しない ID） */
    private function view(int $viewerId): array
    {
        return (new PublicView($this->machine->ctx))->build($viewerId);
    }

    /** @return list<int> 閲覧者から見た、ある衛星に配備されたカード */
    private function seenDeployed(array $view, int $owner, int $satId): array
    {
        foreach ($view['playerData'][$owner]['satellites'] as $sat) {
            if ($sat['satId'] === $satId) {
                return array_map(static fn(array $c): int => $c['id'], $sat['cards']);
            }
        }
        throw new \LogicException('no satellite');
    }

    /** ステージ中は、他人と観戦者の getAllDatas にステージ分（配備・マヤ・手札・山札・捨て札）が出ない。本人には出る。バリア後は全員に出る */
    public function testOthersAndSpectatorsDoNotSeeTheStagedCardUntilTheBarrier(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->toHand(101, 9, 4, 1);
        $waiting = $this->enterDeploy();
        // P009 は配備時に 1 枚引く。4 を売却して払う（捨て札・マヤ・手札・山札がすべて動く）
        $this->playerValues->setMaya(101, 0);
        $publicBefore = $this->view(102);
        $handCount = $publicBefore['playerData'][101]['handCount'];

        $this->stage(101, ['op' => 'deploy', 'card' => 9, 'sat' => $a, 'sell' => [4]]);
        self::assertNotSame($publicBefore['deckCount'], $this->machine->ctx->deckCount(), '実際には山札が減っている');

        foreach ([102, 999] as $viewer) {
            $other = $this->view($viewer);
            self::assertSame([], $this->seenDeployed($other, 101, $a), "viewer $viewer");
            self::assertSame($publicBefore['playerData'][101], $other['playerData'][101], "viewer $viewer");
            self::assertSame($publicBefore['deckCount'], $other['deckCount']);
            self::assertSame($publicBefore['discard'], $other['discard']);
            self::assertSame($handCount, $other['playerData'][101]['handCount']);
        }
        $mine = $this->view(101);
        self::assertSame([9], $this->seenDeployed($mine, 101, $a));
        self::assertSame($this->machine->ctx->deckCount(), $mine['deckCount']);
        self::assertContains(4, $mine['discard']);
        self::assertSame($this->playerValues->maya(101), $mine['playerData'][101]['maya']);
        self::assertSame($this->machine->ctx->services->players->maya(101), $mine['playerData'][101]['maya']);
        self::assertNotContains(4, $this->view(102)['discard']);

        $this->finish($waiting);
        foreach ([101, 102, 999] as $viewer) {
            self::assertSame([9], $this->seenDeployed($this->view($viewer), 101, $a), "after the barrier: viewer $viewer");
        }
        self::assertContains(4, $this->view(102)['discard']);
    }

    /** 2 人がステージしたとき: それぞれ自分のステージ分は見え、相手のステージ分は見えない。山札・捨て札も相手の増減だけを戻す */
    public function testTwoStagersEachSeeOnlyTheirOwn(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $b = $this->satId(102, SatelliteShape::Hammer);
        $this->toHand(101, 9, 4);
        $this->toHand(102, 8, 5);
        $waiting = $this->enterDeploy([101, 102]);
        $before = $this->view(999);

        $this->stage(101, ['op' => 'deploy', 'card' => 9, 'sat' => $a, 'sell' => [4]]);
        $this->stage(102, ['op' => 'deploy', 'card' => 8, 'sat' => $b, 'sell' => [5]]);

        $v101 = $this->view(101);
        $v102 = $this->view(102);
        $spectator = $this->view(999);
        self::assertSame([9], $this->seenDeployed($v101, 101, $a));
        self::assertSame([], $this->seenDeployed($v101, 102, $b));
        self::assertSame([8], $this->seenDeployed($v102, 102, $b));
        self::assertSame([], $this->seenDeployed($v102, 101, $a));
        self::assertSame([], $this->seenDeployed($spectator, 101, $a));
        self::assertSame([], $this->seenDeployed($spectator, 102, $b));
        self::assertSame($before['playerData'], $spectator['playerData']);
        self::assertSame($before['deckCount'], $spectator['deckCount']);
        self::assertSame($before['discard'], $spectator['discard']);
        self::assertContains(4, $v101['discard']);
        self::assertNotContains(5, $v101['discard']);
        self::assertContains(5, $v102['discard']);
        self::assertNotContains(4, $v102['discard']);
        self::assertSame($this->machine->ctx->services->players->maya(101), $v101['playerData'][101]['maya']);
        self::assertSame($before['playerData'][102]['maya'], $v101['playerData'][102]['maya']);
        $this->finish($waiting);
    }

    /** ステージの公開通知は保留に積まれ、本人にはメッセージ空の先送りが即座に届く。バリアで全員に、ステージした順・発生順で流れる */
    public function testPublicNoticesAreHeldAndReleasedAtTheBarrierInStageOrder(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $b = $this->satId(102, SatelliteShape::Hammer);
        $this->toHand(101, 9, 4, 1);
        $this->toHand(102, 8, 5);
        $waiting = $this->enterDeploy([101, 102]);
        $mark = count($this->walker->notices);

        // 102 が先にステージし、101 が後
        $this->stage(102, ['op' => 'deploy', 'card' => 8, 'sat' => $b, 'sell' => [5]]);
        $this->stage(101, ['op' => 'deploy', 'card' => 9, 'sat' => $a, 'sell' => [4]]);
        $staged = array_slice($this->walker->notices, $mark);
        foreach ($staged as $notice) {
            self::assertNotNull($notice->to, $notice->name . ' は全員宛てにしない');
        }
        $early = array_values(array_filter($staged, static fn($n): bool => isset($n->args['ev_id'])));
        self::assertNotEmpty($early);
        foreach ($early as $notice) {
            self::assertSame('', $notice->message);
        }
        $held = $this->db->rows('SELECT `held_origin`, `held_type`, `held_args` FROM `held_notif` ORDER BY `held_id`');
        $origins = array_map(static fn(array $r): int => (int) $r['held_origin'], $held);
        $sorted = $origins;
        sort($sorted);
        self::assertSame([102, 101], array_values(array_unique($origins)), 'ステージした順');
        $types102 = array_values(array_map(static fn(array $r): string => $r['held_type'], array_filter($held, static fn(array $r): bool => (int) $r['held_origin'] === 102)));
        self::assertSame(['cardsDiscarded', 'mayaChanged', 'mayaChanged', 'cardDeployed'], array_slice($types102, 0, 4), '売却 → 売却益 → 支払い → 配備の発生順');

        $mark = count($this->walker->notices);
        $this->finish($waiting, ['op' => 'staged', 'extra' => ['op' => 'deploy', 'card' => 1, 'sat' => $a]]);
        $after = array_slice($this->walker->notices, $mark);
        $released = array_values(array_filter($after, static fn($n): bool => ($n->args['early'] ?? false) === true && $n->to === null));
        self::assertCount(count($held), $released);
        self::assertSame(
            array_map(static fn(array $r): int => (int) (json_decode($r['held_args'], true)['ev_id']), $held),
            array_map(static fn($n): int => $n->args['ev_id'], $released),
            'held_id の昇順',
        );
        self::assertSame(0, $this->db->value('SELECT COUNT(*) FROM `held_notif`') + 0);
        foreach ($released as $notice) {
            self::assertNotSame('', $notice->message, $notice->name);
        }
        // 2 枚目のコミットの通知は、保留分の後
        $lastReleased = array_search(end($released), $after, true);
        $deployNotices = array_keys(array_filter($after, static fn($n): bool => $n->name === 'cardDeployed' && $n->to === null && !isset($n->args['early'])));
        foreach ($deployNotices as $index) {
            self::assertGreaterThan($lastReleased, $index);
        }
    }

    /** Reset すると、そのプレイヤーの保留分は捨てられる（ほかの人の保留は残る）。確定が消えるときは choiceUnmarked が全員に出る */
    public function testResetDiscardsOnlyThatPlayersHeldNotices(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $b = $this->satId(102, SatelliteShape::Hammer);
        $this->toHand(101, 4, 1);
        $this->toHand(102, 8, 5);
        $this->enterDeploy([101, 102]);

        $this->stage(102, ['op' => 'deploy', 'card' => 8, 'sat' => $b]);
        $theirs = (int) $this->db->value('SELECT COUNT(*) FROM `held_notif` WHERE `held_origin` = 102');
        $this->machine->choice(StateId::DEPLOY_MAIN_CHOOSE)->confirm(101, ['op' => 'pass']);
        $result = $this->stage(101, ['op' => 'deploy', 'card' => 4, 'sat' => $a]);
        self::assertTrue($result->wasConfirmed);
        $unmarked = array_values(array_filter($result->notices, static fn($n): bool => $n->name === 'choiceUnmarked'));
        self::assertCount(1, $unmarked);
        self::assertNull($unmarked[0]->to);
        self::assertSame(101, $unmarked[0]->args['player_id']);
        self::assertGreaterThan(0, (int) $this->db->value('SELECT COUNT(*) FROM `held_notif` WHERE `held_origin` = 101'));

        $this->machine->deployStage()->reset(101);
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM `held_notif` WHERE `held_origin` = 101'));
        self::assertSame($theirs, (int) $this->db->value('SELECT COUNT(*) FROM `held_notif` WHERE `held_origin` = 102'));
        self::assertSame([], $this->seenDeployed($this->view(101), 102, $b), '相手のステージ分は本人にも見えない');
    }

    /** 統計: ステージ中は数えず（先送りは本人宛て）、バリアの公開通知で 1 回ずつ数える。Reset した分は数えない */
    public function testStatsCountEachStagedDeploymentOnce(): void
    {
        $this->startStage();
        $a = $this->satId(101, SatelliteShape::Hammer);
        $this->toHand(101, 4, 1, 2);
        $waiting = $this->enterDeploy();

        $this->stage(101, ['op' => 'deploy', 'card' => 2, 'sat' => $a]);
        $this->machine->deployStage()->reset(101);
        $this->stage(101, ['op' => 'deploy', 'card' => 4, 'sat' => $a]);
        $store = new ArrayStatStore();
        $stats = new StatsRecorder($store);
        $stats->init();
        $stats->recordNotices($this->walker->notices);
        self::assertSame(0, $store->player('cards_deployed', 101), 'ステージ中は数えない');

        $this->finish($waiting, ['op' => 'staged', 'extra' => ['op' => 'deploy', 'card' => 1, 'sat' => $a]]);
        $store = new ArrayStatStore();
        $stats = new StatsRecorder($store);
        $stats->init();
        $stats->recordNotices($this->walker->notices);
        self::assertSame(2, $store->player('cards_deployed', 101), '4 と 1 の 2 回（Reset した 2 は数えない）');
    }
}
