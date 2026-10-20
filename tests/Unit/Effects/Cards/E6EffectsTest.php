<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Cards;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\Context\StagedLinkRequest;
use Bga\Games\projectedgeworth\Effects\EffectEngine;
use Bga\Games\projectedgeworth\Effects\EffectViolation;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\FieldType;
use Bga\Games\projectedgeworth\Effects\Question\Question;
use Bga\Games\projectedgeworth\Effects\Question\QuestionType;
use Bga\Games\projectedgeworth\Flow\Action\LinkCost;
use Bga\Games\projectedgeworth\Material\Hook;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E6Fixture;

/**
 * 効果 E-6（通信確立）14 種の結線テスト: 本物の `CardEffects`・部品・条件・盤面（Earth の標準面）で、通信確立のステージ（R11）を確かめる
 * （011 §11-3 の T11〜T13・T18・T21・T26、FAQ 行 21〜27・29・30・42・56・63、007 §7-3 の世代とバリア、未確定 8 の再発動）。
 *
 * 盤面（Earth の標準面）: 区域 ID = 行 × 12 + 列。地域 A は 0〜2・12〜14・24〜26、B は 3〜5・15〜17・27〜29（行 0〜2）。陸 / 海は
 * 1 = 海、2 = 陸、13・14・24〜28 = 陸、12 = 海、36・37・39・40 = 陸、38・41 = 海 など。座標 (x, y) は偶数行が x = 2 × 列、奇数行が x = 2 × 列 + 1。
 * 区域コストは陸 5・海 2、特殊コストは +5。
 */
final class E6EffectsTest extends TestCase
{
    private const P1 = 1;
    private const P2 = 2;

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
        // 通信確立は互いに衝突する（`M−` `L`）ので、同じ窓に複数あれば先に順序を決める（既定の順）
        $first = $engine->questionsFor($player)[0] ?? null;
        if ($first !== null && $first->type === QuestionType::Order) {
            $engine->orderEffects($player, $first->defaultOrder);
        }
        return (int) $this->ask($engine->questionsFor($player), $cardKey)->entryId;
    }

    /** @param array<string, mixed> $fields @param list<int> $sell */
    private function use(EffectEngine $engine, string $cardKey, array $fields = [], array $sell = [], int $player = self::P1): void
    {
        $engine->useEffect($player, $this->entry($engine, $cardKey, $player), new Answer($fields, $sell));
    }

    private function noQuestion(EffectEngine $engine, string $cardKey, int $player = self::P1): void
    {
        foreach ($engine->questionsFor($player) as $q) {
            $this->assertNotSame($cardKey, $q->cardKey, "$cardKey should not be asked");
        }
        $this->addToAssertionCount(1);
    }

    private function linked(int $player, int $sat, int $cell, int $generation = 0): GameEvent
    {
        return new GameEvent(Hook::Linked, $player, $sat, cell: $cell, generation: $generation);
    }

    private function entered(int $player, int $sat, int $cell, string $from, string $to): GameEvent
    {
        return new GameEvent(Hook::OrbitEntered, $player, $sat, cell: $cell, data: ['fromRegion' => $from, 'toRegion' => $to]);
    }

    /** @param list<StagedLinkRequest> $staged @return array<string, mixed> */
    private function costOf(array $staged, int $index = 0): array
    {
        return $staged[$index]->cost;
    }

    // ---- P047 トルネードビット ----

    public function testP047StagesASpecialLinkOfTheCellBelowForTheSpecialCost(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P047']);
        $engine = $f->engine();
        $engine->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $sat)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'tornado_bit');

        $this->assertSame([], $q->fields, '区域が 1 つに決まるので [Use] / [Skip] だけ');
        $this->assertSame(10, $q->preview['links'][0]['cost'], '陸の区域コスト 5 + 特殊コスト 5');

        $this->use($engine, 'tornado_bit');
        $report = $engine->barrier();

        $this->assertCount(1, $report->stagedLinks);
        $link = $report->stagedLinks[0];
        $this->assertSame([self::P1, $sat, 26, 'P:47'], [$link->playerId, $link->satelliteId, $link->cell, $link->sourceKey]);
        $cost = LinkCost::fromArray($link->cost);
        $this->assertSame([5, 5, 10], [$cost->areaCost, $cost->specialCost, $cost->total()]);
        $this->assertFalse($report->canClose(), 'コミットが済むまで窓は閉じない');
        $this->assertSame(60, $f->game->maya[self::P1], '支払いはコミットで行う（ステージでは予約だけ）');
        $this->assertArrayHasKey('P:47', $f->game->used);
    }

    public function testP047ExtendedTargetsApplyToTheEffectLinkToo(): void
    {
        // FAQ 行 23: 対象区域を広げる効果（P052 大陸アンテナ）は効果による通信確立にも効く
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P047', 'P052']);
        $engine = $f->engine();
        $engine->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $sat)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'tornado_bit');

        $this->assertSame(FieldType::Area, $q->fields[0]->type);
        $this->assertSame([13, 14, 25, 26, 27, 37], $q->fields[0]->candidates, '直下と、隣接する陸区域');
        $this->use($engine, 'tornado_bit', ['area' => 27]);
        $this->assertSame(27, $engine->barrier()->stagedLinks[0]->cell);
    }

    public function testP047NeedsAnAreaWithoutOwnCubesAndRejectsAnAreaOutsideTheCandidates(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P047', 'P052']);
        $f->game->linkedCells[self::P1] = [26];                     // (ii) 直下にすでに自分のキューブ
        $engine = $f->engine();
        $engine->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $sat)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'tornado_bit');
        $this->assertSame([13, 14, 25, 27, 37], $q->fields[0]->candidates, '自分のキューブがある区域は選べない');

        $this->expectException(EffectViolation::class);
        $this->use($engine, 'tornado_bit', ['area' => 26]);
    }

    public function testP047IsNotAvailableWhenTheOnlyAreaHasOwnCubeOrCannotBePaid(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P047']);
        $f->game->linkedCells[self::P1] = [26];
        $report = $f->engine()->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $sat)]);
        $this->assertSame([], $report->playersNeedingInput);

        $poor = new E6Fixture(9);                                     // 特殊通信確立は 10 マヤ要る
        $sat = $poor->active(26, Direction::NE, ['P047']);
        $this->assertSame([], $poor->engine()->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $sat)])->playersNeedingInput);
    }

    public function testSellingCardsCoversTheSpecialCost(): void
    {
        $f = new E6Fixture(8);
        $sat = $f->active(26, Direction::NE, ['P047']);
        $f->game->hand[self::P1] = [10, 13];
        $engine = $f->engine();
        $engine->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $sat)]);

        $this->use($engine, 'tornado_bit', [], [10]);               // 8 + 3 = 11 >= 10
        $this->assertSame(11, $f->game->maya[self::P1]);
        $this->assertSame([13], $f->game->hand[self::P1]);
        $this->assertCount(1, $engine->barrier()->stagedLinks);
    }

    // ---- P050・P051 中継ネットワーク ----

    public function testP050LinksAnotherSatelliteAnywhereWhenTheLinkedAreaIsInItsRegions(): void
    {
        // FAQ 行 21・22: 通信確立した区域の地域で判定し、他の衛星の居場所は問わない。他の衛星は直下の区域のみ
        $f = new E6Fixture(60);
        $host = $f->active(27, Direction::NE, ['P050']);            // 衛星は地域 B にいる
        $other = $f->active(60, Direction::NE);                     // 別の衛星は地域 E の海
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $host, 26)]);   // 通信確立した区域 26 は地域 A
        $q = $this->ask($engine->questionsFor(self::P1), 'euro_africa_relay_network');

        $this->assertSame(FieldType::Pairs, $q->fields[0]->type);
        $this->assertSame([[$other, 60]], $q->fields[0]->candidates, 'このカードの衛星ではなく他の衛星の直下だけ');
        $this->use($engine, 'euro_africa_relay_network', ['pairs' => [[$other, 60]]]);
        $link = $engine->barrier()->stagedLinks[0];
        $this->assertSame([$other, 60], [$link->satelliteId, $link->cell]);
        $this->assertSame(7, LinkCost::fromArray($link->cost)->total(), '海 2 + 特殊 5');
    }

    public function testP050DoesNotReactWhenTheLinkedAreaIsOutsideItsRegions(): void
    {
        // FAQ 行 22: 衛星が地域 A にいても、通信確立した区域が地域 B なら発動しない
        $f = new E6Fixture(60);
        $host = $f->active(26, Direction::NE, ['P050']);
        $f->active(60, Direction::NE);
        $engine = $f->engine();
        $report = $engine->openWindow('KE', [$this->linked(self::P1, $host, 27)]);
        $this->assertSame([], $report->playersNeedingInput);
    }

    public function testP051UsesRegionsBDAndF(): void
    {
        $f = new E6Fixture(60);
        $host = $f->active(26, Direction::NE, ['P051']);
        $f->active(60, Direction::NE);
        $engine = $f->engine();
        $this->assertSame([], $engine->openWindow('KE', [$this->linked(self::P1, $host, 26)])->playersNeedingInput, '地域 A は対象外');

        $g = new E6Fixture(60);
        $host = $g->active(26, Direction::NE, ['P051']);
        $other = $g->active(60, Direction::NE);
        $engine = $g->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $host, 27)]);
        $this->use($engine, 'americas_asia_relay_network', ['pairs' => [[$other, 60]]]);
        $this->assertSame($other, $engine->barrier()->stagedLinks[0]->satelliteId);
    }

    public function testP050CannotChooseASatelliteThatCannotLink(): void
    {
        // FAQ 行 56 / T21: P066（電波遮断装置）の衛星は、効果による通信確立の対象にも選べない
        $f = new E6Fixture(60);
        $host = $f->active(26, Direction::NE, ['P050']);
        $f->active(60, Direction::NE, ['P066']);
        $report = $f->engine()->openWindow('KE', [$this->linked(self::P1, $host, 25)]);
        $this->assertSame([], $report->playersNeedingInput, '選べる衛星がなければ使えない');
    }

    public function testP050TargetsTheOtherSatellitesExtendedAreasToo(): void
    {
        // FAQ 行 21: 通信確立対象を広げる効果を持つ衛星は、直下以外の区域も通信確立できる
        $f = new E6Fixture(60);
        $host = $f->active(26, Direction::NE, ['P050']);
        $other = $f->active(60, Direction::NE, ['P053']);           // 面舵アンテナ: 進行方向の右の 2 区域
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $host, 25)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'euro_africa_relay_network');
        $cells = array_values(array_unique(array_column($q->fields[0]->candidates, 1)));
        $this->assertContains(60, $cells);
        $this->assertGreaterThan(1, count($cells));
        foreach ($q->fields[0]->candidates as [$sat]) {
            $this->assertSame($other, $sat);
        }
    }

    // ---- P056・P057 広域送信機 ----

    public function testP056LinksTheAreaAheadOfTheSatellite(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P056']);              // 前方 = 北東 (5,1) = 14
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'forward_wide_area_transmitter');
        $this->assertSame([], $q->fields, '区域が 1 つに決まるので [Use] / [Skip] だけ');

        $this->use($engine, 'forward_wide_area_transmitter');
        $link = $engine->barrier()->stagedLinks[0];
        $this->assertSame(14, $link->cell);
        $this->assertSame(10, LinkCost::fromArray($link->cost)->total());
    }

    public function testP056DoesNotCountTheReflectedAreaAsAhead(): void
    {
        // FAQ 行 26・27 / T12: ボード最上部の段で上向きの衛星の前方は反射後の区域ではない
        $f = new E6Fixture(60);
        $sat = $f->active(2, Direction::NE, ['P056']);               // 行 0 で右上向き: 前方は盤面の外
        $this->assertSame([], $f->engine()->openWindow('KE', [$this->linked(self::P1, $sat, 2)])->playersNeedingInput);
    }

    public function testP056WrapsAroundTheLeftAndRightEdges(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(23, Direction::NE, ['P056']);              // (23,1) の前方は (24,0) = 左端 (0,0) の区域 0
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 23)]);
        $this->use($engine, 'forward_wide_area_transmitter');
        $this->assertSame(0, $engine->barrier()->stagedLinks[0]->cell);
    }

    public function testP057LinksTheAreaBehindAndAddsTheExtendedTargets(): void
    {
        // FAQ 行 25: 後方の区域に加え、対象を広げる効果（P052）の区域も選べる
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P057']);              // 後方 = 南西 (3,3) = 37
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $this->assertSame([], $this->ask($engine->questionsFor(self::P1), 'rear_wide_area_transmitter')->fields);
        $this->use($engine, 'rear_wide_area_transmitter');
        $this->assertSame(37, $engine->barrier()->stagedLinks[0]->cell);

        $g = new E6Fixture(60);
        $sat = $g->active(26, Direction::NE, ['P057', 'P052']);
        $engine = $g->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'rear_wide_area_transmitter');
        $this->assertSame([13, 14, 25, 27, 37], $q->fields[0]->candidates, '後方 37 と、隣接する陸区域（直下 26 は含まない）');
    }

    // ---- P058・P059 遠隔通信装置 ----

    public function testP058ReactsToAnotherSatelliteLinkingInRegionsCAndE(): void
    {
        $f = new E6Fixture(60);
        $host = $f->active(26, Direction::NE, ['P058']);
        $other = $f->active(60, Direction::NE);
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $other, 36)]);      // 他の衛星が地域 E の区域を通信確立
        $q = $this->ask($engine->questionsFor(self::P1), 'crimson_remote_comm_unit');
        $this->assertSame([], $q->fields);

        $this->use($engine, 'crimson_remote_comm_unit');
        $link = $engine->barrier()->stagedLinks[0];
        $this->assertSame([$host, 26], [$link->satelliteId, $link->cell], 'この衛星の直下の区域');
    }

    public function testP058IgnoresItsOwnLinkAndAreasOutsideItsRegions(): void
    {
        $f = new E6Fixture(60);
        $host = $f->active(26, Direction::NE, ['P058']);
        $other = $f->active(60, Direction::NE);
        $this->assertSame([], $f->engine()->openWindow('KE', [$this->linked(self::P1, $host, 36)])->playersNeedingInput, 'この衛星自身の通信確立には反応しない');
        $this->assertSame([], $f->engine()->openWindow('KE', [$this->linked(self::P1, $other, 25)])->playersNeedingInput, '地域 A は対象外');
    }

    public function testP059UsesRegionsDAndF(): void
    {
        $f = new E6Fixture(60);
        $f->active(26, Direction::NE, ['P059']);
        $other = $f->active(60, Direction::NE);
        $engine = $f->engine();
        $this->assertSame([], $engine->openWindow('KE', [$this->linked(self::P1, $other, 36)])->playersNeedingInput, '地域 E は対象外');
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $other, 39)]);        // 地域 F
        $this->use($engine, 'lapis_remote_comm_unit');
        $this->assertSame(26, $engine->barrier()->stagedLinks[0]->cell);
    }

    // ---- P060・P061 波形転送装置 ----

    public function testP060LinksALandAreaAdjacentToTheAreaBelowTheSatellite(): void
    {
        // 011 未確定 1: 「隣接する」は衛星の直下の区域に隣接（通信確立した区域ではない）
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P060']);
        $f->game->linkedCells[self::P1] = [14];                      // 通信確立した区域 14（地域 A）。自分のキューブがあるので選べない
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 14)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'north_sea_waveform_relay');

        $this->assertSame(FieldType::Area, $q->fields[0]->type);
        $this->assertSame([13, 25, 27, 37], $q->fields[0]->candidates, '26 に隣接する陸区域から 14 を除く（海の 38 は含まない）');
        $this->use($engine, 'north_sea_waveform_relay', ['area' => 27]);
        $link = $engine->barrier()->stagedLinks[0];
        $this->assertSame([$sat, 27], [$link->satelliteId, $link->cell]);
        $this->assertSame(10, LinkCost::fromArray($link->cost)->total());
    }

    public function testP060OnlyReactsToItsOwnLinkInRegionsAAndG(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P060']);
        $engine = $f->engine();
        $this->assertSame([], $engine->openWindow('KE', [$this->linked(self::P1, $sat, 27)])->playersNeedingInput, '地域 B は対象外');
    }

    public function testP061UsesRegionsBAndH(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P061']);
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 27)]);   // 地域 B
        $q = $this->ask($engine->questionsFor(self::P1), 'south_russia_waveform_relay');
        $this->assertSame([13, 14, 25, 27, 37], $q->fields[0]->candidates);
        $this->use($engine, 'south_russia_waveform_relay', ['area' => 13]);
        $this->assertSame(13, $engine->barrier()->stagedLinks[0]->cell);
    }

    // ---- P076 グラウンドリンク ----

    public function testP076DiscardsTwoCardsAndLinksAnAdjacentLandAtTheNormalCost(): void
    {
        // FAQ 行 30: 区域コストと先駆コストは払うが、特殊コスト（5 マヤ）は不要
        $f = new E6Fixture(60);
        $a = $f->active(26, Direction::NE, ['P076']);
        $b = $f->active(60, Direction::NE);
        $f->game->hand[self::P1] = [10, 13, 15];
        $engine = $f->engine();
        $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'ground_link');

        $this->assertSame(['pairs', 'discard'], array_map(static fn($fl): string => $fl->key, $q->fields));
        $this->assertContains([$a, 14], $q->fields[0]->candidates);
        $this->assertContains([$b, 49], $q->fields[0]->candidates, '自分の好きな衛星');
        $this->assertSame('DISCARD_COST', $q->cost['kind']);

        $this->use($engine, 'ground_link', ['pairs' => [[$a, 14]], 'discard' => [10, 13]]);
        $link = $engine->barrier()->stagedLinks[0];
        $cost = LinkCost::fromArray($link->cost);
        $this->assertSame([14, 5, 0, 5], [$link->cell, $cost->areaCost, $cost->specialCost, $cost->total()]);
        $this->assertSame([15], $f->game->hand[self::P1]);
        $this->assertSame([10, 13], $f->game->discard);
        $this->assertSame(60, $f->game->maya[self::P1]);
    }

    public function testP076NeedsTwoCardsToDiscardAndKeepsThemOutOfTheSale(): void
    {
        $f = new E6Fixture(3);
        $a = $f->active(26, Direction::NE, ['P076']);
        $f->game->hand[self::P1] = [10, 13, 15];                     // マヤ 3 + 売却 1 枚（3）= 6 >= 5
        $engine = $f->engine();
        $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)]);
        $id = $this->entry($engine, 'ground_link');
        try {
            $engine->useEffect(self::P1, $id, new Answer(['pairs' => [[$a, 14]], 'discard' => [10, 13]], [13]));
            $this->fail('捨てるカードは売却できない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $engine->useEffect(self::P1, $id, new Answer(['pairs' => [[$a, 14]], 'discard' => [10, 13]], [15]));
        $this->assertSame(6, $f->game->maya[self::P1]);
        $this->assertCount(1, $engine->barrier()->stagedLinks);

        $g = new E6Fixture(60);
        $g->active(26, Direction::NE, ['P076']);
        $g->game->hand[self::P1] = [10];
        $this->assertSame([], $g->engine()->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)])->playersNeedingInput, '手札が 1 枚では捨てられない');
    }

    public function testP076PaysThePioneerCostToThePlayerWhoLinkedTheAreaBefore(): void
    {
        $f = new E6Fixture(60);
        $a = $f->active(26, Direction::NE, ['P076']);
        $f->game->hand[self::P1] = [10, 13];
        $f->game->linkedCells[self::P2] = [14];
        $engine = $f->engine();
        $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)]);
        $this->use($engine, 'ground_link', ['pairs' => [[$a, 14]], 'discard' => [10, 13]]);
        $cost = LinkCost::fromArray($engine->barrier()->stagedLinks[0]->cost);
        $this->assertSame([5, [['to' => self::P2, 'amount' => 3]], 8], [$cost->areaCost, $cost->pioneer, $cost->total()]);
    }

    public function testChainOfFaq29P076ThenP048TurnsThenP037Draws(): void
    {
        // T13 / FAQ 行 29: P076 の通信確立 → P048 の転回 → P037 の 1 枚ドロー。このドローは企画の枚数ではないので P067 の +1 は効かない
        $f = new E6Fixture(60);
        $a = $f->active(26, Direction::NE, ['P076', 'P048', 'P037', 'P067']);
        $f->game->hand[self::P1] = [10, 13];
        $f->game->deck = [101, 102, 103];
        $engine = $f->engine();
        $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)]);
        $this->use($engine, 'ground_link', ['pairs' => [[$a, 14]], 'discard' => [10, 13]]);
        $report = $engine->barrier();
        $f->commit($engine, $report->stagedLinks, 1);

        $this->assertSame(Direction::NE, $f->game->satelliteState($a)?->direction);
        $this->use($engine, 'variable_orbit_unit', ['direction' => 'SE']);
        $this->assertSame(Direction::SE, $f->game->satelliteState($a)?->direction);
        $this->assertSame([101], $f->game->hand[self::P1], 'P037 が 1 枚だけ引く');
        $this->assertTrue($engine->barrier()->canClose());
        $this->assertSame(55, $f->game->maya[self::P1], '区域コスト 5 はコミットで払う');
    }

    // ---- P096・P097・P098・P099 リンク ----

    public function testP096LinksTheAreaBelowAfterEnteringRegionsAcFOrFromAnotherRegion(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(14, Direction::NE, ['P096']);
        $engine = $f->engine();
        $engine->openWindow('O1', [$this->entered(self::P1, $sat, 14, 'B', 'A')]);
        $q = $this->ask($engine->questionsFor(self::P1), 'alpha_link');
        $this->assertSame([], $q->fields);
        $this->use($engine, 'alpha_link');
        $this->assertSame(14, $engine->barrier()->stagedLinks[0]->cell);
    }

    public function testP096And097OnlyReactToTheirOwnRegionsAndToChangingRegion(): void
    {
        $f = new E6Fixture(60);
        $a = $f->active(14, Direction::NE, ['P096']);
        $b = $f->active(27, Direction::NE, ['P097']);
        $engine = $f->engine();
        // 同じ地域内の移動、対象外の地域への突入には反応しない
        $this->assertSame([], $engine->openWindow('O1', [$this->entered(self::P1, $a, 14, 'A', 'A')])->playersNeedingInput);
        $this->assertSame([], $f->engine()->openWindow('O1', [$this->entered(self::P1, $a, 27, 'A', 'B')])->playersNeedingInput, 'P096 は B に反応しない');
        $engine = $f->engine();
        $engine->openWindow('O1', [$this->entered(self::P1, $b, 27, 'A', 'B')]);
        $this->use($engine, 'beta_link');
        $this->assertSame([$b, 27], [$engine->barrier()->stagedLinks[0]->satelliteId, 27]);
    }

    public function testP099ReactsToAnyChangeOfRegion(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(40, Direction::NE, ['P099']);
        $engine = $f->engine();
        $this->assertSame([], $engine->openWindow('O1', [$this->entered(self::P1, $sat, 40, 'F', 'F')])->playersNeedingInput);
        $engine = $f->engine();
        $engine->openWindow('O1', [$this->entered(self::P1, $sat, 40, 'G', 'F')]);
        $this->use($engine, 'flyby_link');
        $this->assertSame(40, $engine->barrier()->stagedLinks[0]->cell);
    }

    public function testP098LinksTheAreaBehindOnlyWhenEnteringAnOwnLinkedAreaDuringTheOrbitPhase(): void
    {
        // T26 / FAQ 行 63: 循環フェイズの突入（ORBIT_ENTERED）でのみ発動する
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P098']);
        $f->game->linkedCells[self::P1] = [26];
        $engine = $f->engine();
        $this->assertSame([], $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)])->playersNeedingInput, '企画フェイズでは発動しない');
        $this->assertSame([], $f->engine()->openWindow('KE', [$this->linked(self::P1, $sat, 26)])->playersNeedingInput, '通信確立では発動しない');

        $engine = $f->engine();
        $engine->openWindow('O1', [new GameEvent(Hook::OrbitEntered, self::P1, $sat, cell: 26)]);
        $this->assertSame([], $this->ask($engine->questionsFor(self::P1), 'back_link')->fields);
        $this->use($engine, 'back_link');
        $this->assertSame(37, $engine->barrier()->stagedLinks[0]->cell, '進行方向後方');
    }

    public function testP098DoesNotReactWhenTheEnteredAreaIsNotLinked(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P098']);
        $this->assertSame([], $f->engine()->openWindow('O1', [new GameEvent(Hook::OrbitEntered, self::P1, $sat, cell: 26)])->playersNeedingInput);
    }

    // ---- コストと起動の修正 ----

    public function testActivatedLinkCostModifiersCanBeUsedWithTheEffectLink(): void
    {
        // R025（STEALTH GANYMEDE）は特殊通信確立にも -2 が効く（FAQ 行 74）。下限 0 の後に特殊コスト 5 を足す
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P047'], 4);
        $f->game->setSatelliteKey($sat, 'stealth_ganymede');
        $engine = $f->engine();
        $engine->openWindow('TE', [new GameEvent(Hook::Turned, self::P1, $sat)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'tornado_bit');
        $this->assertSame('useMods', $q->fields[0]->key);
        $this->assertSame(FieldType::UseMods, $q->fields[0]->type);

        $this->use($engine, 'tornado_bit', ['useMods' => ['S:' . $sat]]);
        $cost = LinkCost::fromArray($engine->barrier()->stagedLinks[0]->cost);
        $this->assertSame([3, 5, 8], [$cost->areaCost, $cost->specialCost, $cost->total()]);
        $this->assertArrayHasKey('S:' . $sat, $f->game->used, '使った起動の修正は使用済み');
    }

    public function testDiscountBeyondTheAreaCostComesOffThePioneerCostInSeatOrder(): void
    {
        // 作者回答 2026-10-20: 割引が区域コストを超えたら余りを先駆コストから引く。払う相手が複数いれば席順
        $f = new E6Fixture(60);
        $f->game->player(3, 60);
        $sat = $f->active(26, Direction::NE, ['P056'], 4);               // 前方 14
        $f->game->setSatelliteKey($sat, 'scallop_ganymede');             // 陸で -3
        $f->game->linkedCells[self::P2] = [14];
        $f->game->linkedCells[3] = [14];
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $this->use($engine, 'forward_wide_area_transmitter', ['useMods' => ['S:' . $sat]]);
        $cost = LinkCost::fromArray($engine->barrier()->stagedLinks[0]->cost);

        // 区域 5 + 先駆 3 + 3 = 11 から -3 を区域コストから引くと 2 + 6。特殊 +5 は下限の後
        $this->assertSame([2, 6, 5, 13], [$cost->areaCost, $cost->pioneerTotal(), $cost->specialCost, $cost->total()]);
    }

    public function testP055WaivesThePioneerCostOfTheEffectLinkToo(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P056', 'P055']);
        $f->game->linkedCells[self::P2] = [14];
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $this->use($engine, 'forward_wide_area_transmitter');
        $cost = LinkCost::fromArray($engine->barrier()->stagedLinks[0]->cost);
        $this->assertSame([], $cost->pioneer);
        $this->assertSame(10, $cost->total());
    }

    // ---- ステージの予約 ----

    public function testStagedLinksReserveMayaAndAreasWithinTheSameWindow(): void
    {
        $f = new E6Fixture(12);
        $sat = $f->active(26, Direction::NE, ['P056', 'P057'], 4);      // 前方 14、後方 37（それぞれ 10 マヤ）
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $this->use($engine, 'forward_wide_area_transmitter');
        // 10 マヤを予約したので、残り 2 マヤでは 2 つ目の特殊通信確立は払えない
        $this->assertSame([], $engine->questionsFor(self::P1));
        $this->assertCount(1, $engine->barrier()->stagedLinks);
    }

    public function testSecondStagedLinkIsAllowedWhenTheMayaCoversBothAndTheAreaDiffers(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P056', 'P057'], 4);
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $this->use($engine, 'forward_wide_area_transmitter');
        $this->use($engine, 'rear_wide_area_transmitter');
        $staged = $engine->barrier()->stagedLinks;
        $this->assertSame([14, 37], array_map(static fn(StagedLinkRequest $s): int => $s->cell, $staged));
    }

    public function testNoLinkIsPossibleOnceAllSixteenCubesAreUsed(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P056']);
        $f->game->linkedCells[self::P1] = [40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55];
        $this->assertSame([], $f->engine()->openWindow('KE', [$this->linked(self::P1, $sat, 26)])->playersNeedingInput);

        $g = new E6Fixture(60);
        $sat = $g->active(26, Direction::NE, ['P056', 'P057']);
        $g->game->linkedCells[self::P1] = [40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54];   // 残り 1 個
        $engine = $g->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $this->use($engine, 'forward_wide_area_transmitter');
        $this->assertSame([], $engine->questionsFor(self::P1), 'ステージの予約でキューブが尽きる');
    }

    // ---- 世代とバリア（007 §7-3、T18） ----

    public function testSameGenerationSameAreaHasNoPioneerCostButNextGenerationPaysIt(): void
    {
        // P1 の前方（26 北東）も P2 の後方（27 南東）も区域 14。同じバリアでコミットするので互いに先駆コストなし。
        // P3 は世代 0 に P047 で区域 13 を通信確立し、世代 1 の LINKED（地域 A）で P060 が働いて区域 14 を世代 2 でステージするので、
        // 世代 1 でコミットされた P1・P2 のキューブに先駆コスト 3 ずつ
        $f = new E6Fixture(60);
        $f->game->player(3, 60);
        $s1 = $f->active(26, Direction::NE, ['P056']);
        $s2 = $f->active(27, Direction::SE, ['P057'], 4, 0, self::P2);
        $s3 = $f->active(13, Direction::SE, ['P047', 'P060'], 4, 0, 3);
        $engine = $f->engine();

        $engine->openWindow('KE', [
            $this->linked(self::P1, $s1, 26),
            $this->linked(self::P2, $s2, 27),
            new GameEvent(Hook::Turned, 3, $s3),
        ]);
        $this->use($engine, 'forward_wide_area_transmitter', [], [], self::P1);
        $this->use($engine, 'rear_wide_area_transmitter', [], [], self::P2);
        $this->use($engine, 'tornado_bit', [], [], 3);
        $report = $engine->barrier();

        $this->assertSame([14, 14, 13], array_map(static fn(StagedLinkRequest $s): int => $s->cell, $report->stagedLinks));
        foreach ([0, 1] as $i) {
            $cost = LinkCost::fromArray($report->stagedLinks[$i]->cost);
            $this->assertSame([], $cost->pioneer, '同じ世代の同じ区域は先駆コストなし');
            $this->assertSame(10, $cost->total());
        }
        $f->commit($engine, $report->stagedLinks, 1);
        $this->assertSame([50, 50, 50], [$f->game->maya[self::P1], $f->game->maya[self::P2], $f->game->maya[3]]);

        // 世代 1 の LINKED（P3 の区域 13 は地域 A）で P060 が働く。区域 14 は世代 1 のキューブがあるので先駆コストが掛かる
        $q = $this->ask($engine->questionsFor(3), 'north_sea_waveform_relay');
        $this->assertContains(14, $q->fields[0]->candidates);
        $this->use($engine, 'north_sea_waveform_relay', ['area' => 14], [], 3);
        $report = $engine->barrier();
        $this->assertCount(1, $report->stagedLinks);
        $cost = LinkCost::fromArray($report->stagedLinks[0]->cost);
        $this->assertSame([['to' => self::P1, 'amount' => 3], ['to' => self::P2, 'amount' => 3]], $cost->pioneer, '席順で P1、P2');
        $this->assertSame(16, $cost->total(), '区域 5 + 先駆 6 + 特殊 5');

        $f->commit($engine, $report->stagedLinks, 2);
        $this->assertSame([53, 53, 34], [$f->game->maya[self::P1], $f->game->maya[self::P2], $f->game->maya[3]]);
        $this->assertTrue($engine->barrier()->canClose());
    }

    public function testNewLinkEventsReactAgainEveryGenerationAndDuplicateActivatedEffectsAreNotQueued(): void
    {
        // 007 未確定 8: 効果による通信確立も「通信確立したとき」を再び発動する（P049 は常時なので世代ごとに働く）。
        // N-9: 保留中の同じ宿主の起動効果（P048）は世代 1 の LINKED で重複させない
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P056', 'P049', 'P048'], 4, 3);
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $this->assertSame(2, $f->game->fuelUsed[$sat], 'P049: 世代 0 の通信確立で燃料 1 回復');

        $this->use($engine, 'forward_wide_area_transmitter');
        $f->commit($engine, $engine->barrier()->stagedLinks, 1);
        $engine->barrier();
        $this->assertSame(1, $f->game->fuelUsed[$sat], 'P049: 効果による通信確立（世代 1）でも発動');

        $entries = array_filter($engine->questionsFor(self::P1), static fn(Question $q): bool => $q->cardKey === 'variable_orbit_unit');
        $this->assertCount(1, $entries, 'P048 は 1 回だけ');
        $this->use($engine, 'variable_orbit_unit', ['direction' => 'SW']);
        $this->assertSame([], $engine->questionsFor(self::P1));
        $this->assertTrue($engine->barrier()->canClose());
    }

    public function testAPendingActivatedEffectIsNotQueuedTwiceByLaterEventsOfTheSameWindow(): void
    {
        // N-9: 同じ宿主の起動効果は、保留中なら別のイベントでも重複させない（P048 の ORDER の問いが無駄に出ない）
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P048'], 4);
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 10), $this->linked(self::P1, $sat, 11)]);
        $entries = array_filter($engine->questionsFor(self::P1), static fn(Question $q): bool => $q->cardKey === 'variable_orbit_unit');
        $this->assertCount(1, $entries);
        $this->assertNotSame(QuestionType::Order, $engine->questionsFor(self::P1)[0]->type);
    }

    public function testChainOfSpecialLinksStopsBecauseEachActivatedEffectWorksOncePerRound(): void
    {
        // P056 の通信確立 → 世代 1 の LINKED で P057 が働き、さらに世代 2 では P056 は使用済みで止まる
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P056', 'P057']);
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $this->use($engine, 'forward_wide_area_transmitter');
        $first = $engine->barrier()->stagedLinks;
        $f->commit($engine, $first, 1);
        $this->use($engine, 'rear_wide_area_transmitter');
        $second = $engine->barrier()->stagedLinks;
        $f->commit($engine, $second, 2);

        $this->assertSame([14], array_map(static fn($s): int => $s->cell, $first));
        $this->assertSame([37], array_map(static fn($s): int => $s->cell, $second));
        $this->assertSame([], $engine->questionsFor(self::P1));
        $this->assertTrue($engine->barrier()->canClose());
        $this->assertSame([14, 37], array_slice($f->game->linkedCells[self::P1], 0, 2));
    }

    public function testASkippedEffectStagesNothing(): void
    {
        $f = new E6Fixture(60);
        $sat = $f->active(26, Direction::NE, ['P056']);
        $engine = $f->engine();
        $engine->openWindow('KE', [$this->linked(self::P1, $sat, 26)]);
        $engine->skipEffect(self::P1, $this->entry($engine, 'forward_wide_area_transmitter'));
        $this->assertTrue($engine->barrier()->canClose());
        $this->assertArrayNotHasKey('P:56', $f->game->used);
    }
}
