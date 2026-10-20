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
use Bga\Games\projectedgeworth\Data\HeldNotificationRepository;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\PoolRepository;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;
use Bga\Games\projectedgeworth\Data\SatelliteRepository;
use Bga\Games\projectedgeworth\Data\StagedLinkRepository;
use Bga\Games\projectedgeworth\Effects\Runtime\EffectRuntime;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Machine\FlowContext;
use Bga\Games\projectedgeworth\Flow\Machine\Machine;
use Bga\Games\projectedgeworth\Flow\Machine\PublicView;
use Bga\Games\projectedgeworth\Flow\OrbitSatellite;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\SeededRandom;
use ProjectEdgeworthTests\Support\StateWalker;

/**
 * 効果エンジンを状態機械に本番結線した通しテスト（実 DB）。代表的な効果: 配備時に引く、[Use] と窓の再読み込み、起動の修正（USE_MODS）、
 * 追加の通信確立（バリアのコミットと世代）、EUROPA の宣言、ニアミスの修正、企画のオプション。
 */
final class EffectsWiringTest extends MachineTestCase
{
    private EffectRuntime $effects;

    private function startEffects(): void
    {
        $this->runSetup(2);
        $this->boot();
        $this->playerValues = new PlayerValues($this->db);
        $this->cards = new ProjectCardRepository($this->db, new SeededRandom(1));
        $this->globals->setRoundNo(1);
    }

    /** 新しいリクエスト（再読み込み）と同じく、DB とグローバル値から効果エンジンも状態機械も作り直す */
    private function boot(): void
    {
        $this->effects = new EffectRuntime($this->db, $this->globals);
        $this->machine = new Machine(new FlowContext(
            $this->db,
            $this->globals,
            new SeededRandom(5),
            static fn(int $id): string => "Player$id",
            effects: $this->effects,
        ));
        $this->walker = new StateWalker($this->machine);
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

    private function launch(int $satId, int $cell, Direction $dir = Direction::NE): void
    {
        (new SatelliteRepository($this->db))->launch($satId, $cell, $dir);
    }

    private function phase(FlowPhase $phase, int $action, array $holders): void
    {
        $this->globals->setFlowPhase($phase);
        $this->globals->setFlowStep(0);
        $this->globals->setFlowContext([]);
        $this->globals->setBonusHolders([$action => $holders]);
        $this->playerValues->setMaya(101, 60);
        $this->playerValues->setMaya(102, 60);
    }

    private function rows(string $table): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM `$table`");
    }

    /** 配備時に引く効果（P009）は問いがなく、窓 DE を 30 → 32 で自動で解決する。結果の通知はバリアで全員に流れ、本人には先に届く */
    public function testDeployTimeDrawResolvesAutomaticallyAtTheWindow(): void
    {
        $this->startEffects();
        $sat = $this->satId(101, SatelliteShape::Hammer);
        $this->toHand(101, 9);
        $this->phase(FlowPhase::Deploy, 2, [102]);
        $handBefore = count($this->hand(101));

        $deploy = fn(int $p): array => $p === 101 ? ['op' => 'deploy', 'card' => 9, 'sat' => $sat] : ['op' => 'pass'];
        $waiting = $this->walker->play($this->walker->run(StateId::DEPLOY_DRIVER), [StateId::DEPLOY_MAIN_CHOOSE => $deploy], [StateId::DEPLOY_BONUS_CHOOSE]);

        self::assertSame(StateId::DEPLOY_BONUS_CHOOSE, $waiting);
        self::assertContains(StateId::EFFECT_WINDOW_OPEN, $this->walker->visited);
        self::assertNotContains(StateId::EFFECT_WINDOW_RESOLVE, $this->walker->visited);
        self::assertSame([9], (new ProjectCardRepository($this->db, new SeededRandom(1)))->deployedIds($sat));
        self::assertCount($handBefore, $this->hand(101), '配備で 1 枚減り、P009 で 1 枚引く');

        // 通知: 本人への先送り（メッセージ空）、バリアでの全員への通知（early）、N26
        $used = $this->walker->noticesNamed('effectUsed');
        self::assertSame(['', 'First Penguin'], [$used[0]->message, $used[0]->args['source_name']]);
        self::assertSame(101, $used[0]->to);
        self::assertNull($used[1]->to);
        self::assertTrue($used[1]->args['early']);
        self::assertSame($used[0]->args['ev_id'], $used[1]->args['ev_id']);
        self::assertNotSame('', $used[1]->message);
        $drawn = array_values(array_filter($this->walker->noticesNamed('cardsDrawn'), static fn($n): bool => $n->args['reason'] === 'effect'));
        self::assertCount(2, $drawn);
        self::assertArrayNotHasKey('cards', $drawn[1]->args);
        $closed = $this->walker->noticesNamed('windowClosed');
        self::assertSame('DE', $closed[0]->args['window_id']);
        self::assertContains($used[0]->args['ev_id'], $closed[0]->args['events']);

        // 窓を閉じたら窓の状態は残らない
        self::assertSame([0, 0, 0], [$this->rows('pool'), $this->rows('held_notif'), $this->rows('staged_link')]);
        self::assertNull($this->globals->windowId());
        self::assertNull($this->globals->windowSnapshot());
    }

    /** [Use] を聞く効果（P005）は 31 で待ち、再読み込み（エンジンも状態機械も作り直し）しても同じ問いが DB から復元できる。他人の画面は窓を開いた時点の値 */
    public function testUseEffectSurvivesReloadAndHidesPrivateChanges(): void
    {
        $this->startEffects();
        $sat = $this->satId(101, SatelliteShape::Hammer);
        $this->launch($sat, 14);
        $this->deployTo($sat, 5);
        $this->phase(FlowPhase::Budget, 1, [102]);

        $waiting = $this->walker->run(StateId::BUDGET_DRIVER);
        self::assertSame(StateId::EFFECT_WINDOW_RESOLVE, $waiting);
        self::assertSame('B3', $this->globals->windowId());
        self::assertSame([101], $this->machine->windows()->playersNeedingInput());
        $args = $this->machine->windows()->resolveArgs();
        $question = $args['_private'][101]['questions'][0];
        self::assertSame(['EFFECT', 'procurement_officer', 'PAY_MAYA', 4], [$question['type'], $question['cardKey'], $question['cost']['kind'], $question['cost']['amount']]);
        self::assertSame([['player_id' => 101, 'sources' => ['procurement_officer']]], $args['waiting']);
        $maya = $this->playerValues->maya(101);
        $hand = count($this->hand(101));

        // 再読み込み
        $this->boot();
        self::assertSame([101], $this->machine->windows()->playersNeedingInput());
        self::assertSame($question, $this->machine->windows()->resolveArgs()['_private'][101]['questions'][0]);

        // 答えられるのは本人だけ
        try {
            $this->machine->windows()->use(102, (int) $question['entryId'], [], []);
            self::fail('102 has nothing to resolve');
        } catch (RuleViolation) {
            self::assertTrue(true);
        }
        $notices = $this->machine->windows()->use(101, (int) $question['entryId'], [], []);
        self::assertSame($maya - 4, $this->playerValues->maya(101));
        self::assertCount($hand + 1, $this->hand(101));
        self::assertSame(1, $this->cards->activation(5), '[Use] した起動効果は使用済み');
        // 本人にだけ先に届く（メッセージ空）。保留は DB に積まれている
        self::assertNotEmpty($notices);
        foreach ($notices as $notice) {
            self::assertSame(101, $notice->to);
        }
        self::assertGreaterThan(0, $this->rows('held_notif'));

        // バリアまでは、他人の画面に変化が見えない
        $theirs = (new PublicView($this->machine->ctx))->build(102)['playerData'][101];
        $mine = (new PublicView($this->machine->ctx))->build(101)['playerData'][101];
        self::assertSame([$maya, $hand], [$theirs['maya'], $theirs['handCount']]);
        self::assertSame([$maya - 4, $hand + 1], [$mine['maya'], $mine['handCount']]);

        // 再読み込みしてバリアへ。窓を閉じたら全員に見え、保留と窓の状態は消える
        $this->boot();
        $waiting = $this->walker->play($this->walker->run(StateId::EFFECT_WINDOW_RESOLVE), [], [StateId::PLANNING_PLOT]);
        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        self::assertSame(0, $this->rows('held_notif'));
        self::assertNull($this->globals->windowSnapshot());
        $all = array_values(array_filter($this->walker->noticesNamed('effectUsed'), static fn($n): bool => $n->to === null));
        self::assertSame('Procurement Officer', $all[0]->args['source_name']);
        self::assertTrue($all[0]->args['early']);
        $after = (new PublicView($this->machine->ctx))->build(102)['playerData'][101];
        self::assertSame($hand + 1, $after['handCount']);
    }

    /** [Skip] は使用済みにならず、払いもしない（N28） */
    public function testSkipDoesNotMarkUsedOrPay(): void
    {
        $this->startEffects();
        $sat = $this->satId(101, SatelliteShape::Hammer);
        $this->launch($sat, 14);
        $this->deployTo($sat, 5);
        $this->phase(FlowPhase::Budget, 1, [102]);

        $waiting = $this->walker->run(StateId::BUDGET_DRIVER);
        $maya = $this->playerValues->maya(101);
        $skip = static fn(int $p, array $private): array => ['skip' => (int) $private['questions'][0]['entryId']];
        $this->walker->play($waiting, [StateId::EFFECT_WINDOW_RESOLVE => $skip], [StateId::PLANNING_PLOT]);

        self::assertSame(0, $this->cards->activation(5));
        self::assertSame($maya, $this->playerValues->maya(101));
        $skipped = $this->walker->noticesNamed('effectSkipped');
        self::assertCount(2, $skipped, '本人への先送りと、バリアでの全員への通知');
        self::assertSame([101, null], [$skipped[0]->to, $skipped[1]->to]);
    }

    /** 起動の修正（USE_MODS）は 61 の args に候補が載り、確定に同梱し、コストを払うコミットで使用済みになる */
    public function testUseModsAreOfferedValidatedAndCommitted(): void
    {
        $this->startEffects();
        $active = $this->satId(101, SatelliteShape::Hammer);
        $inactive = $this->satId(101, SatelliteShape::Retro);
        $this->launch($active, 14);
        $this->deployTo($active, 19);
        $this->toHand(101, 9);
        $this->phase(FlowPhase::Deploy, 2, [102]);
        $this->playerValues->setMaya(101, 3);

        $waiting = $this->walker->run(StateId::DEPLOY_DRIVER);
        self::assertSame(StateId::DEPLOY_MAIN_CHOOSE, $waiting);
        $args = $this->machine->choice($waiting)->args();
        $target = array_values(array_filter($args['_private'][101]['targets'][9], static fn(array $t): bool => $t['satId'] === $inactive))[0];
        self::assertSame(3, $target['cost']);
        self::assertSame('P:19', $target['useMods']['options'][0]['hostKey']);
        self::assertSame(2, $target['useMods']['options'][0]['reduction']);
        self::assertSame(1, $target['useMods']['all']);

        // 候補にない鍵は弾く
        $flow = $this->machine->choice($waiting);
        try {
            $flow->confirm(101, ['op' => 'deploy', 'card' => 9, 'sat' => $inactive, 'useMods' => ['P:20']]);
            self::fail('P:20 is not an option');
        } catch (RuleViolation) {
            self::assertTrue(true);
        }
        $mine = ['op' => 'deploy', 'card' => 9, 'sat' => $inactive, 'useMods' => ['P:19']];
        $flow->confirm(101, $mine);
        $this->walker->play($waiting, [StateId::DEPLOY_MAIN_CHOOSE => fn(int $p): array => $p === 101 ? $mine : ['op' => 'pass']], [StateId::DEPLOY_BONUS_CHOOSE]);

        self::assertSame([9], $this->cards->deployedIds($inactive));
        self::assertSame(2, $this->playerValues->maya(101), '3 - (3 - 2) = 2');
        self::assertSame(1, $this->cards->activation(19));
        self::assertNotEmpty(array_filter($this->walker->noticesNamed('effectUsed'), static fn($n): bool => $n->args['source_name'] === 'Fleet Commander'));
    }

    /** 追加の通信確立（P056）は窓でステージし、バリアで全員分をコミットする。コミットは世代 1 の LINKED を起こし、通知も出る */
    public function testStagedLinkIsCommittedAtTheBarrier(): void
    {
        $this->startEffects();
        $sat = $this->satId(101, SatelliteShape::Hammer);
        [$cell, $ahead] = $this->linkCells();
        $this->launch($sat, $cell);
        $this->deployTo($sat, 56);
        $this->phase(FlowPhase::Link, 4, [102]);

        $link = fn(int $p): array => $p === 101 ? ['op' => 'link', 'cell' => $cell, 'sat' => $sat] : ['op' => 'pass'];
        $waiting = $this->walker->play($this->walker->run(StateId::LINK_DRIVER), [StateId::LINK_MAIN_CHOOSE => $link], [StateId::EFFECT_WINDOW_RESOLVE]);
        self::assertSame(StateId::EFFECT_WINDOW_RESOLVE, $waiting);
        self::assertSame('KE', $this->globals->windowId());

        $use = static fn(int $p, array $private): array => ['use' => [(int) $private['questions'][0]['entryId']]];
        $waiting = $this->walker->play($waiting, [StateId::EFFECT_WINDOW_RESOLVE => $use], [StateId::PLANNING_PLOT]);

        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        $cells = (new \Bga\Games\projectedgeworth\Data\LinkRepository($this->db))->cellsFor(101);
        sort($cells);
        $expected = [$cell, $ahead];
        sort($expected);
        self::assertSame($expected, $cells);
        $used = array_values(array_filter($this->walker->noticesNamed('effectUsed'), static fn($n): bool => $n->to === null));
        self::assertSame(['Forward Wide-Area Transmitter', true], [$used[0]->args['source_name'], $used[0]->args['mark_used']]);
        self::assertCount(2, $this->walker->noticesNamed('linkEstablished'));
        $staged = $this->walker->noticesNamed('linkStaged');
        self::assertCount(1, $staged);
        self::assertSame(101, $staged[0]->to);
        self::assertSame($ahead, $staged[0]->args['cell']);
        self::assertTrue($staged[0]->args['special']);
        self::assertSame([0, 0], [$this->rows('staged_link'), $this->rows('pool')]);
        self::assertSame(0, $this->globals->windowGen(), '窓を閉じたら世代は戻る');
        self::assertLessThan(60, $this->playerValues->maya(101));
    }

    /** EUROPA の宣言（R017）は XD の窓で聞き、払うとそのアクションのボーナスの参加者に加わる */
    public function testEuropaDeclarationAddsBonusHolder(): void
    {
        $this->startEffects();
        $sat = $this->satId(101, SatelliteShape::Retro);
        $this->db->execute("UPDATE `satellite` SET `sat_card` = 'R017', `sat_plan` = " . SatelliteRepository::planToInt(\Bga\Games\projectedgeworth\Material\SatellitePlan::Europa) . " WHERE `sat_id` = $sat");
        $this->launch($sat, 14);
        $this->deployTo($sat, 9, 34, 67);
        $this->phase(FlowPhase::Deploy, 2, [102]);

        $waiting = $this->walker->run(StateId::DEPLOY_DRIVER);
        self::assertSame(StateId::EFFECT_WINDOW_RESOLVE, $waiting);
        self::assertSame('XD', $this->globals->windowId());
        $private = $this->machine->windows()->resolveArgs()['_private'][101];
        self::assertSame('retro_europa', $private['questions'][0]['cardKey']);
        self::assertSame(3, $private['questions'][0]['cost']['amount']);

        $use = static fn(int $p, array $private): array => ['use' => [(int) $private['questions'][0]['entryId']]];
        $waiting = $this->walker->play($waiting, [StateId::EFFECT_WINDOW_RESOLVE => $use], [StateId::DEPLOY_MAIN_CHOOSE]);

        self::assertSame(StateId::DEPLOY_MAIN_CHOOSE, $waiting);
        self::assertSame(57, $this->playerValues->maya(101));
        self::assertSame([101, 102], $this->globals->bonusHolders()[2]);
        self::assertTrue($this->effects->game->declaredBonus(101, 'DEPLOY'));
        $closed = $this->walker->noticesNamed('windowClosed');
        self::assertSame('XD', $closed[0]->args['window_id']);
    }

    /** ニアミスの修正（P085）は循環フェイズの問い合わせに本番結線されている */
    public function testNearMissModifierIsWiredIntoTheOrbit(): void
    {
        $this->startEffects();
        $with = $this->satId(101, SatelliteShape::Hammer);
        $without = $this->satId(102, SatelliteShape::Hammer);
        $this->launch($with, 14);
        $this->launch($without, 15);
        $this->deployTo($with, 85);
        $mods = $this->machine->ctx->orbitMods;

        self::assertSame(1, $mods->nearMissFuelCost(new OrbitSatellite($with, 101, 14, Direction::NE, 0, 4)));
        self::assertSame(2, $mods->nearMissFuelCost(new OrbitSatellite($without, 102, 15, Direction::NE, 0, 4)));
    }

    /** 企画のオプション（P072）は 91 の args の extra に載る */
    public function testProposalOptionsAreOfferedInTheKeepArgs(): void
    {
        $this->startEffects();
        $sat = $this->satId(101, SatelliteShape::Hammer);
        $this->launch($sat, 14);
        $this->deployTo($sat, 72);
        $extra = $this->machine->ctx->services->hooks->proposalExtras(101, false, [1, 2]);

        self::assertSame('KEEP_PURCHASE', $extra['options'][0]['kind']);
        self::assertSame([], $this->machine->ctx->services->hooks->proposalExtras(102, false, [1, 2]));
    }

    /**
     * @return array{int, int} 通信確立に使える区域と、その進行方向（NE）前方の区域
     */
    private function linkCells(): array
    {
        $layout = $this->effects->layout;
        $geometry = $layout->geometry();
        $pads = array_values($this->playerValues->padCells());
        foreach ($layout->cellIds() as $id) {
            $here = $geometry->cellFromId($id);
            if ($here->y < 2 || $here->y > 3) {
                continue;
            }
            $ahead = $geometry->cellId($geometry->wrapX($here->x + Direction::NE->dx()), $here->y + Direction::NE->dy());
            if (!in_array($id, $pads, true) && !in_array($ahead, $pads, true)) {
                return [$id, $ahead];
            }
        }
        throw new \LogicException('no cell');
    }
}
