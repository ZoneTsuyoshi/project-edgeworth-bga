<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * E-4（衛星の操作。011 §8）の効果の定義: 転回（R08）と移動（R07）。`touches` は 007 §10 の付表の「資源」列。
 *
 * E-4 の 11 種のうち、P034（加速ブースター）と P038（燃料交換管）は E-0 の代表として `CardEffects::build()` に既にあるので、
 * ここには含めない（残りの 9 種をここで持つ）。
 *
 * - P004 は予算発行フェイズの B3、P081・P082・P091 は O1 の窓（`ORBIT_ENTERED`）の結果部品。追加移動・燃料回復は
 *   `EngineOrbitModifiers`（同期の問い合わせ）では扱わず、窓の中のプレイヤーの選択として解決する（申し送り N-5）。
 * - 転回・移動が起こす `TURNED` / `ORBIT_ENTERED` は連鎖する（007 §8-1）。反射は転回ではない。
 */
final class CardEffectsE4
{
    private function __construct()
    {
    }

    /**
     * @return list<EffectSpec>
     */
    public static function specs(): array
    {
        $pay = ResourceSymbol::MayaPay;
        $fuel = ResourceSymbol::FuelConsume;
        $pos = ResourceSymbol::Position;
        $dir = ResourceSymbol::Direction;
        $host = ResourceScope::HostSatellite;
        $all = ResourceScope::AllSatellites;

        $this_ = new EffectCondition(ConditionId::EventSatellite, ['scope' => 'THIS']);
        $other = new EffectCondition(ConditionId::EventSatellite, ['scope' => 'OTHER_OWN']);
        $own = new EffectCondition(ConditionId::EnteredOwnLinkedArea);
        $moved = new EffectCondition(ConditionId::RegionChanged);
        $full = new EffectCondition(ConditionId::FullTypes);

        $extraMove = ['target' => 'THIS', 'areas' => 1, 'fuelPerArea' => 1, 'fireEntered' => true];
        $moveTouches = [new ResourceTouch($fuel, $host), new ResourceTouch($pos, $host)];

        return [
            // P004 軌道修正士: 予算発行フェイズ中（B3）に 2 マヤ支払って、自分の衛星 1 機を 1 区域進め、燃料 1 消費（ニアミス・突入なし。FAQ 行 4）
            self::task(
                'course_corrector',
                Hook::BudgetFree,
                false,
                PartId::MoveSatellite,
                ['target' => 'CHOSEN_OWN_ACTIVE', 'areas' => 1, 'fuelPerArea' => 1, 'fireEntered' => false],
                EffectCost::maya(2),
                [],
                [new ResourceTouch($pay), new ResourceTouch($fuel, $all), new ResourceTouch($pos, $all)],
            ),

            // P024 スワップ技師: 配備したとき、自分の稼働中の衛星 1 機とこの衛星の稼働状態・燃料消費量・場所を入れ替えてもよい（打ち上げ・着陸は起こさない。FAQ 行 12〜14）
            new EffectSpec(
                'swap_technician',
                Hook::Deployed,
                EffectKind::OnDeploy,
                false,
                true,
                true,
                PartId::SwapSatellites,
                [],
                null,
                [],
                [
                    new ResourceTouch(ResourceSymbol::Activity, $all),
                    new ResourceTouch($fuel, $all),
                    new ResourceTouch($pos, $all),
                    new ResourceTouch(ResourceSymbol::Composition, $host),
                ],
            ),

            // 転回（R08）。向きは 6 方向から選ぶ。新しい TURNED を起こす
            // P044 編隊同期装置: 自分の他の衛星が転回したとき、この衛星は転回してもよい（011 §6-4: 転回先は ALL_SATS として扱う）
            self::task('formation_sync_unit', Hook::Turned, true, PartId::TurnSatellite, ['target' => 'THIS'], null, [$other], [new ResourceTouch($dir, $all)]),
            // P048 可変軌道装置: この衛星が通信確立したとき、転回してもよい
            self::task('variable_orbit_unit', Hook::Linked, true, PartId::TurnSatellite, ['target' => 'THIS'], null, [$this_], [new ResourceTouch($dir, $host)]),
            // P090 ギムバル制御: この衛星が地域間を移動したとき、転回してもよい
            self::task('gimbal_control', Hook::OrbitEntered, true, PartId::TurnSatellite, ['target' => 'THIS'], null, [$this_, $moved], [new ResourceTouch($dir, $host)]),
            // P095 フリップマニューバ: この衛星が自分の通信確立済み区域の上空に突入したとき、転回してもよい
            self::task('flip_maneuver', Hook::OrbitEntered, true, PartId::TurnSatellite, ['target' => 'THIS'], null, [$this_, $own], [new ResourceTouch($dir, $host)]),

            // 追加移動（R07、O1）。進んだ先でも「突入したとき」を起こす（007 未確定 9）
            // P081 ホーマンブースター: 自分の通信確立済み区域の上空に突入したとき、2 マヤで 1 区域進め、燃料 1 消費
            self::task('hohmann_booster', Hook::OrbitEntered, true, PartId::MoveSatellite, $extraMove, EffectCost::maya(2), [$this_, $own], [new ResourceTouch($pay), ...$moveTouches]),
            // P082 トライアドブースター: CREW・DEVICE・SOFTWARE が揃っていれば、1 区域追加で進んでもよい（突入の条件なし）
            self::task('triad_booster', Hook::OrbitEntered, true, PartId::MoveSatellite, $extraMove, null, [$this_, $full], $moveTouches),
            // P091 カスケードブースト: この衛星が地域間を移動したとき、1 区域追加で進めてもよい
            self::task('cascade_boost', Hook::OrbitEntered, true, PartId::MoveSatellite, $extraMove, null, [$this_, $moved], $moveTouches),
        ];
    }

    /**
     * 任意（[Use] / [Skip]）の起動効果。
     *
     * @param array<string, mixed> $params
     * @param list<EffectCondition> $conditions
     * @param list<ResourceTouch> $touches
     */
    private static function task(
        string $key,
        Hook $hook,
        bool $activeOnly,
        PartId $part,
        array $params,
        ?EffectCost $cost,
        array $conditions,
        array $touches,
    ): EffectSpec {
        return new EffectSpec($key, $hook, EffectKind::Activated, $activeOnly, true, false, $part, $params, $cost, $conditions, $touches);
    }
}
