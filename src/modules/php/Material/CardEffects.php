<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * カード → 効果の定義（011 §7 の表）。`name_id` をキーに最初の呼び出しで 1 度だけ組み立てる。
 *
 * E-0 の時点では、エンジンの検証に使う代表 10 枚（P006、R010、P010、P011、P013、P023、P034、P035、P038、P039）だけを定義している。
 * 残りの定義は E-1 以降で埋める（011 §8）。追加の手順: 011 §7 の表の 1 行を `spec()` で 1 つ足し、`touches` を 007 §10 の付表の
 * 「資源」列に合わせる。部品の実装（`Effects/Parts/`）は PR ごとに追加する。
 */
final class CardEffects
{
    /** @var array<string, EffectSpec>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, EffectSpec> name_id => 定義
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $specs = [];
            foreach (self::build() as $spec) {
                $specs[$spec->cardKey] = $spec;
            }
            self::$cache = $specs;
        }

        return self::$cache;
    }

    public static function get(string $cardKey): ?EffectSpec
    {
        return self::all()[$cardKey] ?? null;
    }

    /** `P001`〜`P099` から引く */
    public static function forProject(string $cardId): ?EffectSpec
    {
        return self::get(ProjectCards::get($cardId)->nameId);
    }

    /** 衛星カードの面から引く（MOON 面は色が違っても同じ定義） */
    public static function forSatelliteFace(SatelliteShape $shape, SatellitePlan $plan): ?EffectSpec
    {
        foreach (SatelliteCards::all() as $card) {
            if ($card->shape === $shape && $card->plan === $plan) {
                return self::get($card->nameId);
            }
        }

        return null;
    }

    /**
     * @return list<EffectSpec>
     */
    private static function build(): array
    {
        $m = ResourceSymbol::MayaGain;
        $pay = ResourceSymbol::MayaPay;
        $host = ResourceScope::HostSatellite;
        $event = ResourceScope::EventSatellite;
        $all = ResourceScope::AllSatellites;

        return [
            ...CardEffectsE2::build(),
            ...CardEffectsE3::specs(),
            ...CardEffectsE4::specs(),
            ...CardEffectsE5::specs(),
            // P006 予算折衝官: 予算発行の始めに 5 マヤ支払って予算 +1
            new EffectSpec(
                'budget_negotiator',
                Hook::BudgetStart,
                EffectKind::Activated,
                true,
                true,
                false,
                PartId::ChangeBudget,
                ['amount' => 1],
                EffectCost::maya(5),
                [],
                [new ResourceTouch($pay), new ResourceTouch(ResourceSymbol::BudgetGain)],
            ),
            // R010 STEALTH PHOBOS: 予算発行フェイズ開始時 +2 マヤ（強制）
            new EffectSpec(
                'stealth_phobos',
                Hook::BudgetStart,
                EffectKind::Activated,
                true,
                false,
                false,
                PartId::GainMaya,
                ['amount' => 2],
                null,
                [],
                [new ResourceTouch($m)],
            ),
            // P010 地上整備技師: この衛星への配備コスト -2（常時の修正）
            new EffectSpec(
                'ground_mechanic',
                Hook::DeployCost,
                EffectKind::Constant,
                false,
                false,
                false,
                PartId::DeployCostMod,
                ['delta' => -2, 'scope' => 'THIS_SATELLITE'],
            ),
            // P011 双配備技師: この衛星に DEVICE を配備したとき、配備コストを支払って DEVICE を手札から配備
            new EffectSpec(
                'twin_deploy_technician',
                Hook::Deployed,
                EffectKind::Activated,
                true,
                true,
                false,
                PartId::DeployFromHand,
                ['targetScope' => 'ANY_OWN', 'filterType' => 'DEVICE', 'surcharge' => true],
                null,
                [
                    new EffectCondition(ConditionId::EventSatellite, ['scope' => 'THIS']),
                    new EffectCondition(ConditionId::EventCardType, ['type' => 'DEVICE']),
                ],
                [
                    new ResourceTouch($pay),
                    new ResourceTouch(ResourceSymbol::HandChoose),
                    new ResourceTouch(ResourceSymbol::Composition, $all),
                ],
            ),
            // P013 クルー指揮官: この衛星に CREW を配備したとき予算 +1（自身の配備には反応しない）
            new EffectSpec(
                'crew_commander',
                Hook::Deployed,
                EffectKind::Activated,
                true,
                false,
                false,
                PartId::ChangeBudget,
                ['amount' => 1],
                null,
                [
                    new EffectCondition(ConditionId::EventSatellite, ['scope' => 'THIS']),
                    new EffectCondition(ConditionId::EventCardType, ['type' => 'CREW']),
                ],
                [new ResourceTouch(ResourceSymbol::BudgetGain)],
            ),
            // P023 統合プランナー: この衛星に配備したとき 1 枚引く（自身の配備には反応しない）
            new EffectSpec(
                'integrated_planner',
                Hook::Deployed,
                EffectKind::Activated,
                false,
                false,
                false,
                PartId::DrawCards,
                ['count' => 1],
                null,
                [new EffectCondition(ConditionId::EventSatellite, ['scope' => 'THIS'])],
                [new ResourceTouch(ResourceSymbol::HandDraw)],
            ),
            // P034 加速ブースター: 打ち上げ時、2 / 3 / 4 / 5 マヤで 1 / 2 / 3 / 4 区域進む
            new EffectSpec(
                'acceleration_booster',
                Hook::Launched,
                EffectKind::OnLaunch,
                false,
                true,
                true,
                PartId::MoveSatellite,
                ['target' => 'THIS', 'fuelPerArea' => 1, 'fireEntered' => false, 'maxByFuel' => true],
                EffectCost::mayaTier([2 => 1, 3 => 2, 4 => 3, 5 => 4]),
                [],
                [
                    new ResourceTouch($pay),
                    new ResourceTouch(ResourceSymbol::FuelConsume, $host),
                    new ResourceTouch(ResourceSymbol::Position, $host),
                    new ResourceTouch(ResourceSymbol::CompositionRead, $host),
                ],
            ),
            // P035 ギャンブルシステム: 打ち上げ時、このカードを捨てて山札の一番上を配備コストなしで配備
            new EffectSpec(
                'gamble_system',
                Hook::Launched,
                EffectKind::OnLaunch,
                false,
                false,
                true,
                PartId::DeployDeckTop,
                ['discardSelf' => true, 'free' => true],
                null,
                [],
                [new ResourceTouch(ResourceSymbol::Composition, $host), new ResourceTouch(ResourceSymbol::CompositionRead, $host)],
            ),
            // P038 燃料交換管: 自分の衛星の打ち上げ時、その衛星の燃料を減らして他の稼働中の衛星の燃料を回復
            new EffectSpec(
                'fuel_transfer_pipe',
                Hook::Launched,
                EffectKind::Activated,
                false,
                true,
                false,
                PartId::TransferFuel,
                [],
                null,
                [new EffectCondition(ConditionId::EventSatellite, ['scope' => 'ANY_OWN'])],
                [
                    new ResourceTouch(ResourceSymbol::FuelConsume, $event),
                    new ResourceTouch(ResourceSymbol::FuelRecover, $all),
                    new ResourceTouch(ResourceSymbol::CompositionRead, $event),
                ],
            ),
            // P039 センサーアレイ: 打ち上げ時、この衛星に配備されている DEVICE の枚数だけ引く
            new EffectSpec(
                'sensor_array',
                Hook::Launched,
                EffectKind::OnLaunch,
                false,
                false,
                true,
                PartId::DrawCards,
                ['countDevice' => true],
                null,
                [],
                [new ResourceTouch(ResourceSymbol::HandDraw), new ResourceTouch(ResourceSymbol::CompositionRead, $host)],
            ),
            ...CardEffectsE1::specs(),
        ];
    }
}
