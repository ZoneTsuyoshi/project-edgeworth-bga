<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Cards;

use Bga\Games\ProjectEdgeworth\Board\Direction;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\ProjectEdgeworth\Flow\Action\ProposalCounts;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;
use Bga\Games\ProjectEdgeworth\Flow\OrbitResolver;
use Bga\Games\ProjectEdgeworth\Material\ProjectType;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E1Fixture;

/**
 * E-1（常時の修正・禁止。011 §8）35 種の効果ごとのテスト。本物のカード定義・部品・条件・盤面（Earth の標準面）をつないだ
 * `E1Fixture` で、`ActionHooks` / `OrbitModifiers` / `EffectQueries` の答えを確かめる。011 §11-3 の FAQ ケース
 * T03・T08・T12・T14・T16・T17・T19・T21・T22・T23・T24・T27・T29・T32 を含む。
 */
final class E1CardsTest extends TestCase
{
    /** 配備コスト（下限 0 の後）。配備するカードの番号とタイプで問い合わせる */
    private function deployCost(E1Fixture $f, int $sat, int $cardNo, ProjectType $type, int $base = 10, int $attached = 0): int
    {
        return $f->queries->deployCost(new QueryContext(1, $sat, $cardNo, $type, attachedCount: $attached), $base)->total();
    }

    /** CREW・DEVICE・SOFTWARE の揃う衛星（P010・P040 に `$software` を足す） */
    private function fullSatellite(E1Fixture $f, string $software, bool $active = true): int
    {
        $sat = $f->satellite(SatelliteShape::Hammer, $active);
        $f->deploy($sat, ['P010', 'P040', $software]);

        return $sat;
    }

    /**
     * @param list<int> $cells
     * @return list<int>
     */
    private function sorted(array $cells): array
    {
        sort($cells);

        return $cells;
    }

    // ---- M01 配備コスト ----

    public function testP010GroundMechanicWorksOnAnInactiveSatelliteOnlyForItsOwnSatellite(): void
    {
        $f = new E1Fixture();
        $here = $f->satellite(active: false);
        $other = $f->satellite();
        $f->deploy($here, ['P010']);

        // 非稼働でも -2。他の衛星には効かない（FAQ 行 7・8、T03）
        $this->assertSame(8, $this->deployCost($f, $here, 14, ProjectType::Crew));
        $this->assertSame(10, $this->deployCost($f, $other, 14, ProjectType::Crew));
    }

    public function testP012DockingMechanicNeedsTheSatelliteToBeActive(): void
    {
        $f = new E1Fixture();
        $active = $f->satellite();
        $inactive = $f->satellite(active: false);
        $f->deploy($active, ['P012']);
        $f->deploy($inactive, ['P012']);

        $this->assertSame(7, $this->deployCost($f, $active, 14, ProjectType::Crew));
        $this->assertSame(10, $this->deployCost($f, $inactive, 14, ProjectType::Crew), '非稼働の衛星の P012 は効かない（T03）');
    }

    public function testP014P015P016DiscountOnlyTheirOwnCardType(): void
    {
        $f = new E1Fixture();
        $sat = $f->satellite(active: false);
        $f->deploy($sat, ['P014']);

        $this->assertSame(7, $this->deployCost($f, $sat, 11, ProjectType::Crew));
        $this->assertSame(10, $this->deployCost($f, $sat, 40, ProjectType::Device));
        $this->assertSame(10, $this->deployCost($f, $sat, 70, ProjectType::Software));

        $f = new E1Fixture();
        $sat = $f->satellite(active: false);
        $f->deploy($sat, ['P015']);
        $this->assertSame(7, $this->deployCost($f, $sat, 40, ProjectType::Device));
        $this->assertSame(10, $this->deployCost($f, $sat, 11, ProjectType::Crew));

        $f = new E1Fixture();
        $sat = $f->satellite(active: false);
        $f->deploy($sat, ['P016']);
        $this->assertSame(7, $this->deployCost($f, $sat, 70, ProjectType::Software));
        $this->assertSame(10, $this->deployCost($f, $sat, 40, ProjectType::Device));
    }

    public function testP017ChiefDeviceOfficerDiscountsDevicesByFourWhileActive(): void
    {
        $f = new E1Fixture();
        $active = $f->satellite();
        $inactive = $f->satellite(active: false);
        $f->deploy($active, ['P017']);
        $f->deploy($inactive, ['P017']);

        $this->assertSame(6, $this->deployCost($f, $active, 40, ProjectType::Device));
        $this->assertSame(10, $this->deployCost($f, $active, 11, ProjectType::Crew));
        $this->assertSame(10, $this->deployCost($f, $inactive, 40, ProjectType::Device));
    }

    public function testP018SquadronAideDiscountsEveryOwnSatelliteWhileActive(): void
    {
        $f = new E1Fixture();
        $host = $f->satellite();
        $other = $f->satellite(active: false);
        $f->deploy($host, ['P018']);
        $enemy = $f->satellite(player: 2);

        $this->assertSame(9, $this->deployCost($f, $host, 14, ProjectType::Crew));
        $this->assertSame(9, $this->deployCost($f, $other, 14, ProjectType::Crew), '自分の全ての衛星に効く');
        // 他のプレイヤーの衛星には効かない
        $this->assertSame(10, $f->queries->deployCost(new QueryContext(2, $enemy, 14, ProjectType::Crew), 10)->total());

        $f2 = new E1Fixture();
        $inactiveHost = $f2->satellite(active: false);
        $f2->deploy($inactiveHost, ['P018']);
        $this->assertSame(10, $this->deployCost($f2, $inactiveHost, 14, ProjectType::Crew), '稼働中のみ');
    }

    public function testP026FrenchBulldogDiscountsItselfPerCardAlreadyOnTheTargetSatellite(): void
    {
        $f = new E1Fixture();
        $sat = $f->satellite();
        $f->deploy($sat, ['P033', 'P040', 'P070']);
        $self = new EffectHost('P', 26, 'french_bulldog', 1, $sat, SatelliteShape::Hammer, 0, true, false);

        // 付いているカード 3 枚（このカード自身は数えない）→ -6
        $cost = $f->queries->deployCost(new QueryContext(1, $sat, 26, ProjectType::Crew, attachedCount: 3), 10, [], $self);
        $this->assertSame(4, $cost->total());
        $this->assertSame(6, $f->hooks->deployCostReduction(1, $f->row($sat), 26, 3));
        $this->assertSame(0, $f->hooks->deployCostReduction(1, $f->row($sat), 26, 0), '付いているカードがなければ引かない');
        // 他のカードの配備コストには効かない（配備しようとするカード自身が P026 のときだけ）
        $this->assertSame(0, $f->hooks->deployCostReduction(1, $f->row($sat), 14, 3));

        // 他の修正と加算（P010 の -2）
        $other = $f->satellite();
        $f->deploy($other, ['P010', 'P040']);
        $this->assertSame(2 * 2 + 2, $f->hooks->deployCostReduction(1, $f->row($other), 26, 2));
    }

    public function testAP026AlreadyOnASatelliteDoesNotChangeOtherCardsCosts(): void
    {
        $f = new E1Fixture();
        $sat = $f->satellite();
        $f->deploy($sat, ['P026']);

        $this->assertSame(10, $this->deployCost($f, $sat, 14, ProjectType::Crew, 10, 1));
    }

    public function testR007RetroPhobosDiscountsEveryOwnSatelliteByOneWhileActive(): void
    {
        $f = new E1Fixture();
        $host = $f->satellite(SatelliteShape::Retro, true, 'retro_phobos');
        $other = $f->satellite(active: false);

        $this->assertSame(9, $this->deployCost($f, $host, 14, ProjectType::Crew));
        $this->assertSame(9, $this->deployCost($f, $other, 40, ProjectType::Device));

        $f2 = new E1Fixture();
        $inactive = $f2->satellite(SatelliteShape::Retro, false, 'retro_phobos');
        $this->assertSame(10, $this->deployCost($f2, $inactive, 14, ProjectType::Crew));
    }

    public function testDeployCostModifiersStackAndTheFloorIsAppliedOnceAtTheEnd(): void
    {
        // T19: コストは 0 未満にならない。下限 0 は全修正の後に 1 回
        $f = new E1Fixture();
        $sat = $f->satellite();
        $f->deploy($sat, ['P010', 'P012', 'P015']);

        $breakdown = $f->queries->deployCost(new QueryContext(1, $sat, 40, ProjectType::Device), 5);
        $this->assertSame(8, $breakdown->reduction(), 'P010 -2、P012 -3、P015 -3');
        $this->assertSame(0, $breakdown->total());
        $this->assertSame(2, $f->queries->deployCost(new QueryContext(1, $sat, 40, ProjectType::Device), 10)->total());
    }

    // ---- M02 打ち上げコスト ----

    public function testP040LaunchAssistUnitDiscountsEveryOwnLaunchEvenWhenInactive(): void
    {
        $f = new E1Fixture();
        $host = $f->satellite(active: false);
        $launching = $f->satellite(active: false);
        $f->deploy($host, ['P040']);

        $this->assertSame(3, $f->hooks->launchCostReduction(1, $f->row($launching, active: false)));
        $this->assertSame(3, $f->hooks->launchCostReduction(1, $f->row($host, active: false)), '自身の衛星の打ち上げにも効く');
        $this->assertSame(0, $f->hooks->launchCostReduction(2, $f->row($launching, player: 2, active: false)));
    }

    public function testR006HammerPhobosDoesNotApplyToItsOwnLaunch(): void
    {
        // FAQ 行 67、T27: 打ち上げる衛星は非稼働なので、HAMMER 自身の打ち上げには効かない
        $f = new E1Fixture();
        $hammer = $f->satellite(SatelliteShape::Hammer, false, 'hammer_phobos');
        $this->assertSame(0, $f->hooks->launchCostReduction(1, $f->row($hammer, active: false)));

        // 別の衛星の打ち上げにも、HAMMER が非稼働のうちは効かない。稼働中なら -3（自分の全ての衛星）
        $launching = $f->satellite(SatelliteShape::Scallop, false);
        $this->assertSame(0, $f->hooks->launchCostReduction(1, $f->row($launching, active: false)));
        $f->game->setActive($hammer, true);
        $this->assertSame(3, $f->hooks->launchCostReduction(1, $f->row($launching, active: false)));
        $this->assertSame(0, $f->hooks->launchCostReduction(2, $f->row($launching, player: 2, active: false)));
    }

    // ---- M03 / M04 / M05 通信確立 ----

    public function testR008ScallopPhobosLowersTheLinkCostByOneWhileActive(): void
    {
        $f = new E1Fixture();
        $host = $f->satellite(SatelliteShape::Scallop, true, 'scallop_phobos');
        $other = $f->satellite();

        $this->assertSame(1, $f->hooks->linkCostReduction(1, $f->row($host), 7));
        $this->assertSame(1, $f->hooks->linkCostReduction(1, $f->row($other), 7), '自分の全ての衛星');
        // 特殊通信確立の +5 は下限 0 の後（007 §5-4 の手順 6、T19）。修正の合計は区域コストを超えても下限 0
        $cost = $f->queries->linkCost(new QueryContext(1, $other, cell: 7), 0);
        $this->assertSame(1, $cost->reduction());

        $f2 = new E1Fixture();
        $inactive = $f2->satellite(SatelliteShape::Scallop, false, 'scallop_phobos');
        $this->assertSame(0, $f2->hooks->linkCostReduction(1, $f2->row($inactive, active: false), 7));
    }

    public function testP055StealthShipWaivesThePioneerCostOnlyForItsSatellite(): void
    {
        // T22: 先駆コストだけが 0（区域コストは LinkPhase::cost() が別に必要とする）
        $f = new E1Fixture();
        $host = $f->satellite();
        $other = $f->satellite();
        $f->deploy($host, ['P055']);

        $this->assertTrue($f->hooks->pioneerWaived(1, $f->row($host)));
        $this->assertFalse($f->hooks->pioneerWaived(1, $f->row($other)));
        $this->assertSame(0, $f->hooks->linkCostReduction(1, $f->row($host), 7), '区域コストは免除されない');

        $f2 = new E1Fixture();
        $inactive = $f2->satellite(active: false);
        $f2->deploy($inactive, ['P055']);
        $this->assertFalse($f2->hooks->pioneerWaived(1, $f2->row($inactive, active: false)));
    }

    public function testP052ContinentalAntennaAddsAdjacentLandAreasInEveryMode(): void
    {
        $f = new E1Fixture();
        $sat = $f->satellite();
        $f->deploy($sat, ['P052']);
        $row = $f->row($sat);

        // 区域 29 の隣接: 陸 = 28(W)・16(NW)・40(SW)、海 = 30(E)・17(NE)・41(SE)
        $expected = [16, 28, 40];
        $this->assertSame($expected, $this->sorted($f->hooks->extraLinkTargets(1, $row, StepMode::Main)));
        $this->assertSame($expected, $this->sorted($f->hooks->extraLinkTargets(1, $row, StepMode::Bonus)), '特殊通信確立にも常に効く（FAQ 行 24）');
    }

    public function testP053StarboardAndP054PortAddTheTwoAreasOnEachSideOfTheHeading(): void
    {
        $f = new E1Fixture();
        $starboard = $f->satellite();
        $port = $f->satellite();
        $f->deploy($starboard, ['P053']);
        $f->deploy($port, ['P054']);

        // 区域 29 で右上（NE）を向く: 右側 = E(30)・SE(41)、左側 = NW(16)・W(28)
        $this->assertSame([30, 41], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($starboard), StepMode::Main)));
        $this->assertSame([16, 28], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($port), StepMode::Main)));
        // 左下（SW）を向くと左右が入れ替わる: 右側 = W(28)・NW(16)
        $this->assertSame([16, 28], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($starboard, dir: Direction::SW), StepMode::Main)));
    }

    public function testP053DoesNotConsiderReflectionAtTheTopRow(): void
    {
        // FAQ 行 26: 最上段で左上を向く場合、右側隣接は右隣の区域のみ
        $f = new E1Fixture();
        $sat = $f->satellite();
        $f->deploy($sat, ['P053']);

        $row = $f->row($sat, E1Fixture::CELL_TOP, Direction::NW);
        $this->assertSame([5], $f->hooks->extraLinkTargets(1, $row, StepMode::Main));
    }

    public function testP064ForwardAndP065RearBeamAntennasAddThreeAreas(): void
    {
        $f = new E1Fixture();
        $forward = $f->satellite();
        $rear = $f->satellite();
        $f->deploy($forward, ['P064']);
        $f->deploy($rear, ['P065']);

        // 区域 29 で NE を向く: 前方 = NE(17)・E(30)・NW(16)、後方 = SW(40)・W(28)・SE(41)
        $this->assertSame([16, 17, 30], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($forward), StepMode::Main)));
        $this->assertSame([28, 40, 41], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($rear), StepMode::Main)));
    }

    public function testFrontThreeDoesNotIncludeTheReflectedAreaAtTheTopRow(): void
    {
        // T32（FAQ 行 27・36）: 上端で右上を向くとき、前方に右下は含まれない
        $f = new E1Fixture();
        $sat = $f->satellite();
        $f->deploy($sat, ['P064']);

        // 区域 4（x = 8、y = 0）の前方: NE(盤面外)・NW(盤面外)・E(5)。右下 SE(16) は含まれない
        $targets = $f->hooks->extraLinkTargets(1, $f->row($sat, E1Fixture::CELL_TOP, Direction::NE), StepMode::Main);
        $this->assertSame([5], $targets);
        $this->assertNotContains(16, $targets);
    }

    public function testLeftAntennaWrapsAcrossTheBoardEdge(): void
    {
        // 区域 0（x = 0、y = 0）で NW を向く: 左側 = W（左右は接続して区域 11）・SW（区域 23）
        $f = new E1Fixture();
        $sat = $f->satellite();
        $f->deploy($sat, ['P054']);

        $this->assertSame([11, 23], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($sat, 0, Direction::NW), StepMode::Main)));
    }

    public function testAntennasOnlyWidenTheTargetsOfTheirOwnSatellite(): void
    {
        $f = new E1Fixture();
        $host = $f->satellite();
        $other = $f->satellite();
        $f->deploy($host, ['P052']);
        $inactive = $f->satellite(active: false);
        $f->deploy($inactive, ['P052']);

        $this->assertSame([], $f->hooks->extraLinkTargets(1, $f->row($other), StepMode::Main));
        $this->assertSame([], $f->hooks->extraLinkTargets(1, $f->row($inactive, active: false), StepMode::Main), '稼働中のみ');
    }

    public function testP062DustOceanAndP063ChileOceanAreGatedByTheRegionBelowTheSatellite(): void
    {
        $f = new E1Fixture();
        $dust = $f->satellite();
        $chile = $f->satellite();
        $f->deploy($dust, ['P062']);
        $f->deploy($chile, ['P063']);

        // 区域 49（地域 E）の隣接する海 = 48・50・60・61。P062（E・G）は効き、P063（F・H）は効かない
        $this->assertSame([48, 50, 60, 61], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($dust, 49), StepMode::Main)));
        $this->assertSame([], $f->hooks->extraLinkTargets(1, $f->row($chile, 49), StepMode::Main));
        // 区域 52（地域 F）の隣接する海 = 51・63・64。P063 は効き、P062 は効かない
        $this->assertSame([51, 63, 64], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($chile, 52), StepMode::Main)));
        $this->assertSame([], $f->hooks->extraLinkTargets(1, $f->row($dust, 52), StepMode::Main));
        // 地域 B では両方とも働かない
        $this->assertSame([], $f->hooks->extraLinkTargets(1, $f->row($dust, E1Fixture::CELL_B_SEA), StepMode::Main));
    }

    public function testR014ConcordeDeimosAddsAllSixAdjacentAreasOnlyForTheBonus(): void
    {
        // T29（FAQ 行 72）
        $f = new E1Fixture();
        $sat = $f->satellite(SatelliteShape::Concorde, true, 'concorde_deimos');
        $row = $f->row($sat);

        $this->assertSame([16, 17, 28, 30, 40, 41], $this->sorted($f->hooks->extraLinkTargets(1, $row, StepMode::Bonus)));
        $this->assertSame([], $f->hooks->extraLinkTargets(1, $row, StepMode::Main), 'メインでは広がらない');

        $f2 = new E1Fixture();
        $inactive = $f2->satellite(SatelliteShape::Concorde, false, 'concorde_deimos');
        $this->assertSame([], $f2->hooks->extraLinkTargets(1, $f2->row($inactive, active: false), StepMode::Bonus));
    }

    public function testTargetExpansionsFromSeveralCardsAreUnited(): void
    {
        $f = new E1Fixture();
        $sat = $f->satellite();
        $f->deploy($sat, ['P052', 'P053']);

        // 陸 {16, 28, 40} と右側 {30, 41} の和集合
        $this->assertSame([16, 28, 30, 40, 41], $this->sorted($f->hooks->extraLinkTargets(1, $f->row($sat), StepMode::Main)));
    }

    // ---- F01 禁止 ----

    public function testP033SeventhPrinceForbidsDeployingToItsSatelliteEvenWhenInactive(): void
    {
        $f = new E1Fixture();
        $prince = $f->satellite(active: false);
        $other = $f->satellite();
        $f->deploy($prince, ['P033']);

        $this->assertTrue($f->hooks->isForbidden(ForbiddenAction::DeployTo, $f->row($prince, active: false)));
        $this->assertFalse($f->hooks->isForbidden(ForbiddenAction::DeployTo, $f->row($other)), '他の衛星には配備できる（T21）');
        $this->assertFalse($f->hooks->isForbidden(ForbiddenAction::Turn, $f->row($prince, active: false)));
        $this->assertFalse($f->hooks->isForbidden(ForbiddenAction::Link, $f->row($prince, active: false)));
    }

    public function testP046FixedOrbitUnitForbidsTurningWhileActive(): void
    {
        // T08（FAQ 行 54）: 転回できない。反射は盤面の移動の規則で、禁止の対象ではない
        $f = new E1Fixture();
        $fixed = $f->satellite();
        $inactive = $f->satellite(active: false);
        $f->deploy($fixed, ['P046']);
        $f->deploy($inactive, ['P046']);

        $this->assertTrue($f->hooks->isForbidden(ForbiddenAction::Turn, $f->row($fixed)));
        $this->assertFalse($f->hooks->isForbidden(ForbiddenAction::DeployTo, $f->row($fixed)));
        $this->assertFalse($f->hooks->isForbidden(ForbiddenAction::Link, $f->row($fixed)));
        $this->assertFalse($f->hooks->isForbidden(ForbiddenAction::Turn, $f->row($inactive, active: false)), '稼働中のみ');
    }

    public function testP066SignalBlockerForbidsLinkingAndBeingChosenByCardEffects(): void
    {
        // T21（FAQ 行 56）: 通信確立できない。カード効果の対象にも選べない（`ALLOWED` の問い合わせで同じ答え）
        $f = new E1Fixture();
        $blocker = $f->satellite();
        $other = $f->satellite();
        $f->deploy($blocker, ['P066']);

        $this->assertTrue($f->hooks->isForbidden(ForbiddenAction::Link, $f->row($blocker)));
        $this->assertFalse($f->hooks->isForbidden(ForbiddenAction::Link, $f->row($other)));
        $this->assertFalse($f->queries->allowed(new QueryContext(1, $blocker, action: ForbiddenAction::Link)));
        $this->assertTrue($f->queries->allowed(new QueryContext(1, $other, action: ForbiddenAction::Link)));
    }

    // ---- M06 企画の枚数 ----

    public function testP067ChuryumovAddsOneDrawInMainAndBonusButNotKeep(): void
    {
        $f = new E1Fixture();
        $f->deploy($f->satellite(), ['P067']);

        $this->assertEquals(new ProposalCounts(3, 1), $f->hooks->proposalCounts(1, false, new ProposalCounts(2, 1)));
        $this->assertEquals(new ProposalCounts(6, 2), $f->hooks->proposalCounts(1, true, new ProposalCounts(5, 2)));
    }

    public function testP071ChandrasekharAddsDrawAndKeepOnlyToTheBonus(): void
    {
        $f = new E1Fixture();
        $f->deploy($f->satellite(), ['P071']);

        $this->assertEquals(new ProposalCounts(2, 1), $f->hooks->proposalCounts(1, false, new ProposalCounts(2, 1)));
        $this->assertEquals(new ProposalCounts(6, 3), $f->hooks->proposalCounts(1, true, new ProposalCounts(5, 2)));
    }

    public function testP073TeslaMemoryAddsOneKeepInMainAndBonus(): void
    {
        $f = new E1Fixture();
        $f->deploy($f->satellite(), ['P073']);

        $this->assertEquals(new ProposalCounts(2, 2), $f->hooks->proposalCounts(1, false, new ProposalCounts(2, 1)));
        $this->assertEquals(new ProposalCounts(5, 3), $f->hooks->proposalCounts(1, true, new ProposalCounts(5, 2)));
    }

    public function testR011HammerDeimosMakesTheBonusSixDrawAndThreeKeepOnly(): void
    {
        $f = new E1Fixture();
        $f->satellite(SatelliteShape::Hammer, true, 'hammer_deimos');

        $this->assertEquals(new ProposalCounts(6, 3), $f->hooks->proposalCounts(1, true, new ProposalCounts(5, 2)));
        $this->assertEquals(new ProposalCounts(2, 1), $f->hooks->proposalCounts(1, false, new ProposalCounts(2, 1)), 'メインは変わらない');

        $f2 = new E1Fixture();
        $f2->satellite(SatelliteShape::Hammer, false, 'hammer_deimos');
        $this->assertEquals(new ProposalCounts(5, 2), $f2->hooks->proposalCounts(1, true, new ProposalCounts(5, 2)), '稼働中のみ');
    }

    public function testR011AndP071StackToSevenDrawAndFourKeep(): void
    {
        // T24（FAQ 行 61）
        $f = new E1Fixture();
        $sat = $f->satellite(SatelliteShape::Hammer, true, 'hammer_deimos');
        $f->deploy($sat, ['P071']);

        $this->assertEquals(new ProposalCounts(7, 4), $f->hooks->proposalCounts(1, true, new ProposalCounts(5, 2)));

        // P067・P073 とも加算（順序に依らない）
        $f->deploy($sat, ['P067', 'P073']);
        $this->assertEquals(new ProposalCounts(8, 5), $f->hooks->proposalCounts(1, true, new ProposalCounts(5, 2)));
    }

    public function testR011DoesNotApplyToABonusGainedByAnEuropaDeclaration(): void
    {
        // T24（FAQ 行 71）: R016 で得たボーナスには R011 は効かない。P071 は効く
        $f = new E1Fixture();
        $sat = $f->satellite(SatelliteShape::Hammer, true, 'hammer_deimos');
        $f->deploy($sat, ['P071']);
        $declared = new QueryContext(1, mode: StepMode::Bonus, ownActionChoice: false);
        $chosen = new QueryContext(1, mode: StepMode::Bonus, ownActionChoice: true);

        $this->assertEquals(new ProposalCounts(6, 3), $f->queries->proposalCounts($declared, new ProposalCounts(5, 2)));
        $this->assertEquals(new ProposalCounts(7, 4), $f->queries->proposalCounts($chosen, new ProposalCounts(5, 2)));
    }

    // ---- M09 / M10 ----

    public function testP008IntegratedOperatorAddsTwoPerSatelliteWithAllThreeTypes(): void
    {
        $f = new E1Fixture();
        $f->deploy($f->satellite(), ['P008', 'P040', 'P070']); // P008 は CREW
        $this->fullSatellite($f, 'P068', false); // 非稼働でも揃っていれば数える
        $partial = $f->satellite();
        $f->deploy($partial, ['P010', 'P040']);

        $this->assertSame(10 + 2 * 2, $f->hooks->budgetMainGain(1, 10));
        $this->assertSame(10, $f->hooks->budgetMainGain(2, 10), '他のプレイヤーには効かない');
    }

    public function testP008NeedsItsSatelliteToBeActiveAndAFullSatellite(): void
    {
        $f = new E1Fixture();
        $inactive = $f->satellite(active: false);
        $f->deploy($inactive, ['P008', 'P040', 'P070']);
        $this->assertSame(10, $f->hooks->budgetMainGain(1, 10), '非稼働の P008 は効かない');

        $f2 = new E1Fixture();
        $sat = $f2->satellite();
        $f2->deploy($sat, ['P008']);
        $this->assertSame(10, $f2->hooks->budgetMainGain(1, 10), '揃った衛星がなければ 0');
    }

    public function testP077OrionRewardAndR009ConcordePhobosAddUpToSevenMaya(): void
    {
        // T14（FAQ 行 31・60・70）: 3 + 2 + 2
        $f = new E1Fixture();
        $sat = $f->satellite(SatelliteShape::Concorde, true, 'concorde_phobos');
        $this->assertSame(5, $f->hooks->sellPrice(1, 3), 'R009 だけで +2');
        $f->deploy($sat, ['P077']);
        $this->assertSame(7, $f->hooks->sellPrice(1, 3));
        $this->assertSame(3, $f->hooks->sellPrice(2, 3), '他のプレイヤーには効かない');

        $f2 = new E1Fixture();
        $inactive = $f2->satellite(active: false);
        $f2->deploy($inactive, ['P077']);
        $this->assertSame(3, $f2->hooks->sellPrice(1, 3), '稼働中の衛星のカードだけ数える');
    }

    // ---- M11 / M12 / M13 循環フェイズ ----

    public function testP079ZeroGravityAlphaStopsTheFuelConsumptionAboveRegionsAAndH(): void
    {
        // T16（FAQ 行 33）: 移動後の位置の地域で判定する。区域 24 = 地域 A、45 = 地域 H、29 = 地域 B
        $f = new E1Fixture();
        $sat = $this->fullSatellite($f, 'P079');

        $this->assertSame(0, $f->orbit->moveFuelCost($f->orbitSat($sat, 24)));
        $this->assertSame(0, $f->orbit->moveFuelCost($f->orbitSat($sat, 45)));
        $this->assertSame(1, $f->orbit->moveFuelCost($f->orbitSat($sat, E1Fixture::CELL_B_SEA)));
    }

    public function testP080ZeroGravityBetaStopsTheFuelConsumptionAboveRegionsBAndE(): void
    {
        $f = new E1Fixture();
        $sat = $this->fullSatellite($f, 'P080');

        $this->assertSame(0, $f->orbit->moveFuelCost($f->orbitSat($sat, E1Fixture::CELL_B_SEA)));
        $this->assertSame(0, $f->orbit->moveFuelCost($f->orbitSat($sat, 49)));
        $this->assertSame(1, $f->orbit->moveFuelCost($f->orbitSat($sat, 24)));
    }

    public function testZeroGravityNeedsAllThreeTypesAndAnActiveSatellite(): void
    {
        $f = new E1Fixture();
        $partial = $f->satellite();
        $f->deploy($partial, ['P079', 'P010']); // DEVICE がない
        $this->assertSame(1, $f->orbit->moveFuelCost($f->orbitSat($partial, 24)));

        $f2 = new E1Fixture();
        $inactive = $this->fullSatellite($f2, 'P079', false);
        $this->assertSame(1, $f2->orbit->moveFuelCost($f2->orbitSat($inactive, 24)));

        // 他の衛星の P079 は効かない
        $f3 = new E1Fixture();
        $this->fullSatellite($f3, 'P079');
        $other = $f3->satellite();
        $this->assertSame(1, $f3->orbit->moveFuelCost($f3->orbitSat($other, 24)));
    }

    public function testP085KesslerAvoidanceSystemMakesTheNearMissCostOneFromTheStart(): void
    {
        // T17（FAQ 行 35）
        $f = new E1Fixture();
        $kessler = $f->satellite();
        $f->deploy($kessler, ['P085']);
        $other = $f->satellite();

        $this->assertSame(1, $f->orbit->nearMissFuelCost($f->orbitSat($kessler, E1Fixture::CELL_B_SEA)));
        $this->assertSame(2, $f->orbit->nearMissFuelCost($f->orbitSat($other, E1Fixture::CELL_B_SEA)));

        $f2 = new E1Fixture();
        $inactive = $f2->satellite(active: false);
        $f2->deploy($inactive, ['P085']);
        $this->assertSame(2, $f2->orbit->nearMissFuelCost($f2->orbitSat($inactive, E1Fixture::CELL_B_SEA)));
    }

    public function testP094DeconflictExemptsItsSatelliteAndAlsoTheOnlyOtherSatellite(): void
    {
        // T23（FAQ 行 58）
        $f = new E1Fixture();
        $decon = $this->fullSatellite($f, 'P094');
        $other = $f->satellite();
        $cell = E1Fixture::CELL_B_SEA;

        $this->assertTrue($f->orbit->nearMissExempt($f->orbitSat($decon, $cell)));
        $this->assertFalse($f->orbit->nearMissExempt($f->orbitSat($other, $cell)));
        $two = [$f->orbitSat($decon, $cell), $f->orbitSat($other, $cell)];
        $this->assertSame([], OrbitResolver::nearMissIds($two, $f->orbit), '同じ区域の他の衛星が 1 機ならその衛星もニアミスしない');
    }

    public function testP094DeconflictLeavesTheOthersNearMissingWhenThreeShareACell(): void
    {
        $f = new E1Fixture();
        $decon = $this->fullSatellite($f, 'P094');
        $b = $f->satellite();
        $c = $f->satellite(player: 2);
        $cell = E1Fixture::CELL_B_SEA;
        $sats = [$f->orbitSat($decon, $cell), $f->orbitSat($b, $cell), $f->orbitSat($c, $cell, player: 2)];

        $this->assertSame([$b, $c], OrbitResolver::nearMissIds($sats, $f->orbit), '3 機なら P094 以外の 2 機はニアミスする');
    }

    public function testP094NeedsAllThreeTypes(): void
    {
        $f = new E1Fixture();
        $partial = $f->satellite();
        $f->deploy($partial, ['P094', 'P010']);
        $this->assertFalse($f->orbit->nearMissExempt($f->orbitSat($partial, E1Fixture::CELL_B_SEA)));
    }
}
