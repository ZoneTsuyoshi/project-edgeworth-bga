<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Cards;

use Bga\Games\ProjectEdgeworth\Board\Direction;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\EffectEngine;
use Bga\Games\ProjectEdgeworth\Effects\EffectViolation;
use Bga\Games\ProjectEdgeworth\Effects\Query\EngineOrbitModifiers;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\FieldType;
use Bga\Games\ProjectEdgeworth\Effects\Question\Question;
use Bga\Games\ProjectEdgeworth\Effects\Question\QuestionType;
use Bga\Games\ProjectEdgeworth\Flow\OrbitSatellite;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;
use ProjectEdgeworthTests\Support\Effects\E4Fixture;
use PHPUnit\Framework\TestCase;

/**
 * 効果 E-4（衛星の操作）11 種の結線テスト: 本物の `CardEffects`・部品・条件・盤面（Earth の標準面）で、転回（R08）・移動（R07、003 の反射と
 * 左右の接続）・燃料の移し替え（R06）・入れ替え（R13）と、`TURNED` / `ORBIT_ENTERED` の連鎖を確かめる
 * （011 §7・§11-3 の T01・T06・T07・T09、FAQ 行 4・12〜15・17・18・19・29・34・54・59）。
 *
 * 区域 ID: 26 = (4,2) A、14 = (5,1) A、3 = (6,0) B、15 = (7,1) B、23 = (23,1)、0 = (0,0)。燃料庫の数 C = 衛星カード（`active()` の `baseFuel`）+ 配備カード。
 */
final class E4EffectsTest extends TestCase
{
    private const P1 = 1;

    /** @param list<Question> $questions */
    private function ask(array $questions, string $cardKey): Question
    {
        foreach ($questions as $q) {
            if ($q->cardKey === $cardKey) {
                return $q;
            }
        }
        $this->fail("no question for $cardKey");
    }

    private function entry(EffectEngine $engine, string $cardKey, int $player = self::P1): int
    {
        return (int) $this->ask($engine->questionsFor($player), $cardKey)->entryId;
    }

    /** @param array<string, mixed> $fields @param list<int> $sell */
    private function use(EffectEngine $engine, string $cardKey, array $fields = [], array $sell = [], int $player = self::P1): void
    {
        $engine->useEffect($player, $this->entry($engine, $cardKey, $player), new Answer($fields, $sell));
    }

    private function budgetFree(): GameEvent
    {
        return new GameEvent(Hook::BudgetFree, null);
    }

    private function entered(int $sat, int $cell, string $from = 'A', string $to = 'A'): GameEvent
    {
        return new GameEvent(Hook::OrbitEntered, self::P1, $sat, null, null, $cell, 0, ['fromRegion' => $from, 'toRegion' => $to]);
    }

    /** @return list<array{int, ?int}> 状態 [cell, c] */
    private function stateOf(E4Fixture $f, int $sat): array
    {
        $s = $f->game->satelliteState($sat);
        $this->assertNotNull($s);

        return [[(int) $s->cell, $s->fuelUsed]];
    }

    // ---- P004 軌道修正士（R07 + C1(2)、B3） ----

    public function testP004PaysTwoMayaAndMovesTheChosenSatelliteOneAreaSpendingOneFuel(): void
    {
        $f = new E4Fixture(10);
        $f->active(26, Direction::NE, ['P004'], 4);          // 効果を持つ衛星
        $other = $f->active(26, Direction::NE, [], 4);       // 選ぶ衛星
        $engine = $f->engine();
        $engine->openWindow('B3', [$this->budgetFree()]);

        $q = $this->ask($engine->questionsFor(self::P1), 'course_corrector');
        $this->assertFalse($q->mandatory);
        $this->assertSame(2, $q->cost['amount']);
        $this->assertSame(FieldType::Satellite, $q->fields[0]->type);
        $this->assertSame([1, $other], $q->fields[0]->candidates);

        $this->use($engine, 'course_corrector', ['satellite' => $other]);

        $this->assertSame(8, $f->game->maya[self::P1]);
        $s = $f->game->satelliteState($other);
        $this->assertSame([14, Direction::NE, 1], [$s?->cell, $s?->direction, $s?->fuelUsed]);
        $this->assertSame(['P:4' => true], $f->game->used);
        $this->assertSame(1, $engine->closeWindow()->resolvedCount);
    }

    public function testP004DoesNotCauseANearMissWhenTheDestinationHoldsAnotherSatellite(): void
    {
        // FAQ 行 4: 1 区域進めた先に別の衛星がいても、燃料は移動の 1 だけ（ニアミスの 2 は O3 の判定のときだけ）
        $f = new E4Fixture(10);
        $mover = $f->active(26, Direction::NE, ['P004'], 4);
        $f->active(14, Direction::SW, [], 4);
        $engine = $f->engine();
        $engine->openWindow('B3', [$this->budgetFree()]);
        $this->use($engine, 'course_corrector', ['satellite' => $mover]);

        $this->assertSame(14, $f->game->satelliteState($mover)?->cell);
        $this->assertSame(1, $f->game->satelliteState($mover)?->fuelUsed);
        // 燃料の消費は移動の 1 回だけ（ニアミスの消費は O3 の判定のときだけ）
        $this->assertSame([['moveSatellite', 14], ['spendFuel', 1]], array_values(array_filter(
            $f->game->journal,
            static fn(array $j): bool => in_array($j[0], ['moveSatellite', 'spendFuel'], true),
        )));
    }

    public function testP004ReflectsAtTheTopEdgeWithoutTurning(): void
    {
        // 003 §4.2: 上端 (y = 0) で北東に進む衛星は先に反射して南東へ。反射は転回ではないので「転回したとき」の P037 は反応しない
        $f = new E4Fixture(10);
        $mover = $f->active(3, Direction::NE, ['P004', 'P037'], 4);
        $engine = $f->engine();
        $engine->openWindow('B3', [$this->budgetFree()]);
        $this->use($engine, 'course_corrector', ['satellite' => $mover]);

        $s = $f->game->satelliteState($mover);
        $this->assertSame([15, Direction::SE], [$s?->cell, $s?->direction]);
        $this->assertSame([], $f->game->hand[self::P1], 'P037 は反応しない');
        $this->assertFalse($engine->playersNeedingInput() !== []);
    }

    public function testP004WrapsAroundTheLeftAndRightEdges(): void
    {
        $f = new E4Fixture(10);
        $a = $f->active(23, Direction::NE, ['P004'], 4);
        $engine = $f->engine();
        $engine->openWindow('B3', [$this->budgetFree()]);
        $this->use($engine, 'course_corrector', ['satellite' => $a]);
        $this->assertSame(0, $f->game->satelliteState($a)?->cell);
        $this->assertSame(Direction::NE, $f->game->satelliteState($a)?->direction);

        $g = new E4Fixture(10);
        $c = $g->active(23, Direction::SE, ['P004'], 4);
        $engine2 = $g->engine();
        $engine2->openWindow('B3', [$this->budgetFree()]);
        $this->use($engine2, 'course_corrector', ['satellite' => $c]);
        // (23,1) 南東 → (24,2) = (0,2) = 区域 24
        $this->assertSame(24, $g->game->satelliteState($c)?->cell);
    }

    public function testP004CanTakeTheLastFuelOutOfTheTankButDoesNotLandThere(): void
    {
        // FAQ 59: 燃料コマが庫外に出ても、その時点では着陸しない（循環フェイズの O4 で判定）
        $f = new E4Fixture(10);
        $mover = $f->active(26, Direction::NE, ['P004'], 2, 4);   // C = 2 + 2 = 4、c = 4（庫内の最後）
        $engine = $f->engine();
        $engine->openWindow('B3', [$this->budgetFree()]);
        $this->use($engine, 'course_corrector', ['satellite' => $mover]);

        $s = $f->game->satelliteState($mover);
        $this->assertSame(5, $s?->fuelUsed);
        $this->assertTrue($s?->isOutOfFuel());
        $this->assertTrue($s?->isActive(), '着陸は O4 の判定だけ');
        $this->assertSame(14, $s?->cell);
    }

    public function testP004CannotMoveASatelliteThatIsOutOfFuelOrInactive(): void
    {
        $f = new E4Fixture(10);
        $f->satellite(self::P1, ['P004'], false);                  // 非稼働
        $f->active(26, Direction::NE, [], 2, 3);                   // C = 2、c = 3: 燃料切れ
        $engine = $f->engine();
        $report = $engine->openWindow('B3', [$this->budgetFree()]);

        $this->assertSame([], $report->playersNeedingInput, '動かせる衛星がないので問いも出ない');
        $this->assertSame([], $f->game->used);
        $this->assertSame(10, $f->game->maya[self::P1]);
    }

    public function testP004RejectsAnInactiveOrForeignSatelliteAndCanBePaidBySelling(): void
    {
        $f = new E4Fixture(1);
        $f->game->hand[self::P1] = [50];
        $mover = $f->active(26, Direction::NE, ['P004'], 4);
        $standby = $f->satellite(self::P1, [], false);
        $engine = $f->engine();
        $engine->openWindow('B3', [$this->budgetFree()]);
        $id = $this->entry($engine, 'course_corrector');

        try {
            $engine->useEffect(self::P1, $id, new Answer(['satellite' => $standby]));
            $this->fail('非稼働の衛星は選べない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        try {
            $engine->useEffect(self::P1, $id, new Answer(['satellite' => $mover]));
            $this->fail('1 マヤでは 2 マヤを払えない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $engine->useEffect(self::P1, $id, new Answer(['satellite' => $mover], [50]));
        $this->assertSame(2, $f->game->maya[self::P1]);   // 1 + 3 − 2
        $this->assertSame(14, $f->game->satelliteState($mover)?->cell);
    }

    // ---- P034 加速ブースター（R07 + C2、LE。T07） ----

    private function launched(int $sat): GameEvent
    {
        return new GameEvent(Hook::Launched, self::P1, $sat);
    }

    public function testP034LimitsTheAreasToWhereTheFuelLeavesTheTank(): void
    {
        // FAQ 15・T07: 燃料庫 8・消費 6 なら 3 区域まで（3 消費で庫外）。4 区域（5 マヤ）は選べない
        $f = new E4Fixture(10);
        $sat = $f->active(26, Direction::NE, ['P034'], 6, 6);      // C = 6 + 2 = 8
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($sat)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'acceleration_booster');
        $this->assertSame('PAY_MAYA_TIER', $q->cost['kind']);
        $this->assertSame([2, 3, 4], $q->fields[0]->candidates);

        try {
            $this->use($engine, 'acceleration_booster', ['tier' => 5]);
            $this->fail('4 区域は庫外に出た先まで進むことになる');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $this->use($engine, 'acceleration_booster', ['tier' => 4]);

        // 26 → 14 → 3 → 反射して 15（南東）。3 区域で 3 消費 → c = 9 = C + 1（庫外）。着陸はしない（FAQ 59）
        $s = $f->game->satelliteState($sat);
        $this->assertSame([15, Direction::SE, 9], [$s?->cell, $s?->direction, $s?->fuelUsed]);
        $this->assertTrue($s?->isOutOfFuel());
        $this->assertTrue($s?->isActive());
        $this->assertSame(6, $f->game->maya[self::P1]);
    }

    public function testP034PaysTheChosenTierAndNoNearMissOrEntryIsRaised(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(26, Direction::NE, ['P034', 'P093'], 6);
        $f->game->linkedCells[self::P1] = [14, 3];
        $f->active(14, Direction::SW, [], 4);                     // 通過する区域にいる別の衛星
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($sat)]);
        $this->use($engine, 'acceleration_booster', ['tier' => 3]);

        $this->assertSame(7, $f->game->maya[self::P1]);
        $this->assertSame([3, 2], $this->stateOf($f, $sat)[0], '2 区域進んで 2 消費。通過した 14 の別の衛星とのニアミスの消費はない');
        $this->assertSame([], $f->game->used, 'ORBIT_ENTERED を起こさないので P093 は反応しない（P034 は ON_LAUNCH で使用済みにならない）');
        $this->assertSame([], $engine->playersNeedingInput());
    }

    public function testP034CanBePaidBySelling(): void
    {
        $f = new E4Fixture(1);
        $f->game->hand[self::P1] = [50, 51];
        $sat = $f->active(26, Direction::NE, ['P034'], 6);
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($sat)]);
        $id = $this->entry($engine, 'acceleration_booster');
        try {
            $engine->useEffect(self::P1, $id, new Answer(['tier' => 4]));
            $this->fail('1 マヤでは 4 マヤを払えない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $engine->useEffect(self::P1, $id, new Answer(['tier' => 4], [50]));
        $this->assertSame(0, $f->game->maya[self::P1]);   // 1 + 3 − 4
        $this->assertSame(3, $f->game->satelliteState($sat)?->fuelUsed);
    }

    // ---- P038 燃料交換管（R06、LE。T09） ----

    public function testP038MovesFuelWithinTheLoadedFuelAndRecoveryStopsAtTheInitialSlot(): void
    {
        // FAQ 18・19: 搭載燃料は衛星カードと配備カードの燃料庫の合計。回復は初期燃料庫より下に戻らない
        $f = new E4Fixture(10);
        $launched = $f->active(26, Direction::NE, [], 4);          // C = 4、c = 0 → 残り 4
        $f->active(14, Direction::NE, ['P038'], 4, 3);             // 他の稼働中の衛星（c = 3）
        $recipient = $f->active(15, Direction::SE, [], 4, 3);
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($launched)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'fuel_transfer_pipe');
        $this->assertSame([0, 1, 2, 3, 4], $q->fields[0]->candidates);
        $this->assertSame([2, 3], $q->fields[1]->candidates, '打ち上げた衛星以外で、回復できる衛星');

        try {
            $this->use($engine, 'fuel_transfer_pipe', ['amount' => 5, 'satellite' => $recipient]);
            $this->fail('残り燃料を超えては減らせない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $this->use($engine, 'fuel_transfer_pipe', ['amount' => 4, 'satellite' => $recipient]);

        $this->assertSame(4, $f->game->satelliteState($launched)?->fuelUsed, '4 減らした（庫外には出ない）');
        $this->assertSame(0, $f->game->satelliteState($recipient)?->fuelUsed, '3 しか消費していないので 0 で止まる');
        $this->assertSame(['P:38' => true], $f->game->used);
    }

    public function testP038DoesNotTriggerWhenNothingCanBeRecovered(): void
    {
        $f = new E4Fixture(10);
        $launched = $f->active(26, Direction::NE, [], 4);
        $f->active(14, Direction::NE, ['P038'], 4);               // 他の衛星は満タン
        $engine = $f->engine();
        $report = $engine->openWindow('LE', [$this->launched($launched)]);

        $this->assertSame([], $report->playersNeedingInput);
        $this->assertSame([], $f->game->used);
    }

    public function testP038SeesTheFuelAfterP034AndAsksTheOrderBecauseTheyConflict(): void
    {
        // S04: P034（M− F− P Cr）と P038（F− F+ Cr）は燃料で衝突する → ORDER を出す
        $f = new E4Fixture(10);
        $launched = $f->active(26, Direction::NE, ['P034', 'P038'], 6);
        $f->active(14, Direction::NE, [], 4, 3);
        $engine = $f->engine();
        $report = $engine->openWindow('LE', [$this->launched($launched)]);

        $this->assertSame([self::P1], $report->playersNeedingInput);
        $questions = $engine->questionsFor(self::P1);
        $this->assertCount(1, $questions);
        $this->assertSame(QuestionType::Order, $questions[0]->type);
        $entries = array_column($questions[0]->entries, 'cardKey');
        sort($entries);
        $this->assertSame(['acceleration_booster', 'fuel_transfer_pipe'], $entries);
    }

    // ---- P024 スワップ技師（R13、DE。T06） ----

    private function deployed(int $sat, string $cardId): GameEvent
    {
        return new GameEvent(Hook::Deployed, self::P1, $sat, (int) substr($cardId, 1), ProjectCards::get($cardId)->type);
    }

    public function testP024SwapsActivityPositionAndFuelAndDoesNotLandWhenTheFuelOverflows(): void
    {
        // FAQ 12〜14: 入れ替えで燃料庫を超えても即着陸しない
        $f = new E4Fixture(10);
        $standby = $f->satellite(self::P1, ['P024'], false);       // C = 0 + 2（P024）
        $f->game->baseFuel[$standby] = 0;
        $active = $f->active(26, Direction::NE, [], 6, 3);         // C = 6、c = 3
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($standby, 'P024')]);
        $q = $this->ask($engine->questionsFor(self::P1), 'swap_technician');
        $this->assertSame([$active], $q->fields[0]->candidates);

        $this->use($engine, 'swap_technician', ['satellite' => $active]);

        $host = $f->game->satelliteState($standby);
        $this->assertTrue($host?->isActive());
        $this->assertSame([26, Direction::NE], [$host?->cell, $host?->direction]);
        $this->assertSame(3, $host?->fuelUsed, 'c = 3 > C = 2: 燃料切れだが着陸していない');
        $this->assertTrue($host?->isOutOfFuel());
        $this->assertFalse($f->game->satelliteState($active)?->isActive(), '入れ替え先は非稼働になる');
        $this->assertSame(0, $f->game->satelliteState($active)?->fuelUsed);
        $this->assertSame(1, $engine->closeWindow()->resolvedCount);
    }

    public function testP024KeepsAnOutOfFuelSatelliteOutOfFuelEvenIntoBiggerTank(): void
    {
        // FAQ 13: 庫外に出た燃料コマは配備でも回復効果でも戻らない
        $f = new E4Fixture(10);
        $host = $f->active(14, Direction::SE, ['P024'], 8);        // C = 10、c = 0
        $out = $f->active(26, Direction::NE, [], 2, 3);            // C = 2、c = 3 = C + 1（燃料切れ）
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($host, 'P024')]);
        $this->use($engine, 'swap_technician', ['satellite' => $out]);

        $this->assertTrue($f->game->satelliteState($host)?->isOutOfFuel());
        $this->assertSame(11, $f->game->satelliteState($host)?->fuelUsed);
        $this->assertSame([14, Direction::SE], [$f->game->satelliteState($out)?->cell, $f->game->satelliteState($out)?->direction]);
        $this->assertSame(0, $f->game->satelliteState($out)?->fuelUsed);
    }

    public function testP024IsOptionalAndNeedsAnotherActiveSatellite(): void
    {
        $f = new E4Fixture(10);
        $standby = $f->satellite(self::P1, ['P024'], false);
        $engine = $f->engine();
        $report = $engine->openWindow('DE', [$this->deployed($standby, 'P024')]);
        $this->assertSame([], $report->playersNeedingInput, '入れ替える稼働中の衛星がない');

        $g = new E4Fixture(10);
        $standby = $g->satellite(self::P1, ['P024'], false);
        $other = $g->active(26, Direction::NE, [], 4);
        $engine = $g->engine();
        $engine->openWindow('DE', [$this->deployed($standby, 'P024')]);
        $engine->skipEffect(self::P1, $this->entry($engine, 'swap_technician'));
        $this->assertTrue($g->game->satelliteState($other)?->isActive());
        $this->assertFalse($g->game->satelliteState($standby)?->isActive());
        $this->assertSame([], $g->game->used);
    }

    // ---- P044 編隊同期装置（R08、TE） ----

    private function turned(int $sat): GameEvent
    {
        return new GameEvent(Hook::Turned, self::P1, $sat);
    }

    public function testP044TurnsThisSatelliteWhenAnotherOwnSatelliteTurnsAndRaisesANewTurnedEvent(): void
    {
        $f = new E4Fixture(10);
        $other = $f->active(26, Direction::NE, [], 4);
        $mine = $f->active(14, Direction::NE, ['P044', 'P037'], 4);   // P037: この衛星が転回したとき 1 枚引く
        $f->game->deck = [61, 62, 63];
        $engine = $f->engine();
        $engine->openWindow('TE', [$this->turned($other)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'formation_sync_unit');
        $this->assertSame(FieldType::Direction, $q->fields[0]->type);
        $this->assertSame(['NE', 'NW', 'SE', 'SW'], $q->fields[0]->candidates);

        $this->use($engine, 'formation_sync_unit', ['direction' => 'SW']);

        $this->assertSame(Direction::SW, $f->game->satelliteState($mine)?->direction);
        $this->assertSame([61], $f->game->hand[self::P1], '新しい TURNED で P037 が 1 枚引いた');
        $this->assertSame(Direction::NE, $f->game->satelliteState($other)?->direction);
        $this->assertSame(['P:44' => true, 'P:37' => true], $f->game->used);
    }

    public function testP044DoesNotReactToItsOwnSatellitesTurn(): void
    {
        $f = new E4Fixture(10);
        $mine = $f->active(14, Direction::NE, ['P044'], 4);
        $engine = $f->engine();
        $report = $engine->openWindow('TE', [$this->turned($mine)]);
        $this->assertSame([], $report->playersNeedingInput, 'K01 OTHER_OWN');
    }

    public function testP044RaisedTurnedEventDoesNotMakeItReactAgain(): void
    {
        // 007 §8-4: P044 が起こした新しい TURNED には、P044 自身（自分の衛星の転回）も使用済みなので反応しない。連鎖はここで止まる
        $f = new E4Fixture(10);
        $a = $f->active(26, Direction::NE, [], 4);
        $b = $f->active(14, Direction::NE, ['P044'], 4);
        $engine = $f->engine();
        $engine->openWindow('TE', [$this->turned($a)]);
        $this->assertSame([self::P1], $engine->playersNeedingInput());

        $this->use($engine, 'formation_sync_unit', ['direction' => 'NW']);

        $this->assertSame(Direction::NW, $f->game->satelliteState($b)?->direction);
        $this->assertSame([], $engine->playersNeedingInput());
        $this->assertSame(1, $engine->closeWindow()->resolvedCount);
    }

    public function testP044IsNotAvailableToASatelliteThatCannotTurn(): void
    {
        // F01 TURN_THIS（P046）の衛星は転回できない（FAQ 行 54）
        $f = new E4Fixture(10);
        $other = $f->active(26, Direction::NE, [], 4);
        $f->active(14, Direction::NE, ['P044', 'P046'], 4);
        $engine = $f->engine();
        $report = $engine->openWindow('TE', [$this->turned($other)]);
        $this->assertSame([], $report->playersNeedingInput);
        $this->assertSame([], $f->game->used);
    }

    public function testATurnFacingTheSameWayIsStillATurnAndRejectsInvalidDirections(): void
    {
        // FAQ 17: 向きを変えない 360 度の転回も転回（TURNED を起こす）
        $f = new E4Fixture(10);
        $other = $f->active(26, Direction::NE, [], 4);
        $mine = $f->active(14, Direction::NE, ['P044', 'P037'], 4);
        $f->game->deck = [61];
        $engine = $f->engine();
        $engine->openWindow('TE', [$this->turned($other)]);
        $id = $this->entry($engine, 'formation_sync_unit');
        foreach (['E', 'W', 'UP', ''] as $bad) {
            try {
                $engine->useEffect(self::P1, $id, new Answer(['direction' => $bad]));
                $this->fail("$bad is not a facing");
            } catch (EffectViolation) {
                $this->addToAssertionCount(1);
            }
        }
        $engine->useEffect(self::P1, $id, new Answer(['direction' => 'NE']));
        $this->assertSame(Direction::NE, $f->game->satelliteState($mine)?->direction);
        $this->assertSame([61], $f->game->hand[self::P1]);
    }

    // ---- P048 可変軌道装置（R08、KE。FAQ 29 の連鎖） ----

    public function testP048TurnsWhenThisSatelliteLinkedAndChainsToP037(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(26, Direction::NE, ['P048', 'P037'], 4);
        $other = $f->active(14, Direction::NE, ['P048'], 4);
        $f->game->deck = [61, 62];
        $engine = $f->engine();
        $engine->openWindow('KE', [new GameEvent(Hook::Linked, self::P1, $sat, null, null, 14)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'variable_orbit_unit');
        $this->assertSame(1, count($engine->questionsFor(self::P1)), '通信確立したのはこの衛星だけ');
        $this->assertSame(FieldType::Direction, $q->fields[0]->type);

        $this->use($engine, 'variable_orbit_unit', ['direction' => 'SE']);

        $this->assertSame(Direction::SE, $f->game->satelliteState($sat)?->direction);
        $this->assertSame([61], $f->game->hand[self::P1], '転回 → P037 が 1 枚引く');
        $this->assertSame(Direction::NE, $f->game->satelliteState($other)?->direction);
    }

    // ---- P081 ホーマンブースター（R07 + C1(2)、O1。T01） ----

    public function testP081MovesOneMoreAreaAfterEnteringAnOwnLinkedAreaAndSpendsOneFuel(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(14, Direction::NE, ['P081'], 4);
        $f->game->linkedCells[self::P1] = [14];
        $engine = $f->engine();
        $engine->openWindow('O1', [$this->entered($sat, 14)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'hohmann_booster');
        $this->assertSame(2, $q->cost['amount']);
        $this->assertSame([], $q->fields, '選ぶものはない（使うか使わないか）');

        $this->use($engine, 'hohmann_booster');

        $s = $f->game->satelliteState($sat);
        $this->assertSame([3, 1], [$s?->cell, $s?->fuelUsed]);
        $this->assertSame(8, $f->game->maya[self::P1]);
        $this->assertSame(['P:81' => true], $f->game->used);
    }

    public function testP081RaisesANewEntryThatTriggersOtherEntryEffects(): void
    {
        // 007 未確定 9: 追加移動でも突入の効果が発動する。14 (A) → 3 (B) で地域が変わるので P090 が転回できる
        $f = new E4Fixture(10);
        $sat = $f->active(14, Direction::NE, ['P081', 'P090'], 4);
        $f->game->linkedCells[self::P1] = [14];
        $engine = $f->engine();
        $engine->openWindow('O1', [$this->entered($sat, 14)]);
        $this->assertCount(1, $engine->questionsFor(self::P1), '最初の突入は地域が変わらないので P090 は出ない');
        $this->use($engine, 'hohmann_booster');

        $q = $this->ask($engine->questionsFor(self::P1), 'gimbal_control');
        $this->assertSame(FieldType::Direction, $q->fields[0]->type);
        $this->use($engine, 'gimbal_control', ['direction' => 'SW']);
        $this->assertSame(Direction::SW, $f->game->satelliteState($sat)?->direction);
        $this->assertSame([], $engine->playersNeedingInput());
    }

    public function testP081NeedsAnOwnLinkedAreaAndOnlyReactsOncePerRound(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(14, Direction::NE, ['P081'], 4);
        $engine = $f->engine();
        $report = $engine->openWindow('O1', [$this->entered($sat, 14)]);
        $this->assertSame([], $report->playersNeedingInput, '通信確立済みの区域ではない');

        $g = new E4Fixture(10);
        $sat = $g->active(14, Direction::NE, ['P081'], 4);
        $g->game->linkedCells[self::P1] = [14, 3];   // 追加移動の先も自分の区域: 自身の再発動は使用済みで起きない
        $engine = $g->engine();
        $engine->openWindow('O1', [$this->entered($sat, 14)]);
        $this->use($engine, 'hohmann_booster');
        $this->assertSame([], $engine->playersNeedingInput());
        $this->assertSame(3, $g->game->satelliteState($sat)?->cell);
    }

    public function testP081CannotBeUsedOutOfFuel(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(14, Direction::NE, ['P081'], 1, 4);      // C = 3、c = 4: 燃料切れ
        $f->game->linkedCells[self::P1] = [14];
        $engine = $f->engine();
        $report = $engine->openWindow('O1', [$this->entered($sat, 14)]);
        $this->assertSame([], $report->playersNeedingInput);
        $this->assertSame(10, $f->game->maya[self::P1]);
    }

    // ---- P082 トライアドブースター（R07、O1） ----

    public function testP082MovesOneExtraAreaWhenAllThreeTypesAreDeployedWithoutAnyEntryCondition(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(26, Direction::NE, ['P001', 'P040', 'P082'], 2);   // CREW + DEVICE + SOFTWARE
        $engine = $f->engine();
        $engine->openWindow('O1', [$this->entered($sat, 26)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'triad_booster');
        $this->assertSame('NONE', $q->cost['kind']);

        $this->use($engine, 'triad_booster');

        $this->assertSame([14, 1], $this->stateOf($f, $sat)[0]);
        $this->assertSame(10, $f->game->maya[self::P1]);
    }

    public function testP082NeedsAllThreeTypes(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(26, Direction::NE, ['P001', 'P082'], 2);   // DEVICE がない
        $engine = $f->engine();
        $report = $engine->openWindow('O1', [$this->entered($sat, 26)]);
        $this->assertSame([], $report->playersNeedingInput);
    }

    // ---- P090 ギムバル制御 / P091 カスケードブースト（K08）/ P095 フリップマニューバ（K07） ----

    public function testP090TurnsOnlyWhenTheSatelliteMovedBetweenRegionsAndAReflectionIsNotATurn(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(3, Direction::NE, ['P090'], 4);
        $engine = $f->engine();
        $report = $engine->openWindow('O1', [$this->entered($sat, 3, 'B', 'B')]);
        $this->assertSame([], $report->playersNeedingInput, '同じ地域内の移動');

        $g = new E4Fixture(10);
        $sat = $g->active(3, Direction::NE, ['P090'], 4);
        $engine = $g->engine();
        $engine->openWindow('O1', [$this->entered($sat, 3, 'A', 'B')]);
        $this->use($engine, 'gimbal_control', ['direction' => 'SE']);
        $this->assertSame(Direction::SE, $g->game->satelliteState($sat)?->direction);
        $this->assertSame(['P:90' => true], $g->game->used);
    }

    public function testP091MovesOneExtraAreaAfterChangingRegionsAndThatMoveCanTriggerAnotherEntry(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(26, Direction::NE, ['P091'], 4);
        $engine = $f->engine();
        // 26 (A) から北東へ → 14 (A) の移動。P091 の条件（地域が変わった）は満たす入力にして、その先の移動 14 → 3 が A → B
        $engine->openWindow('O1', [$this->entered($sat, 26, 'B', 'A')]);
        $this->use($engine, 'cascade_boost');
        $this->assertSame([14, 1], $this->stateOf($f, $sat)[0]);
        $this->assertSame([], $engine->playersNeedingInput());
    }

    public function testP091ChainsIntoAnotherRegionChangeAndTriggersP090(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(14, Direction::NE, ['P091', 'P090'], 4);
        $engine = $f->engine();
        $engine->openWindow('O1', [$this->entered($sat, 14, 'C', 'A')]);   // C → A に突入した直後
        $this->use($engine, 'cascade_boost');                                 // 14 → 3: A → B
        $this->assertSame(3, $f->game->satelliteState($sat)?->cell);
        // P090 は最初の突入（C → A）と追加移動の突入（A → B）の 2 件がプールにあり、同じ向きに触れるので衝突として ORDER を聞く
        $questions = $engine->questionsFor(self::P1);
        $this->assertSame(QuestionType::Order, $questions[0]->type);
        $engine->orderEffects(self::P1, array_column($questions[0]->entries, 'entryId'));
        $this->use($engine, 'gimbal_control', ['direction' => 'NW']);
        $this->assertSame(Direction::NW, $f->game->satelliteState($sat)?->direction);
        $this->assertSame([], $engine->playersNeedingInput(), '2 件目は使用済みで捨てられる');
        $this->assertSame(['P:91' => true, 'P:90' => true], $f->game->used);
    }

    public function testP095TurnsAfterEnteringAnOwnLinkedAreaIncludingTheLaunchSiteArea(): void
    {
        $f = new E4Fixture(10);
        $sat = $f->active(14, Direction::NE, ['P095'], 4);
        $engine = $f->engine();
        $report = $engine->openWindow('O1', [$this->entered($sat, 14)]);
        $this->assertSame([], $report->playersNeedingInput);

        $g = new E4Fixture(10);
        $sat = $g->active(14, Direction::NE, ['P095'], 4);
        $g->game->linkedCells[self::P1] = [14];                // 発射拠点の区域も通信確立済みとして扱う（FAQ 行 44）
        $engine = $g->engine();
        $engine->openWindow('O1', [$this->entered($sat, 14)]);
        $this->use($engine, 'flip_maneuver', ['direction' => 'SE']);
        $this->assertSame(Direction::SE, $g->game->satelliteState($sat)?->direction);
    }

    public function testTheOrbitPhaseModifiersStayAtZeroBecauseTheExtraMovesAreWindowEffects(): void
    {
        // 申し送り N-5: 追加移動（P081・P082・P091）と燃料回復（P083・P084）は O1 の窓の結果部品。同期の問い合わせ EngineOrbitModifiers は 0
        $f = new E4Fixture(10);
        $f->active(26, Direction::NE, ['P081', 'P082', 'P091', 'P083'], 4);
        $mods = new EngineOrbitModifiers($f->queries);
        $sat = new OrbitSatellite(1, self::P1, 26, Direction::NE, 0, 4);
        $this->assertSame(0, $mods->extraMoves($sat));
        $this->assertSame(0, $mods->entryFuelDelta($sat));
    }

    public function testTheHandlesOfAPlayerWithoutTheCardDoNotReact(): void
    {
        // 効果は自分のイベントにだけ反応する（007 §8-1）
        $f = new E4Fixture(10);
        $f->game->player(3, 10);
        $mine = $f->active(14, Direction::NE, ['P095'], 4);
        $f->game->linkedCells[self::P1] = [14];
        $engine = $f->engine();
        $report = $engine->openWindow('O1', [new GameEvent(Hook::OrbitEntered, 3, $mine, null, null, 14)]);
        $this->assertSame([], $report->playersNeedingInput);
    }
}
