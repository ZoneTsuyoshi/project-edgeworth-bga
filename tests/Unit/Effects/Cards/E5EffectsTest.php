<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Cards;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\EffectEngine;
use Bga\Games\projectedgeworth\Effects\EffectViolation;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\FieldType;
use Bga\Games\projectedgeworth\Effects\Question\Question;
use Bga\Games\projectedgeworth\Effects\Question\QuestionType;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E5Fixture;

/**
 * 効果 E-5（配備）6 種の結線テスト: 手札からの配備（P011・P045・P074・R005）、山札からの配備（P035）、付け替え（P022）を、
 * 本物の `CardEffects`・部品・条件で確かめる（011 §11-3 の T04・T05・T10・T21・T28、FAQ 行 9・10・11・16・20・55・69、
 * 申し送り N-1 の燃料切れの衛星への配備）。手札・山札のカード番号は実在のカード（P009 など）を使う。
 */
final class E5EffectsTest extends TestCase
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

    private function entry(EffectEngine $engine, string $cardKey): int
    {
        return (int) $this->ask($engine->questionsFor(self::P1), $cardKey)->entryId;
    }

    /** @param array<string, mixed> $fields @param list<int> $sell */
    private function use(EffectEngine $engine, string $cardKey, array $fields = [], array $sell = []): void
    {
        $engine->useEffect(self::P1, $this->entry($engine, $cardKey), new Answer($fields, $sell));
    }

    private function deployed(int $sat, string $cardId): GameEvent
    {
        return new GameEvent(Hook::Deployed, self::P1, $sat, (int) substr($cardId, 1), ProjectCards::get($cardId)->type);
    }

    private function launched(int $sat): GameEvent
    {
        return new GameEvent(Hook::Launched, self::P1, $sat);
    }

    // ---- P011 双配備技師 ----

    public function testP011DeploysADeviceToAnySatellitePayingTheCostPlusTheActiveSurcharge(): void
    {
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P011', 'P034'], 4);
        $b = $f->satellite(self::P1, [], false);
        $f->game->hand[self::P1] = [40, 10];                       // P040 DEVICE（コスト 9）、P010 CREW
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($a, 'P034')]);
        $q = $this->ask($engine->questionsFor(self::P1), 'twin_deploy_technician');

        $this->assertSame(FieldType::Pairs, $q->fields[0]->type);
        $this->assertSame([[40, $a], [40, $b]], $q->fields[0]->candidates, 'DEVICE だけ。稼働中も待機中も選べる');
        $costs = array_column($q->preview['costs'], 'cost', 'sat');
        $this->assertSame([$a => 14, $b => 9], $costs, '稼働中の衛星には追加配備コスト +5（FAQ 行 20）');

        $this->use($engine, 'twin_deploy_technician', ['pairs' => [[40, $b]]]);

        $this->assertSame(21, $f->game->maya[self::P1]);
        $this->assertSame([10], $f->game->hand[self::P1]);
        $this->assertSame([40], $f->game->deployedCards($b));
        $this->assertSame(['P:11' => true], $f->game->used);
    }

    public function testP011ChargesTheSurchargeOnAnActiveSatelliteAndRaisesANewDeployedEvent(): void
    {
        // 新しい DEPLOYED が起きる: A の P031（DEVICE を配備したとき予算 +1）は、P011 が配備した DEVICE にも反応する
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P011', 'P031', 'P034'], 4);
        $f->game->hand[self::P1] = [40];
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($a, 'P034')]);
        $this->use($engine, 'twin_deploy_technician', ['pairs' => [[40, $a]]]);

        $this->assertSame(16, $f->game->maya[self::P1], '9 + 5');
        $this->assertSame([40], array_slice($f->game->deployedCards($a), -1));
        $this->assertSame(1, $f->game->budget[self::P1], 'P031 は使用済みで 1 回だけ（元の配備と P011 の配備の 2 件が積まれても）');
        $this->assertSame([], $engine->playersNeedingInput());
    }

    public function testP011AskstheOrderWithP023AndAnAbilityToDeployTheCardDrawnFirst(): void
    {
        // S02: P011（H*）と P023（H+）は衝突して ORDER。P023 を先にすると、引いたカードを P011 で配備できる
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P011', 'P023', 'P034'], 4);
        $f->game->deck = [40, 101];
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($a, 'P034')]);

        $questions = $engine->questionsFor(self::P1);
        $this->assertSame(QuestionType::Order, $questions[0]->type);
        $byKey = array_column($questions[0]->entries, 'entryId', 'cardKey');
        $engine->orderEffects(self::P1, [$byKey['integrated_planner'], $byKey['twin_deploy_technician']]);

        $this->assertSame([40], $f->game->hand[self::P1], 'P023 が引いた');
        $q = $this->ask($engine->questionsFor(self::P1), 'twin_deploy_technician');
        $this->assertSame([[40, $a]], $q->fields[0]->candidates);
    }

    public function testP011RespectsTheFourCardLimitAndTheSeventhPrincesProhibition(): void
    {
        // 4 枚上限と P033（FAQ 行 55、T21）: 配備先から外れる
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P011', 'P034'], 4);
        $full = $f->satellite(self::P1, ['P010', 'P013', 'P023', 'P009'], false);
        $prince = $f->satellite(self::P1, ['P033'], false);
        $open = $f->satellite(self::P1, ['P009'], false);
        $f->game->hand[self::P1] = [40];
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($a, 'P034')]);
        $q = $this->ask($engine->questionsFor(self::P1), 'twin_deploy_technician');

        $this->assertSame([[40, $a], [40, $open]], $q->fields[0]->candidates);
        $this->assertNotContains([40, $full], $q->fields[0]->candidates);
        $this->assertNotContains([40, $prince], $q->fields[0]->candidates);
        foreach ([$full, $prince] as $bad) {
            try {
                $this->use($engine, 'twin_deploy_technician', ['pairs' => [[40, $bad]]]);
                $this->fail('配備できない衛星');
            } catch (EffectViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testP011UsesAnActivatedCostModifierAndCanBePaidBySelling(): void
    {
        // 起動の修正 P020（DEVICE の配備コスト -2）を同梱できる。使用済みはコミットで付く
        $f = new E5Fixture(6);
        $a = $f->active(26, Direction::NE, ['P011', 'P020', 'P034'], 4);
        $b = $f->satellite(self::P1, [], false);
        $f->game->hand[self::P1] = [40, 10, 13];
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($a, 'P034')]);
        $q = $this->ask($engine->questionsFor(self::P1), 'twin_deploy_technician');
        $this->assertSame('USE_MODS', $q->fields[1]->type->value);
        $this->assertSame(['P:20'], $q->fields[1]->candidates);

        $id = (int) $q->entryId;
        try {
            $engine->useEffect(self::P1, $id, new Answer(['pairs' => [[40, $b]]]));
            $this->fail('6 マヤでは 9 マヤを払えない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        // 9 - 2 = 7 マヤ。手札 1 枚（P010）を売って 6 + 3 = 9 から払う
        $engine->useEffect(self::P1, $id, new Answer(['pairs' => [[40, $b]], 'useMods' => ['P:20']], [10]));

        $this->assertSame(2, $f->game->maya[self::P1]);
        $this->assertSame([13], $f->game->hand[self::P1]);
        $this->assertSame([40], $f->game->deployedCards($b));
        $this->assertSame(['P:20' => true, 'P:11' => true], $f->game->used);
    }

    // ---- P045 軌道整備ドッグ ----

    public function testP045DeploysToThisSatelliteWithTheSurchargeAndChainsToOtherDeployReactions(): void
    {
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P045', 'P013'], 4);
        $f->active(14, Direction::NE, [], 4);
        $f->game->hand[self::P1] = [10, 40];
        $engine = $f->engine();
        $engine->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $a)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'orbital_repair_dock');

        $this->assertSame([[10, $a], [40, $a]], $q->fields[0]->candidates, 'この衛星だけ');
        $this->assertSame([10 => 9, 40 => 14], array_column($q->preview['costs'], 'cost', 'card'), '4 + 5、9 + 5');

        $this->use($engine, 'orbital_repair_dock', ['pairs' => [[10, $a]]]);

        $this->assertSame(21, $f->game->maya[self::P1]);
        $this->assertSame(1, $f->game->budget[self::P1], 'CREW の配備に P013 が反応');
        $this->assertSame([10], array_slice($f->game->deployedCards($a), -1));
    }

    public function testP045IsReachedFromP044ThroughTheTurnedChain(): void
    {
        // E-4 との連鎖: 別の衛星の転回 → P044 がこの衛星を転回 → TURNED → P045 が配備
        $f = new E5Fixture(30);
        $other = $f->active(14, Direction::NE, [], 4);
        $a = $f->active(26, Direction::NE, ['P044', 'P045'], 4);
        $f->game->hand[self::P1] = [10];
        $engine = $f->engine();
        $engine->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $other)]);
        $this->use($engine, 'formation_sync_unit', ['direction' => 'SW']);
        $this->use($engine, 'orbital_repair_dock', ['pairs' => [[10, $a]]]);

        $this->assertSame(Direction::SW, $f->game->satelliteState($a)?->direction);
        $this->assertContains(10, $f->game->deployedCards($a));
        $this->assertSame(21, $f->game->maya[self::P1]);
    }

    // ---- P074 ゼロデイデプロイ ----

    public function testP074DiscardsACardThenDeploysAnotherPayingTheSurchargeOnAnActiveSatellite(): void
    {
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P074'], 4);
        $b = $f->satellite(self::P1, [], false);
        $f->game->hand[self::P1] = [10, 13];
        $engine = $f->engine();
        $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'zero_day_deploy');
        $this->assertSame('DISCARD_COST', $q->cost['kind']);
        $this->assertSame(['pairs', 'discard'], array_map(static fn($fl): string => $fl->key, $q->fields));
        $this->assertSame([10 => 9, 13 => 12], array_column(array_filter($q->preview['costs'], static fn(array $c): bool => $c['sat'] === $a), 'cost', 'card'));

        // 同じカードを捨てて配備はできない
        $id = (int) $q->entryId;
        try {
            $engine->useEffect(self::P1, $id, new Answer(['pairs' => [[13, $a]], 'discard' => [13]]));
            $this->fail('配備するカードは捨てられない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $engine->useEffect(self::P1, $id, new Answer(['pairs' => [[13, $a]], 'discard' => [10]]));

        $this->assertSame(18, $f->game->maya[self::P1], '捨てても売却額は得ず、7 + 5 = 12 を払う');
        $this->assertSame([], $f->game->hand[self::P1]);
        $this->assertSame([10], $f->game->discard);
        $this->assertSame([13], array_slice($f->game->deployedCards($a), -1));
        $this->assertSame([], $f->game->deployedCards($b));
    }

    public function testP074NeedsASecondCardToDiscardAndSomethingToAfford(): void
    {
        $f = new E5Fixture(30);
        $f->active(26, Direction::NE, ['P074'], 4);
        $f->game->hand[self::P1] = [13];
        $engine = $f->engine();
        $report = $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)]);
        $this->assertSame([], $report->playersNeedingInput, '捨てるカードが別に要る');

        $g = new E5Fixture(0);
        $g->active(26, Direction::NE, ['P074'], 4);
        $g->game->hand[self::P1] = [40, 13];
        $engine = $g->engine();
        $report = $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)]);
        $this->assertSame([], $report->playersNeedingInput, '売却に使えるのは 0 枚で、コストを払えない');
        $this->assertSame([], $g->game->used);
    }

    // ---- R005 STEALTH（MOON） ----

    public function testR005DeploysUpToTwoCardsWithoutTheSurchargeAndTheirEffectsFire(): void
    {
        // T28 / FAQ 行 69: 最大 2 枚、追加配備コスト不要、配備時効果（P009）が発動し、他の配備反応（P013）も働く
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P013'], 4, 0, self::P1);
        $f->game->setSatelliteKey($a, 'stealth_moon');
        $f->game->hand[self::P1] = [9, 10, 40];
        $f->game->deck = [61, 62];
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($a)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'stealth_moon');
        $this->assertSame(1, $q->fields[0]->min);
        $this->assertSame(2, $q->fields[0]->max);
        $this->assertSame([[9, $a], [10, $a], [40, $a]], $q->fields[0]->candidates);

        $id = (int) $q->entryId;
        try {
            $engine->useEffect(self::P1, $id, new Answer(['pairs' => [[9, $a], [10, $a], [40, $a]]]));
            $this->fail('3 枚は配備できない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $engine->useEffect(self::P1, $id, new Answer(['pairs' => [[9, $a], [10, $a]]]));

        $this->assertSame(23, $f->game->maya[self::P1], '3 + 4。稼働中でも +5 はない');
        $this->assertSame([40, 61], $f->game->hand[self::P1], 'P009 の配備時効果で 1 枚引いた');
        $this->assertSame(1, $f->game->budget[self::P1], 'P013 は CREW 2 枚でも 1 回');
        $this->assertSame([13, 9, 10], $f->game->deployedCards($a));
    }

    public function testR005FiresTheDeployedCardsLaunchEffectButNotTheOthersAgain(): void
    {
        // 配備した P039（打ち上げ時: DEVICE の枚数だけ引く）の ON_LAUNCH が発動する。すでにある P042 は打ち上げの 1 回だけ
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P042'], 4);
        $f->game->setSatelliteKey($a, 'stealth_moon');
        $f->game->hand[self::P1] = [39];
        $f->game->deck = [61, 62, 63];
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($a)]);

        // R005 と P042（予算・構成を読む）は衝突するので ORDER。R005 を先にする
        $questions = $engine->questionsFor(self::P1);
        $this->assertSame(QuestionType::Order, $questions[0]->type);
        $byKey = array_column($questions[0]->entries, 'entryId', 'cardKey');
        $engine->orderEffects(self::P1, [$byKey['stealth_moon'], $byKey['dancing_dog_robot']]);
        $this->use($engine, 'stealth_moon', ['pairs' => [[39, $a]]]);

        // DEVICE は P042 と配備した P039 の 2 枚。P039 は配備されたときに 1 回だけ打ち上げ時効果が発動し 2 枚引く
        $this->assertSame([61, 62], $f->game->hand[self::P1]);
        $this->assertSame(2, $f->game->budget[self::P1], 'P042 は R005 の後なので 2 枚ぶん、元の打ち上げの 1 回だけ');
    }

    // ---- P035 ギャンブルシステム ----

    public function testP035DiscardsItselfAndDeploysTheTopCardForFreeTriggeringItsEffects(): void
    {
        // FAQ 行 16、T05: 山札の P027（人事プランナー）を配備。他のカード（P030）は反応するが、配備された P027 自身は反応しない
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P035', 'P030'], 4);
        $f->game->deck = [27, 61];
        $engine = $f->engine();
        $report = $engine->openWindow('LE', [$this->launched($a)]);

        $this->assertSame([], $report->playersNeedingInput, '強制で選択がないので自動で解決する');
        $this->assertSame([30, 27], $f->game->deployedCards($a));
        $this->assertSame([35], $f->game->discard);
        $this->assertSame(30, $f->game->maya[self::P1], '配備コストは払わない');
        $this->assertSame(1, $f->game->budget[self::P1], 'P030 が CREW の配備に反応');
        $this->assertSame([], $f->game->hand[self::P1], 'P027 自身は反応しない');
    }

    public function testP035DeployedCardsOnDeployAndOnLaunchEffectsTrigger(): void
    {
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P035'], 4);
        $f->game->deck = [9, 39, 61, 62, 63];                      // P009: 配備時に 1 枚引く
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($a)]);
        $this->assertSame([9], $f->game->deployedCards($a));
        $this->assertSame([39], $f->game->hand[self::P1], 'P009 の ON_DEPLOY で引いた');

        $g = new E5Fixture(30);
        $b = $g->active(26, Direction::NE, ['P035'], 4);
        $g->game->deck = [39, 61, 62];                             // P039: 打ち上げ時に DEVICE の枚数だけ引く
        $engine = $g->engine();
        $engine->openWindow('LE', [$this->launched($b)]);
        $this->assertSame([39], $g->game->deployedCards($b));
        $this->assertSame([61], $g->game->hand[self::P1], 'P039 の ON_LAUNCH が発動');
    }

    public function testP035KeepsAnOutOfFuelSatelliteOutOfFuelWhenTheTankGrows(): void
    {
        // N-1: 燃料切れ（c = C + 1）の衛星の燃料庫が増えても、燃料切れのまま
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P035'], 2, 5);        // C = 2 + 2 = 4、c = 5（燃料切れ）
        $f->game->deck = [40];                                     // P040: 燃料庫 3 → C = 2 + 3 = 5
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($a)]);

        $s = $f->game->satelliteState($a);
        $this->assertSame(5, $s?->capacity);
        $this->assertSame(6, $s?->fuelUsed);
        $this->assertTrue($s?->isOutOfFuel());
    }

    public function testP035DoesNothingOnASatelliteThatCannotReceiveCards(): void
    {
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P035', 'P033'], 4);
        $f->game->deck = [27];
        $engine = $f->engine();
        $engine->openWindow('LE', [$this->launched($a)]);
        $this->assertSame([35, 33], $f->game->deployedCards($a));
        $this->assertSame([27], $f->game->deck);
    }

    // ---- 燃料切れの衛星への配備（N-1） ----

    public function testDeployingToAnOutOfFuelSatelliteKeepsItOutOfFuel(): void
    {
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P011', 'P034'], 2, 7);   // C = 2 + 2 + 2 = 6、c = 7: 燃料切れ
        $f->game->hand[self::P1] = [40];                              // 燃料庫 3
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($a, 'P034')]);
        $this->assertSame(7, $f->game->satelliteState($a)?->fuelUsed);
        $this->use($engine, 'twin_deploy_technician', ['pairs' => [[40, $a]]]);

        $s = $f->game->satelliteState($a);
        $this->assertSame(9, $s?->capacity);
        $this->assertSame(10, $s?->fuelUsed, 'C が増えても燃料切れ（c = C + 1）のまま');
        $this->assertTrue($s?->isOutOfFuel());
    }

    // ---- P022 ジャグラー ----

    public function testP022RearrangesCardsOnStandbySatellitesWithoutRaisingDeployEvents(): void
    {
        // T04 / FAQ 行 9・10: 付け替えは配備ではない。配備時の効果も配備への反応も発動しない
        $f = new E5Fixture(30);
        $active = $f->active(26, Direction::NE, ['P013'], 4);        // 稼働中: 対象外
        $b = $f->satellite(self::P1, ['P009', 'P022'], false);         // P009 の ON_DEPLOY は発動しない
        $c = $f->satellite(self::P1, ['P010'], false);
        $f->game->deck = [61, 62];
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($b, 'P022')]);
        $q = $this->ask($engine->questionsFor(self::P1), 'juggler');

        $this->assertTrue($q->mandatory);
        $this->assertSame(FieldType::Assignment, $q->fields[0]->type);
        $this->assertSame([9 => $b, 10 => $c, 22 => $b], $q->fields[0]->default);
        $this->assertSame([$b, $c], $q->preview['satellites']);

        $this->use($engine, 'juggler', ['assignment' => [9 => $c, 10 => $c, 22 => $c]]);

        $this->assertSame([], $f->game->deployedCards($b));
        $this->assertSame([10, 9, 22], $f->game->deployedCards($c), '残るカードの下に、動いたカードを番号順に積む');
        $this->assertSame([13], $f->game->deployedCards($active));
        $this->assertSame([], $f->game->hand[self::P1], 'P009 は引かない');
        $this->assertSame(0, $f->game->budget[self::P1]);
        $this->assertSame([61, 62], $f->game->deck);
        $this->assertSame([], $engine->playersNeedingInput());
    }

    public function testP022ValidatesTheAssignment(): void
    {
        $f = new E5Fixture(30);
        $active = $f->active(26, Direction::NE, ['P013'], 4);
        $b = $f->satellite(self::P1, ['P009', 'P022', 'P010', 'P023'], false);
        $c = $f->satellite(self::P1, ['P011'], false);
        $prince = $f->satellite(self::P1, ['P033'], false);
        $engine = $f->engine();
        $engine->openWindow('DE', [$this->deployed($b, 'P022')]);
        $id = $this->entry($engine, 'juggler');
        $keep = [9 => $b, 10 => $b, 11 => $c, 22 => $b, 23 => $b, 33 => $prince];

        $bad = [
            '稼働中の衛星へは置けない' => [9 => $active] + $keep,
            '1 機 4 枚まで' => [11 => $b] + $keep,
            '配備できない衛星へは動かせない' => [9 => $prince] + $keep,
            '全てのカードを割り当てる' => array_diff_key($keep, [9 => 0]),
            '稼働中の衛星のカードは動かせない' => [13 => $b] + $keep,
        ];
        foreach ($bad as $label => $assignment) {
            try {
                $engine->useEffect(self::P1, $id, new Answer(['assignment' => $assignment]));
                $this->fail($label);
            } catch (EffectViolation) {
                $this->addToAssertionCount(1);
            }
        }
        // もともと P033 の衛星にあるカード（P033 自身）は動かさなくてよい。P022 自身を別の衛星へ動かせる
        $this->use($engine, 'juggler', ['assignment' => [22 => $c] + $keep]);
        $this->assertSame([9, 10, 23], $f->game->deployedCards($b));
        $this->assertSame([11, 22], $f->game->deployedCards($c));
    }

    public function testP022IsNotAvailableWithoutCardsOnStandbySatellitesAndZombieKeepsTheLayout(): void
    {
        $f = new E5Fixture(30);
        $a = $f->active(26, Direction::NE, ['P022'], 4);
        $f->satellite(self::P1, [], false);
        $engine = $f->engine();
        $report = $engine->openWindow('DE', [$this->deployed($a, 'P022')]);
        $this->assertSame([], $report->playersNeedingInput);

        $g = new E5Fixture(30);
        $a = $g->active(26, Direction::NE, ['P022'], 4);
        $b = $g->satellite(self::P1, ['P009'], false);
        $engine = $g->engine();
        $engine->openWindow('DE', [$this->deployed($a, 'P022')]);
        $engine->zombie(self::P1);
        $this->assertSame([9], $g->game->deployedCards($b), '既定は現在の配置のまま');
        $this->assertSame([], $g->game->hand[self::P1]);
    }
}
