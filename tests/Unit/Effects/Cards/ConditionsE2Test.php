<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Cards;

use Bga\Games\ProjectEdgeworth\Effects\Condition\BonusHolderCondition;
use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionContext;
use Bga\Games\ProjectEdgeworth\Effects\Condition\LandedAreaKindCondition;
use Bga\Games\ProjectEdgeworth\Effects\Condition\NoActionOverlapCondition;
use Bga\Games\ProjectEdgeworth\Effects\Condition\RegionChangedCondition;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Material\ConditionId;
use Bga\Games\ProjectEdgeworth\Material\EffectCondition;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use PHPUnit\Framework\TestCase;

/**
 * E-2 の条件 K08〜K11 の単体テスト（K07 は盤面を読むので `E2EffectsTest` の P093 で確かめる）。
 * イベントは JSON に保存されるので、プレイヤー ID のキーが文字列に変わっても判定できること。
 */
final class ConditionsE2Test extends TestCase
{
    /** @param array<string, mixed> $data */
    private function context(array $data, int $playerId = 1): ConditionContext
    {
        $host = new EffectHost('P', 1, 'x', $playerId, 1, null, 1, true, false);

        return new ConditionContext($host, new GameEvent(Hook::PlanningReveal, null, null, null, null, null, 0, $data));
    }

    public function testNoActionOverlapSurvivesAJsonRoundTrip(): void
    {
        $event = new GameEvent(Hook::PlanningReveal, null, null, null, null, null, 0, ['choices' => [1 => 2, 2 => 3]]);
        $restored = GameEvent::fromArray(json_decode((string) json_encode($event->toArray()), true));
        $host = new EffectHost('P', 3, 'lonesome_rabbit', 1, 1, null, 1, true, false);
        $condition = new NoActionOverlapCondition();

        $this->assertTrue($condition->holds(new EffectCondition(ConditionId::NoActionOverlap), new ConditionContext($host, $restored)));
        $this->assertFalse($condition->holds(
            new EffectCondition(ConditionId::NoActionOverlap),
            $this->context(['choices' => [1 => 2, 2 => 2]]),
        ));
        $this->assertFalse($condition->holds(new EffectCondition(ConditionId::NoActionOverlap), $this->context(['choices' => [2 => 2]])), '自分の選択が無ければ成立しない');
    }

    public function testRegionChanged(): void
    {
        $condition = new RegionChangedCondition();
        $any = new EffectCondition(ConditionId::RegionChanged);
        $onlyAc = new EffectCondition(ConditionId::RegionChanged, ['regions' => ['A', 'C']]);

        $this->assertTrue($condition->holds($any, $this->context(['fromRegion' => 'A', 'toRegion' => 'B'])));
        $this->assertFalse($condition->holds($any, $this->context(['fromRegion' => 'B', 'toRegion' => 'B'])));
        $this->assertTrue($condition->holds($onlyAc, $this->context(['fromRegion' => 'B', 'toRegion' => 'C'])));
        $this->assertFalse($condition->holds($onlyAc, $this->context(['fromRegion' => 'A', 'toRegion' => 'B'])));
        $this->assertFalse($condition->holds($any, $this->context([])));
    }

    public function testLandedAreaKind(): void
    {
        $condition = new LandedAreaKindCondition();
        $land = new EffectCondition(ConditionId::LandedAreaKind, ['kind' => 'LAND']);

        $this->assertTrue($condition->holds($land, $this->context(['areaKind' => 'LAND'])));
        $this->assertFalse($condition->holds($land, $this->context(['areaKind' => 'SEA'])));
        $this->assertFalse($condition->holds($land, $this->context([])));
    }

    public function testBonusHolder(): void
    {
        $condition = new BonusHolderCondition();
        $c = new EffectCondition(ConditionId::IsBonusHolder);

        $this->assertTrue($condition->holds($c, $this->context(['holders' => [1, 3]])));
        $this->assertFalse($condition->holds($c, $this->context(['holders' => [2]])));
        $this->assertFalse($condition->holds($c, $this->context([])));
    }
}
