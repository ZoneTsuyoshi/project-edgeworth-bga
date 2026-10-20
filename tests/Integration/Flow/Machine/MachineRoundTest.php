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
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Machine\FlowContext;
use Bga\Games\projectedgeworth\Flow\Machine\Machine;
use Bga\Games\projectedgeworth\Flow\Machine\PublicView;
use Bga\Games\projectedgeworth\Flow\Machine\WindowGate;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use Bga\Games\projectedgeworth\Setup\StartingHand;
use ProjectEdgeworthTests\Support\SeededRandom;
use ProjectEdgeworthTests\Support\StateWalker;

/** セットアップ → 計画 → アクション → 循環 → 終了判定 → 次のラウンドが、状態クラスを使わずに通しで進むこと（009）。 */
final class MachineRoundTest extends MachineTestCase
{
    public function testSetupThenRoundWithBudgetAndProposal(): void
    {
        $this->startGame();
        $mayaAtStart = [101 => $this->playerValues->maya(101), 102 => $this->playerValues->maya(102)];

        // セットアップ（発射拠点 → 手札なし → 配備フェイズ）を zombie の既定で通り、第 1 ラウンドの計画で止める
        $waiting = $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        self::assertSame(1, $this->globals->roundNo());
        self::assertSame(FlowPhase::Planning, $this->globals->flowPhase());
        self::assertCount(2, $this->playerValues->padCells());
        self::assertCount(2, array_unique($this->playerValues->padCells()));
        self::assertContains(StateId::DEPLOY_MAIN_CHOOSE, $this->walker->visited);
        self::assertNotContains(62, $this->walker->visited, '62 は欠番（配備のボーナスは 61 で同時に選ぶ）');
        self::assertNotContains(StateId::SETUP_MULLIGAN, $this->walker->visited);
        self::assertSame(['launchBasePlaced', 'launchBasePlaced'], array_slice($this->walker->noticeNames(), 0, 2));
        self::assertSame([], $this->globals->bonusHolders());
        self::assertSame([1, 2, 3, 4, 5], $this->machine->ctx->phases->planning()->selectable(101));

        // 第 1 ラウンド: 101 は I 予算発行、102 は V 企画。第 2 ラウンドの計画で止まる
        $plot = static fn(int $playerId): array => ['action' => $playerId === 101 ? 1 : 5];
        $waiting = $this->walker->play($waiting, [StateId::PLANNING_PLOT => $plot], [StateId::PLANNING_PLOT]);
        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        self::assertSame(2, $this->globals->roundNo());

        // I は毎ラウンド手札に戻る。V は前ラウンドに選んだので横向き（001 §6-1）
        self::assertSame([1, 2, 3, 4, 5], $this->machine->ctx->phases->planning()->selectable(101));
        self::assertSame([1, 2, 3, 4], $this->machine->ctx->phases->planning()->selectable(102));

        // I 予算発行: 全員が予算（15）、101 だけボーナス +6
        self::assertSame($mayaAtStart[101] + 15 + 6, $this->playerValues->maya(101));
        self::assertSame($mayaAtStart[102] + 15, $this->playerValues->maya(102));
        // V 企画: メインは 2 枚引いて 1 枚残す、V を選んだ 102 のボーナスは 5 枚引いて 2 枚残す（残りは捨て札）
        self::assertCount(6, $this->hand(101));
        self::assertCount(7, $this->hand(102));
        self::assertSame(1 + 3, $this->cards->countIn(CardLocation::Discard));
        self::assertSame(0, $this->cards->countIn(CardLocation::Drawn, 101) + $this->cards->countIn(CardLocation::Drawn, 102));

        $names = $this->walker->noticeNames();
        foreach (
            ['roundStarted', 'actionRevealed', 'phaseStarted', 'mayaChanged', 'cardsDrawn', 'handCardsAdded', 'drawnCardsKept',
            'cardsDiscarded', 'countersUpdate', 'choiceMarked', 'choiceRecorded', 'actionCardsCollected'] as $name
        ) {
            self::assertContains($name, $names, $name);
        }
        // 公開される通知に、選んだ内容も引いたカードの ID も載らない
        foreach ($this->walker->noticesNamed('choiceMarked') as $notice) {
            self::assertSame(['player_id', 'state_id'], array_keys($notice->args));
        }
        foreach ($this->walker->noticesNamed('cardsDrawn') as $notice) {
            self::assertNull($notice->to);
            self::assertArrayNotHasKey('cards', $notice->args);
        }
        foreach ($this->walker->noticesNamed('handCardsAdded') as $notice) {
            self::assertNotNull($notice->to);
        }
        // 窓は開かない（効果なし）
        self::assertNotContains(StateId::EFFECT_WINDOW_OPEN, $this->walker->visited);
    }

    /**
     * Studio で全員が「Waiting for other players」で止まった不具合の再現。BGA は getArgs を onEnteringState の前に呼ぶので、
     * 入場前の args（参加者・確定済み・_private）が、入場後と同じでなければならない。セットアップの配備（61）を含む通しで確かめる。
     */
    public function testArgsBeforeEnteringMatchArgsAfterEntering(): void
    {
        $this->startGame();
        $waiting = $this->walker->run(StateId::SETUP_LAUNCH_PAD_NEXT);
        // 61 の args に両方のプレイヤーが参加者として載り、_private がある（入場前後の一致は次のテストで確かめる）
        $this->walker->play($waiting, [], [StateId::DEPLOY_MAIN_CHOOSE]);
        $args = $this->machine->choice(StateId::DEPLOY_MAIN_CHOOSE)->args();
        self::assertSame([101, 102], $args['participants']);
        self::assertSame([101, 102], array_keys($args['_private']));
        self::assertArrayHasKey('targets', $args['_private'][101]);
    }

    /** 通しで、通った全員同時の状態すべてで入場前後の args が同じ。 */
    public function testArgsBeforeEnteringMatchInAWholeRound(): void
    {
        $this->startGame();
        $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        self::assertSame([], $this->walker->staleArgsStates);
        self::assertContains(StateId::DEPLOY_MAIN_CHOOSE, $this->walker->visited);
        self::assertNotContains(62, $this->walker->visited, '62 は欠番（配備のボーナスは 61 で同時に選ぶ）');
    }

    public function testMulliganDiscardsAndRedraws(): void
    {
        $this->startGame(StartingHand::Mulligan);
        $waiting = $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::SETUP_MULLIGAN]);
        self::assertSame(StateId::SETUP_MULLIGAN, $waiting);
        $before = $this->hand(101);
        $discard = array_slice($before, 0, 2);
        $beforeOther = $this->hand(102);

        $choices = [StateId::SETUP_MULLIGAN => static fn(int $id): array => ['discard' => $id === 101 ? $discard : []]];
        $waiting = $this->walker->play($waiting, $choices, [StateId::PLANNING_PLOT]);

        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        self::assertContains(StateId::SETUP_MULLIGAN_APPLY, $this->walker->visited);
        self::assertCount(5, $this->hand(101));
        self::assertSame($beforeOther, $this->hand(102));
        self::assertSame([], array_values(array_intersect($discard, $this->hand(101))));
        self::assertEqualsCanonicalizing($discard, $this->cards->idsIn(CardLocation::Discard));

        $discarded = $this->walker->noticesNamed('cardsDiscarded');
        self::assertCount(1, $discarded);
        self::assertSame('mulligan', $discarded[0]->args['reason']);
        self::assertEqualsCanonicalizing($discard, $discarded[0]->args['cards']);
        $added = $this->walker->noticesNamed('handCardsAdded');
        self::assertCount(1, $added);
        self::assertSame(101, $added[0]->to);
        self::assertCount(2, $added[0]->args['cards']);
    }

    public function testDeployLaunchAndOrbitInOneRound(): void
    {
        $this->startGame();
        $sat = $this->machine->ctx->services->satellites->forPlayer(101)[0];
        self::assertSame(SatelliteShape::Hammer, $sat->shape);
        $this->walker->run(StateId::SETUP_LAUNCH_PAD_NEXT);

        // セットアップの配備フェイズ: 101 は一番安いカードを Hammer に配備し、ボーナスで 1 枚引く
        $deploy = $this->machine->ctx->phases->deploy();
        $cheapest = null;
        $cost = 99;
        foreach ($this->hand(101) as $cardId) {
            $total = $deploy->targets(101, $cardId)[0]->cost->total();
            if ($total < $cost) {
                [$cheapest, $cost] = [$cardId, $total];
            }
        }
        self::assertNotNull($cheapest);
        $this->playerValues->setMaya(101, 60);
        $this->playerValues->setMaya(102, 60);
        $choices = [
            StateId::DEPLOY_MAIN_CHOOSE => fn(int $id): ?array => $id === 101
                ? ['op' => 'deploy', 'card' => $cheapest, 'sat' => $sat->id, 'extra' => ['op' => 'draw']]
                : null,
        ];
        $waiting = $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, $choices, [StateId::PLANNING_PLOT]);
        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        self::assertSame([$cheapest], $this->cards->deployedIds($sat->id));
        self::assertSame(60 - $cost, $this->playerValues->maya(101));
        self::assertCount(5 - 1 + 1, $this->hand(101));
        $deployed = $this->walker->noticesNamed('cardDeployed');
        self::assertCount(1, $deployed);
        self::assertSame($cheapest, $deployed[0]->args['card']);
        self::assertSame($sat->id, $deployed[0]->args['sat_id']);

        // 第 1 ラウンド: 101 は III 打ち上げ・転回（メインで打ち上げ、ボーナスで予算 +1）、102 は IV 通信確立（できることがない）
        $this->playerValues->setMaya(101, 60);
        $budgetBefore = $this->playerValues->budget(101);
        $choices = [
            StateId::PLANNING_PLOT => static fn(int $id): array => ['action' => $id === 101 ? 3 : 4],
            StateId::LAUNCH_MAIN_CHOOSE => fn(int $id): ?array => $id === 101 ? ['op' => 'launch', 'sat' => $sat->id, 'dir' => 'NE'] : null,
            StateId::LAUNCH_BONUS_CHOOSE => static fn(int $id): ?array => $id === 101 ? ['op' => 'budget'] : null,
        ];
        $waiting = $this->walker->play($waiting, $choices, [StateId::PLANNING_PLOT]);
        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        self::assertSame(2, $this->globals->roundNo());

        // 打ち上げた衛星は循環フェイズで 1 区域動き、燃料を 1 消費する
        $launched = $this->machine->ctx->services->satellites->get($sat->id);
        self::assertTrue($launched->isActive());
        self::assertSame($budgetBefore + 1, $this->playerValues->budget(101));
        self::assertSame(1, $launched->fuelUsed);
        $padCell = $this->playerValues->padCell(101);
        self::assertNotSame($padCell, $launched->cell);
        self::assertContains($launched->direction, [Direction::NE, Direction::SE, Direction::NW, Direction::SW]);

        $names = $this->walker->noticeNames();
        foreach (['satelliteLaunched', 'budgetChanged', 'satellitesMoved', 'fuelChanged', 'actionPassed'] as $name) {
            self::assertContains($name, $names, $name);
        }
        $passes = array_filter(
            $this->walker->noticesNamed('actionPassed'),
            static fn($n): bool => $n->args['auto'] === true,
        );
        self::assertNotEmpty($passes);
        $moved = $this->walker->noticesNamed('satellitesMoved');
        self::assertSame($sat->id, $moved[0]->args['moves'][0]['sat_id']);
        self::assertSame($padCell, $moved[0]->args['moves'][0]['from_cell']);
    }

    public function testGameEndsWithSixteenLinksCashesOutAndScores(): void
    {
        $this->startGame();
        $waiting = $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        $pad = $this->playerValues->padCell(102);
        $placements = [];
        $cell = 0;
        while (count($placements) < 16) {
            if ($cell !== $pad && !in_array($cell, $this->playerValues->padCells(), true)) {
                $placements[] = ['playerId' => 102, 'cell' => $cell];
            }
            $cell++;
        }
        $this->machine->ctx->services->links->commit($placements, 1, 1);
        $handBefore = count($this->hand(101));
        $mayaBefore = $this->playerValues->maya(101);

        $result = $this->walker->play($waiting);

        self::assertSame(0, $result);
        self::assertSame(1, $this->globals->roundNo());
        self::assertSame([102], $this->globals->endConditionPlayers());
        self::assertSame(FlowPhase::End, $this->globals->flowPhase());
        self::assertContains(StateId::END_HAND_CASH_OUT, $this->walker->visited);
        self::assertSame(StateId::FINAL_SCORING, $this->walker->visited[array_key_last($this->walker->visited)]);

        $end = $this->walker->noticesNamed('gameEndTriggered');
        self::assertCount(1, $end);
        self::assertSame([102], $end[0]->args['players']);
        self::assertSame(['links'], $end[0]->args['reasons']);

        // 手札は 1 枚 3 マヤで換金されて捨て札へ。名声計算（II）は換金後のマヤで、二重に換金しない（申し送り N-3）
        $cashOut = $this->walker->noticesNamed('handCashedOut');
        self::assertCount(2, $cashOut);
        self::assertSame(3 * $handBefore, $cashOut[0]->args['maya_gain']);
        $afterMaya = $this->playerValues->maya(101);
        self::assertSame($mayaBefore + 21 + 3 * $handBefore, $afterMaya);
        self::assertSame([], $this->hand(101));
        $detail = $this->playerValues->fameDetail(101);
        self::assertNotNull($detail);
        self::assertSame($afterMaya, $detail['II']['maya']);
        self::assertSame(0, $detail['IX']['points']);
        $detail102 = $this->playerValues->fameDetail(102);
        self::assertNotNull($detail102);
        self::assertSame(3, $detail102['IX']['points']);

        $scores = $this->walker->noticesNamed('finalScore');
        self::assertCount(2, $scores);
        self::assertSame([101, 102], array_map(static fn($n): int => $n->args['player_id'], $scores));
        self::assertSame($detail['total'], $scores[0]->args['fame']);
        self::assertCount(2, $scores[0]->args['missions']);
    }

    public function testSellingIsOnlyForParticipantsAndNotForChosenCards(): void
    {
        $this->startGame();
        $waiting = $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        $hand = $this->hand(101);
        $maya = $this->playerValues->maya(101);

        $notices = $this->machine->sell(101, [$hand[0]], StateId::PLANNING_PLOT);
        self::assertSame($maya + 3, $this->playerValues->maya(101));
        self::assertNotContains($hand[0], $this->hand(101));
        $names = array_map(static fn($n): string => $n->name, $notices);
        self::assertSame(['cardsDiscarded', 'mayaChanged', 'countersUpdate'], $names);
        self::assertSame([$hand[0]], $notices[0]->args['cards']);

        // 売却できない状態では拒否する。手札にないカードも拒否する
        try {
            $this->machine->sell(101, [$hand[1]], StateId::ACTION_PHASE_ROUTER);
            self::fail('selling outside a choice state must be rejected');
        } catch (RuleViolation) {
            self::assertTrue(true);
        }
        $this->expectException(RuleViolation::class);
        $this->machine->sell(101, [$hand[0]], StateId::PLANNING_PLOT);
    }

    public function testChosenCardCannotBeSold(): void
    {
        $this->startGame();
        $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::DEPLOY_MAIN_CHOOSE]);
        $hand = $this->hand(101);
        $sat = $this->machine->ctx->services->satellites->forPlayer(101)[0];
        $this->machine->choice(StateId::DEPLOY_MAIN_CHOOSE)->confirm(101, ['op' => 'deploy', 'card' => $hand[0], 'sat' => $sat->id]);

        $this->expectException(RuleViolation::class);
        $this->machine->sell(101, [$hand[0]], StateId::DEPLOY_MAIN_CHOOSE);
    }

    public function testArgsAndAllDatasKeepPrivateInformationPrivate(): void
    {
        $this->startGame();
        $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        $flow = $this->machine->choice(StateId::PLANNING_PLOT);
        $flow->confirm(101, ['action' => 4]);

        $args = $flow->args();
        self::assertSame([101, 102], $args['participants']);
        self::assertSame([101], $args['confirmed']);
        self::assertSame(['participants', 'confirmed', '_private'], array_keys($args));
        self::assertSame(['action' => 4], $args['_private'][101]['myChoice']);
        self::assertNull($args['_private'][102]['myChoice']);
        self::assertSame([1, 2, 3, 4, 5], $args['_private'][101]['selectable']);

        $view = new PublicView($this->machine->ctx);
        $mine = $view->build(101);
        $theirs = $view->build(102);
        $spectator = $view->build(999);
        self::assertSame($this->hand(101), $mine['myHand']);
        self::assertSame($this->hand(102), $theirs['myHand']);
        self::assertNotSame($mine['myHand'], $theirs['myHand']);
        self::assertSame([], $spectator['myHand']);
        self::assertSame([], $spectator['myMissions']);
        self::assertCount(2, $mine['myMissions']);
        self::assertSame(5, $mine['playerData'][102]['handCount']);
        self::assertSame(2, $mine['playerData'][102]['missionCount']);
        // 山札は枚数だけ、他人の手札・ミッション・選択内容は含まれない
        self::assertIsInt($mine['deckCount']);
        $json = json_encode($theirs, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('"action":4', $json);
        self::assertNull($mine['finalScores']);
        self::assertNull($mine['window']);
        self::assertSame([], $mine['playerData'][101]['satellites'][0]['cards']);
    }

    /** 窓の位置で効果がある（WindowGate が true）ときも、30 → 32 を通って戻り先のドライバから続きを進める（009 §6.2、V-4 の整数遷移） */
    public function testEveryWindowRoutesThroughStates30To32AndReturns(): void
    {
        $this->startGame();
        $gate = new class implements WindowGate {
            /** @var list<string> */
            public array $asked = [];

            public function needsWindow(string $windowId): bool
            {
                $this->asked[] = $windowId;
                return true;
            }
        };
        $this->machine = new Machine(new FlowContext(
            $this->db,
            $this->globals,
            new SeededRandom(5),
            static fn(int $id): string => "Player$id",
            $gate,
        ));
        $this->walker = new StateWalker($this->machine);
        $mayaAtStart = $this->playerValues->maya(101);

        $waiting = $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        $plot = static fn(int $playerId): array => ['action' => $playerId === 101 ? 1 : 5];
        $waiting = $this->walker->play($waiting, [StateId::PLANNING_PLOT => $plot], [StateId::PLANNING_PLOT]);

        self::assertSame(StateId::PLANNING_PLOT, $waiting);
        self::assertSame(2, $this->globals->roundNo());
        self::assertSame($mayaAtStart + 21, $this->playerValues->maya(101));
        self::assertContains(StateId::EFFECT_WINDOW_OPEN, $this->walker->visited);
        // 選択が要る効果がないので 31 には入らない
        self::assertNotContains(StateId::EFFECT_WINDOW_RESOLVE, $this->walker->visited);
        self::assertContains(StateId::EFFECT_WINDOW_BARRIER, $this->walker->visited);
        foreach (['PL1', 'PL2', 'B0', 'XD', 'B2', 'B3', 'DE', 'PB', 'O0', 'O1', 'O4'] as $windowId) {
            self::assertContains($windowId, $gate->asked, $windowId);
        }
        // 窓を閉じるたびに戻り先と窓の情報が空になる
        self::assertNull($this->globals->windowId());
        self::assertNull($this->globals->windowResume());
        $closed = $this->walker->noticesNamed('windowClosed');
        self::assertNotEmpty($closed);
        self::assertSame(['window_id', 'events'], array_keys($closed[0]->args));
    }
}
