<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Cards;

use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionsE2;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\EffectEngine;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartRegistry;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE2;
use Bga\Games\ProjectEdgeworth\Effects\WindowResult;
use Bga\Games\ProjectEdgeworth\Material\CardEffectCatalog;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E2Cards;
use ProjectEdgeworthTests\Support\Effects\FakeGame;

/**
 * 効果 E-2（強制の反応効果）36 種の結線テスト: 本物の `CardEffects`・`PartsE2`・`ConditionsE2` で、イベント → 窓 → 結果を確かめる。
 * 強制・選択なしなので、窓を開くだけで自動に解決し、誰の答えも待たない（011 §5-3）。
 */
final class E2EffectsTest extends TestCase
{
    private const P1 = 1;
    private const P2 = 2;

    private function game(): FakeGame
    {
        $game = (new FakeGame())->player(self::P1, 10)->player(self::P2, 10);
        $game->deck = [101, 102, 103, 104, 105, 106];

        return $game;
    }

    /** 効果を持たない（`CardEffects` に定義のない）名前の衛星カードで衛星を作る */
    private function sat(FakeGame $game, int $player, SatelliteShape $shape, bool $active = true, string $key = 'plain_satellite'): int
    {
        return $game->satellite($player, $shape, $active, $key);
    }

    private function engine(FakeGame $game): EffectEngine
    {
        return new EffectEngine(
            $game,
            $game,
            PartsE2::register(new PartRegistry()),
            new CardEffectCatalog(),
            ConditionsE2::register(new ConditionEvaluator(), $game),
        );
    }

    /**
     * 窓を開き、誰の答えも待たずに閉じられることを確かめて閉じる。
     *
     * @param list<GameEvent> $events
     */
    private function open(FakeGame $game, string $window, array $events): WindowResult
    {
        $engine = $this->engine($game);
        $report = $engine->openWindow($window, $events);
        $this->assertFalse($report->needsInput(), 'E-2 の効果は強制で、問いは出ない');
        $this->assertTrue($report->canClose());

        return $engine->closeWindow();
    }

    private function deployed(int $player, int $sat, int $cardNo): GameEvent
    {
        return new GameEvent(Hook::Deployed, $player, $sat, $cardNo, ProjectCards::get(sprintf('P%03d', $cardNo))->type);
    }

    private function launched(int $sat, int $player = self::P1): GameEvent
    {
        return new GameEvent(Hook::Launched, $player, $sat);
    }

    private function turned(int $sat): GameEvent
    {
        return new GameEvent(Hook::Turned, self::P1, $sat);
    }

    private function landed(int $sat, string $areaKind): GameEvent
    {
        return new GameEvent(Hook::Landed, self::P1, $sat, null, null, null, 0, ['areaKind' => $areaKind]);
    }

    private function entered(int $sat, int $cell, string $from, string $to): GameEvent
    {
        return new GameEvent(Hook::OrbitEntered, self::P1, $sat, null, null, $cell, 0, ['fromRegion' => $from, 'toRegion' => $to]);
    }

    // ---- 計画フェイズ: P001・P002・P003 ----

    public function testP001AndP002KeepTheirUprightActionCardsInHand(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P001')->deploy($sat, 'P002');
        $game->upright[self::P1] = [2, 4];

        $this->open($game, 'PL1', [new GameEvent(Hook::PlanningRetrieve, self::P1, null, null, null, null, 0, ['upright' => [2, 4]])]);

        // P001 は II・III、P002 は IV・V。異なるカードを戻すので衝突しない
        $this->assertSame([2, 4], $game->keptActions[self::P1]);
        $this->assertSame([], $game->upright[self::P1]);
        $this->assertSame(['P:1' => true, 'P:2' => true], $game->used);
    }

    public function testP001IgnoresOtherActionsAndIsNotUsedWhenThereIsNothingToKeep(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P001')->deploy($sat, 'P002');

        $this->open($game, 'PL1', [new GameEvent(Hook::PlanningRetrieve, self::P1, null, null, null, null, 0, ['upright' => [1, 5]])]);

        // P001（II・III）は対象なしで使用済みにならず、P002（IV・V）は V だけ戻す
        $this->assertSame([5], $game->keptActions[self::P1]);
        $this->assertSame(['P:2' => true], $game->used);
    }

    public function testP001NeedsAnActiveSatellite(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false);
        $game->deploy($sat, 'P001');

        $this->open($game, 'PL1', [new GameEvent(Hook::PlanningRetrieve, self::P1, null, null, null, null, 0, ['upright' => [2]])]);

        $this->assertSame([], $game->keptActions);
    }

    public function testP003GivesTwoMayaOnlyWhenTheActionChoiceDoesNotOverlap(): void
    {
        $game = $this->game();
        $game->deploy($this->sat($game, self::P1, SatelliteShape::Hammer), 'P003');
        $reveal = static fn(array $choices): GameEvent => new GameEvent(Hook::PlanningReveal, null, null, null, null, null, 0, ['choices' => $choices]);

        $this->open($game, 'PL2', [$reveal([self::P1 => 2, self::P2 => 3])]);
        $this->assertSame(12, $game->maya[self::P1]);
        $this->assertSame(10, $game->maya[self::P2]);

        $game->used = [];
        $this->open($game, 'PL2', [$reveal([self::P1 => 4, self::P2 => 4])]);
        $this->assertSame(12, $game->maya[self::P1], '被ったら得ない');
        $this->assertArrayNotHasKey('P:3', $game->used);
    }

    public function testP003ComparesThePublishedChoicesOfEveryPlayer(): void
    {
        $game = $this->game();
        $game->player(3, 0);
        $game->deploy($this->sat($game, self::P1, SatelliteShape::Hammer), 'P003');

        // 3 人: P1 は P3 と被る（P2 とは被らない）
        $this->open($game, 'PL2', [new GameEvent(Hook::PlanningReveal, null, null, null, null, null, 0, ['choices' => [1 => 5, 2 => 1, 3 => 5]])]);

        $this->assertSame(10, $game->maya[self::P1]);
    }

    // ---- 予算発行フェイズ: P007・R015・R010 ----

    public function testP007AndR015GainMayaWhenTheBonusIsGained(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Stealth, true, 'stealth_deimos');
        $game->deploy($sat, 'P007');

        $this->open($game, 'B2', [new GameEvent(Hook::BudgetBonusGain, null, null, null, null, null, 0, ['holders' => [self::P1]])]);

        // M+ 同士は衝突しないので、順序を聞かず両方が働く（+2 と +3）
        $this->assertSame(15, $game->maya[self::P1]);
        $this->assertSame(10, $game->maya[self::P2]);
    }

    public function testP007AndR015DoNotWorkForAPlayerWithoutTheBonus(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Stealth, true, 'stealth_deimos');
        $game->deploy($sat, 'P007');

        $this->open($game, 'B2', [new GameEvent(Hook::BudgetBonusGain, null, null, null, null, null, 0, ['holders' => [self::P2]])]);

        $this->assertSame(10, $game->maya[self::P1]);
    }

    public function testR010GivesTwoMayaAtTheStartOfTheBudgetPhase(): void
    {
        $game = $this->game();
        $this->sat($game, self::P1, SatelliteShape::Stealth, true, 'stealth_phobos');

        $this->open($game, 'B0', [new GameEvent(Hook::BudgetStart, self::P1)]);

        $this->assertSame(12, $game->maya[self::P1]);
        $this->assertSame(['S:1' => true], $game->used);
    }

    // ---- 配備: P009・P013・P023・P025・P027〜P032 ----

    public function testP009DrawsOnlyWhenItselfIsDeployed(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false);
        $game->deploy($sat, 'P009');

        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 9)]);
        $this->assertSame([101], $game->hand[self::P1], '非稼働の衛星でも自身の配備で引く（稼働条件なし）');

        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 10)]);
        $this->assertSame([101], $game->hand[self::P1], '他のカードの配備には反応しない');
    }

    public function testP013GivesBudgetForACrewDeployedOnTheSameSatelliteButNotItself(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $other = $this->sat($game, self::P1, SatelliteShape::Retro);
        $game->deploy($sat, 'P013')->deploy($sat, 'P001')->deploy($other, 'P002');

        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 13)]);
        $this->assertSame(0, $game->budget[self::P1], '自身の配備には反応しない（FAQ 行 11）');

        $this->open($game, 'DE', [$this->deployed(self::P1, $other, 2)]);
        $this->assertSame(0, $game->budget[self::P1], '他の衛星への配備には反応しない');

        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 1)]);
        $this->assertSame(1, $game->budget[self::P1]);
    }

    public function testP023DrawsForAnyCardOnItsSatelliteButNotForItselfAndDoesNotAskTheOrderWithP013(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false);
        $game->deploy($sat, 'P023')->deploy($sat, 'P013')->deploy($sat, 'P001');

        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 23)]);
        $this->assertSame([], $game->hand[self::P1]);

        // P013（B+）と P023（H+）は衝突しない（S03）ので、順序を聞かずに自動で解決する。P023 は稼働条件なし、P013 は稼働中のみ
        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 1)]);
        $this->assertSame([101], $game->hand[self::P1]);
        $this->assertSame(0, $game->budget[self::P1], 'P013 は非稼働の衛星では働かない');
    }

    public function testP025DrawsTwoForASoftwareOnItsOwnSatelliteOnly(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $other = $this->sat($game, self::P1, SatelliteShape::Retro);
        $game->deploy($sat, 'P025')->deploy($sat, 'P067')->deploy($sat, 'P001')->deploy($other, 'P068');

        $this->open($game, 'DE', [$this->deployed(self::P1, $other, 68)]);
        $this->assertSame([], $game->hand[self::P1], '他の衛星');
        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 1)]);
        $this->assertSame([], $game->hand[self::P1], 'CREW');
        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 67)]);
        $this->assertSame([101, 102], $game->hand[self::P1]);
    }

    /**
     * @return array<string, array{string, int, string}> カード => [配備するカード ID, 配備されるカード番号, 効果('draw' | 'budget')]
     */
    public static function plannersAndInvestors(): array
    {
        return [
            'P027 人事プランナー' => ['P027', 1, 'draw'],
            'P028 機器プランナー' => ['P028', 38, 'draw'],
            'P029 ITプランナー' => ['P029', 67, 'draw'],
            'P030 人材育成官' => ['P030', 1, 'budget'],
            'P031 機器投資官' => ['P031', 38, 'budget'],
            'P032 IT投資官' => ['P032', 67, 'budget'],
        ];
    }

    #[DataProvider('plannersAndInvestors')]
    public function testPlannersAndInvestorsReactToTheirCardTypeOnAnyOwnSatellite(string $host, int $deployedNo, string $effect): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $other = $this->sat($game, self::P1, SatelliteShape::Retro);
        $game->deploy($sat, $host)->deploy($other, sprintf('P%03d', $deployedNo));
        $wrongNo = $deployedNo === 1 ? 38 : 1;
        $game->deploy($other, sprintf('P%03d', $wrongNo));

        $this->open($game, 'DE', [$this->deployed(self::P1, $other, $wrongNo)]);
        $this->assertSame([], $game->hand[self::P1]);
        $this->assertSame(0, $game->budget[self::P1]);

        $this->open($game, 'DE', [$this->deployed(self::P1, $other, $deployedNo)]);
        $this->assertSame($effect === 'draw' ? [101] : [], $game->hand[self::P1]);
        $this->assertSame($effect === 'budget' ? 1 : 0, $game->budget[self::P1]);
    }

    public function testT20MainAndBonusDeploymentsOfTheSameTypeTriggerThePlannerOnce(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P027')->deploy($sat, 'P001')->deploy($sat, 'P002');

        // 009: DE の窓は 60 のメイン・ボーナスの後に 1 回開く。CREW 2 枚の配備でも起動効果は各ラウンド 1 回（FAQ 行 52）
        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 1), $this->deployed(self::P1, $sat, 2)]);

        $this->assertSame([101], $game->hand[self::P1]);
        $this->assertSame(['P:27' => true], $game->used);

        // 使用済みなので、同じラウンドの別の窓でも反応しない。ラウンドが替われば再び働く
        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 1)]);
        $this->assertSame([101], $game->hand[self::P1]);
        $game->used = [];
        $this->open($game, 'DE', [$this->deployed(self::P1, $sat, 1)]);
        $this->assertSame([101, 102], $game->hand[self::P1]);
    }

    public function testDeploymentReactionsDoNotWorkForOtherPlayersDeployments(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $theirs = $this->sat($game, self::P2, SatelliteShape::Hammer);
        $game->deploy($sat, 'P027')->deploy($theirs, 'P001');

        $this->open($game, 'DE', [$this->deployed(self::P2, $theirs, 1)]);

        $this->assertSame([], $game->hand[self::P1]);
    }

    // ---- 打ち上げ: P039・P041・P042・R001〜R004 ----

    public function testP039DrawsAsManyAsTheDevicesOnItsSatelliteWhenResolved(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false);
        $game->deploy($sat, 'P039')->deploy($sat, 'P040')->deploy($sat, 'P001');

        $this->open($game, 'LE', [$this->launched($sat)]);

        $this->assertSame([101, 102], $game->hand[self::P1], 'DEVICE 2 枚（P001 は CREW）');
    }

    public function testP039DoesNotReactToAnotherSatellitesLaunch(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false);
        $other = $this->sat($game, self::P1, SatelliteShape::Retro, false);
        $game->deploy($sat, 'P039');

        $this->open($game, 'LE', [$this->launched($other)]);

        $this->assertSame([], $game->hand[self::P1]);
    }

    public function testP041GivesBudgetWheneverAnOwnSatelliteIsLaunched(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false);
        $other = $this->sat($game, self::P1, SatelliteShape::Retro, false);
        $game->deploy($sat, 'P041');

        $this->open($game, 'LE', [$this->launched($other)]);
        $this->assertSame(1, $game->budget[self::P1]);
        $this->assertSame(['P:41' => true], $game->used);

        // 起動効果なので同じラウンドの 2 回目は働かない
        $this->open($game, 'LE', [$this->launched($sat)]);
        $this->assertSame(1, $game->budget[self::P1]);
    }

    public function testP042GivesBudgetPerDeviceIncludingItself(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false);
        $game->deploy($sat, 'P042')->deploy($sat, 'P039')->deploy($sat, 'P001');

        $this->open($game, 'LE', [$this->launched($sat)]);

        $this->assertSame(2, $game->budget[self::P1]);
        $this->assertSame([101, 102], $game->hand[self::P1], '同じ窓の P039 も働く（B+ と H+ は衝突しない）');
    }

    /**
     * @return array<string, array{string, string, int, int}> カード => [name_id, 効果, 量, 形状の添字]
     */
    public static function moons(): array
    {
        return [
            'R001 HAMMER MOON' => ['hammer_moon', 'draw', 1, 0],
            'R002 RETRO MOON' => ['retro_moon', 'budget', 2, 1],
            'R003 SCALLOP MOON' => ['scallop_moon', 'draw', 2, 2],
            'R004 CONCORDE MOON' => ['concorde_moon', 'budget', 4, 3],
        ];
    }

    #[DataProvider('moons')]
    public function testMoonSatellitesReactToTheirOwnLaunchIncludingRelaunch(string $key, string $effect, int $amount, int $shape): void
    {
        $game = $this->game();
        $shapes = [SatelliteShape::Hammer, SatelliteShape::Retro, SatelliteShape::Scallop, SatelliteShape::Concorde];
        $sat = $this->sat($game, self::P1, $shapes[$shape], false, $key);
        $other = $this->sat($game, self::P1, SatelliteShape::Stealth, false);

        $this->open($game, 'LE', [$this->launched($other)]);
        $this->assertSame([], $game->hand[self::P1], '他の衛星の打ち上げには反応しない');
        $this->assertSame(0, $game->budget[self::P1]);

        $this->open($game, 'LE', [$this->launched($sat)]);
        $this->open($game, 'LE', [$this->launched($sat)]); // 着陸して再び打ち上げても発動する（FAQ 行 68）

        $this->assertCount($effect === 'draw' ? 2 * $amount : 0, $game->hand[self::P1]);
        $this->assertSame($effect === 'budget' ? 2 * $amount : 0, $game->budget[self::P1]);
        $this->assertSame([], $game->used, 'ON_LAUNCH に使用済みはない');
    }

    // ---- 転回: P037・P043 ----

    public function testP037DrawsEvenForATurnThatKeepsTheDirection(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $other = $this->sat($game, self::P1, SatelliteShape::Retro);
        $game->deploy($sat, 'P037');

        // 向きを変えない 360 度転回でも `TURNED` は起きる（FAQ 行 17）。イベントに向きは要らない
        $this->open($game, 'TE', [$this->turned($other)]);
        $this->assertSame([], $game->hand[self::P1], '他の衛星の転回には反応しない');
        $this->open($game, 'TE', [$this->turned($sat)]);
        $this->assertSame([101], $game->hand[self::P1]);
    }

    public function testP043RecoversOneFuelOnTurnAndNeverBelowTheInitialDepot(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, true, 'hammer_moon');
        $game->deploy($sat, 'P043');
        $game->fuelUsed[$sat] = 1;

        $this->open($game, 'TE', [$this->turned($sat)]);
        $this->assertSame(0, $game->fuelUsed[$sat]);

        $game->used = [];
        $this->open($game, 'TE', [$this->turned($sat)]);
        $this->assertSame(0, $game->fuelUsed[$sat], '初期燃料庫より下には動かない（FAQ 行 19）');
    }

    // ---- 通信確立: P049 ----

    public function testP049RecoversFuelForEveryLinkOfItsSatelliteIncludingAdditionalOnes(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, true, 'hammer_moon');
        $other = $this->sat($game, self::P1, SatelliteShape::Retro);
        $game->deploy($sat, 'P049');
        $game->fuelUsed[$sat] = 3;

        // 世代 0 と世代 1（追加の通信確立）。常時の効果なので、そのたびに働く（007 未確定 8）
        $this->open($game, 'KE', [
            new GameEvent(Hook::Linked, self::P1, $sat, null, null, 5, 0),
            new GameEvent(Hook::Linked, self::P1, $other, null, null, 6, 0),
            new GameEvent(Hook::Linked, self::P1, $sat, null, null, 7, 1),
        ]);

        $this->assertSame(1, $game->fuelUsed[$sat]);
        $this->assertSame([], $game->used, 'CONSTANT に使用済みはない');
    }

    // ---- 循環フェイズ: P078・P083・P084・P088・P089・P092・P093 ----

    public function testP078DrawsOnceEvenWhenTwoSatellitesLandTogetherAndForItsOwnLanding(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer); // 着陸猶予で稼働中として扱う
        $other = $this->sat($game, self::P1, SatelliteShape::Retro);
        $game->deploy($sat, 'P078');

        $this->open($game, 'O4', [$this->landed($sat, 'LAND'), $this->landed($other, 'SEA')]);

        $this->assertSame([101], $game->hand[self::P1]);
    }

    public function testT15P078DoesNotWorkOnceTheLandingGraceHasEnded(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false); // O4 の窓が閉じた後
        $game->deploy($sat, 'P078');

        $this->open($game, 'O4', [$this->landed($sat, 'LAND')]);

        $this->assertSame([], $game->hand[self::P1]);
    }

    public function testP083AndP084RecoverTwoFuelWhenEnteringTheirRegionsFromAnotherRegion(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, true, 'hammer_moon');
        $game->deploy($sat, 'P083')->deploy($sat, 'P084');

        // 別地域から C に突入: P083（A・C・H）だけ
        $game->fuelUsed[$sat] = 5;
        $this->open($game, 'O1', [$this->entered($sat, 3, 'B', 'C')]);
        $this->assertSame(3, $game->fuelUsed[$sat]);

        // 同じ地域の中の移動、および条件にない地域への突入は働かない
        $game->used = [];
        $this->open($game, 'O1', [$this->entered($sat, 4, 'C', 'C')]);
        $this->open($game, 'O1', [$this->entered($sat, 5, 'C', 'B')]);
        $this->assertSame(3, $game->fuelUsed[$sat]);

        // H は両方の対象。P083 と P084 の両方が回復する（F+ 同士は衝突しない）
        $this->open($game, 'O1', [$this->entered($sat, 6, 'G', 'H')]);
        $this->assertSame(0, $game->fuelUsed[$sat], '3 から 4 回復しても初期燃料庫より下には動かない');
    }

    public function testP084OnlyRecoversInDFAndH(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, true, 'hammer_moon');
        $game->deploy($sat, 'P084');
        $game->fuelUsed[$sat] = 4;

        $this->open($game, 'O1', [$this->entered($sat, 3, 'A', 'C')]);
        $this->assertSame(4, $game->fuelUsed[$sat]);
        $this->open($game, 'O1', [$this->entered($sat, 3, 'A', 'F')]);
        $this->assertSame(2, $game->fuelUsed[$sat]);
    }

    public function testT06P083DoesNotMoveAFuelMarkerThatIsOutsideTheDepot(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, true, 'hammer_moon');
        $game->deploy($sat, 'P083');
        $game->fuelUsed[$sat] = $game->fuelCapacity($sat) + 1; // 燃料切れ（c > C）

        $this->open($game, 'O1', [$this->entered($sat, 3, 'B', 'C')]);

        $this->assertSame($game->fuelCapacity($sat) + 1, $game->fuelUsed[$sat], '庫外のコマは回復しても戻らない（FAQ 行 14）');
    }

    public function testLandingRewardsDependOnTheKindOfAreaAndStackWithEachOther(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P088')->deploy($sat, 'P089')->deploy($sat, 'P092');

        $this->open($game, 'O4', [$this->landed($sat, 'LAND')]);
        $this->assertSame(10 + 8 + 15, $game->maya[self::P1], '陸: P088 の 8 と P089 の 15');

        $game->used = [];
        $this->open($game, 'O4', [$this->landed($sat, 'SEA')]);
        $this->assertSame(33 + 8 + 10, $game->maya[self::P1], '海: P088 の 8 と P092 の 10');
    }

    public function testLandingRewardsAreForOwnSatellitesAndNeedActiveCards(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, false);
        $theirs = $this->sat($game, self::P2, SatelliteShape::Hammer);
        $game->deploy($sat, 'P088');

        $this->open($game, 'O4', [new GameEvent(Hook::Landed, self::P2, $theirs, null, null, null, 0, ['areaKind' => 'LAND'])]);

        $this->assertSame(10, $game->maya[self::P1]);
        $this->assertSame(10, $game->maya[self::P2]);
    }

    public function testP093GivesThreeMayaOnceEnteringAnOwnLinkedAreaAndOnlyForItsOwnSatellite(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $other = $this->sat($game, self::P1, SatelliteShape::Retro);
        $game->deploy($sat, 'P093');
        $game->linkedCells[self::P1] = [5, 6];

        $this->open($game, 'O1', [
            $this->entered($sat, 7, 'B', 'B'), // 通信確立していない区域
            $this->entered($other, 5, 'A', 'A'), // 他の衛星
        ]);
        $this->assertSame(10, $game->maya[self::P1]);

        // 印刷は「起動」（各ラウンド 1 回）。追加移動で 2 回目の突入があっても、1 回だけ働く
        $this->open($game, 'O1', [$this->entered($sat, 5, 'A', 'A'), $this->entered($sat, 6, 'A', 'B')]);
        $this->assertSame(13, $game->maya[self::P1]);
        $this->assertSame(['P:93' => true], $game->used);

        $game->used = [];
        $this->open($game, 'O1', [$this->entered($sat, 6, 'A', 'B')]);
        $this->assertSame(16, $game->maya[self::P1]);
    }

    // ---- 企画: P075・R012・R013 と連鎖 ----

    public function testP075GivesOneMayaPerCardDrawnInTheProposalPhase(): void
    {
        $game = $this->game();
        $game->deploy($this->sat($game, self::P1, SatelliteShape::Hammer), 'P075');

        // 90 のステップ 1（企画の引き）は、ドライバが引いた枚数を `PROPOSAL_DRAWN` で渡す（P067 の追加分を含む）
        $this->open($game, 'PA', [new GameEvent(Hook::ProposalDrawn, self::P1, null, null, null, null, 0, ['count' => 3])]);

        $this->assertSame(13, $game->maya[self::P1]);
        $this->assertSame([], $game->used);
    }

    public function testP075CountsCardsDrawnByAChainedEffectInTheProposalPhase(): void
    {
        $game = $this->game();
        $game->proposalPhase = true;
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P075')->deploy($sat, 'P037')->deploy($sat, 'P023');

        // PB の窓: 転回 → P037 が 1 枚引く → `PROPOSAL_DRAWN`（1 枚）→ P075 が +1 マヤ（FAQ 行 29 の連鎖、007 未確定 11 の暫定案）
        $this->open($game, 'PB', [$this->turned($sat)]);

        $this->assertSame([101], $game->hand[self::P1]);
        $this->assertSame(11, $game->maya[self::P1]);
    }

    public function testChainedDrawsOutsideTheProposalPhaseDoNotTriggerP075(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P075')->deploy($sat, 'P037');

        $this->open($game, 'TE', [$this->turned($sat)]);

        $this->assertSame([101], $game->hand[self::P1]);
        $this->assertSame(10, $game->maya[self::P1]);
    }

    public function testChainedDrawsOfSeveralEffectsAreEachCountedByP075(): void
    {
        $game = $this->game();
        $game->proposalPhase = true;
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, true, 'scallop_moon');
        $game->deploy($sat, 'P075')->deploy($sat, 'P039')->deploy($sat, 'P040');

        // 打ち上げ時: scallop_moon の 2 枚 + P039 の DEVICE 2 枚 = 4 枚。引いたたびに +枚数
        $this->open($game, 'PB', [$this->launched($sat)]);

        $this->assertCount(4, $game->hand[self::P1]);
        $this->assertSame(14, $game->maya[self::P1]);
    }

    public function testR012GivesBudgetOnlyForTheLaunchTurnBonusAndR013DrawsOnlyForTheDeployBonus(): void
    {
        $game = $this->game();
        $sat = $this->sat($game, self::P1, SatelliteShape::Retro, true, 'retro_deimos');
        $sat2 = $this->sat($game, self::P1, SatelliteShape::Scallop, true, 'scallop_deimos');
        $bonus = static fn(string $action): GameEvent => new GameEvent(Hook::ActionBonusStep, self::P1, null, null, null, null, 0, ['action' => $action]);

        $this->open($game, 'ABS', [$bonus('LAUNCH_TURN')]);
        $this->assertSame(1, $game->budget[self::P1]);
        $this->assertSame([], $game->hand[self::P1]);
        $this->assertSame(['S:' . $sat => true], $game->used);

        $game->used = [];
        $this->open($game, 'ABS', [$bonus('DEPLOY')]);
        $this->assertSame(1, $game->budget[self::P1]);
        $this->assertSame([101], $game->hand[self::P1]);
        $this->assertSame(['S:' . $sat2 => true], $game->used);

        $game->used = [];
        $this->open($game, 'ABS', [$bonus('LINK'), $bonus('BUDGET'), $bonus('PROPOSAL')]);
        $this->assertSame(1, $game->budget[self::P1]);
        $this->assertSame([101], $game->hand[self::P1]);
        $this->assertSame([], $game->used);
    }

    // ---- 共通 ----

    public function testT31DrawingFromAnEmptyDeckReshufflesTheDiscardPile(): void
    {
        $game = $this->game();
        $game->deck = [101];
        $game->discard = [201, 202];
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, true, 'scallop_moon');

        $this->open($game, 'LE', [$this->launched($sat)]);

        $this->assertSame([101, 201], $game->hand[self::P1]);
        $this->assertSame([202], $game->deck);
        $this->assertSame([], $game->discard);
    }

    public function testDrawingWhenBothPilesAreEmptyResolvesWithoutDrawing(): void
    {
        $game = $this->game();
        $game->deck = [];
        $sat = $this->sat($game, self::P1, SatelliteShape::Hammer, true, 'scallop_moon');
        $game->deploy($sat, 'P075');
        $game->proposalPhase = true;

        $result = $this->open($game, 'PB', [$this->launched($sat)]);

        $this->assertSame([], $game->hand[self::P1]);
        $this->assertSame(10, $game->maya[self::P1], '引けなければ PROPOSAL_DRAWN も起きない');
        $this->assertSame(1, $result->resolvedCount);
    }

    public function testEveryE2EffectIsMandatoryAndNeedsNoPayment(): void
    {
        foreach (E2Cards::keys() as $cardKey) {
            $spec = (new CardEffectCatalog())->specFor($cardKey);
            $this->assertNotNull($spec, $cardKey);
            $this->assertFalse($spec->optional, $cardKey);
            $this->assertNull($spec->cost, $cardKey);
            $this->assertSame(1, $spec->stages, $cardKey);
        }
    }

    public function testResultLogsDescribeWhatHappened(): void
    {
        $game = $this->game();
        $game->deploy($this->sat($game, self::P1, SatelliteShape::Hammer), 'P003');

        $result = $this->open($game, 'PL2', [new GameEvent(Hook::PlanningReveal, null, null, null, null, null, 0, ['choices' => [1 => 1, 2 => 2]])]);

        $keys = array_map(static fn($l): string => $l->key, $result->logs);
        $this->assertSame(['effectUsed', 'mayaGained'], $keys);
        $this->assertSame(2, $result->logs[1]->args['amount']);
    }
}
