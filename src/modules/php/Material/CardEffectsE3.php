<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * E-3（任意の効果と問い。011 §8）の効果の定義。[Use] / [Skip] と支払い、起動の修正（`USE_MODS`）、EUROPA の宣言、企画のオプション、
 * 2 段の効果（P087）。`touches` は 007 §10 の付表の「資源」列。
 *
 * E-3 の 22 種のうち、P006 は E-0 の代表として `CardEffects::build()` に既にあるので、ここには含めない（残りの 21 種をここで持つ）。
 *
 * - P019〜P021・P036・R021〜R025 は起動の修正（`optional` 真）。プールには入らず、配備・打ち上げ・通信確立の選択に同梱する
 *   `USE_MODS` で使ったものだけが畳み込まれる（`Effects/Query/UseMods`）。
 * - P068・P070・P072 は `PROPOSAL_OPTIONS`。窓を開かず 91 の問いに含める（`Effects/Query/ProposalOptions`、011 §6-5）。
 * - P087 は 2 段（引く → 捨てる）。結果部品は R04 `DISCARD_HAND` が引くところまで持つ（`drawFirst`）。
 */
final class CardEffectsE3
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
        $this_ = new EffectCondition(ConditionId::EventSatellite, ['scope' => 'THIS']);
        $full = new EffectCondition(ConditionId::FullTypes);
        $region = static fn(string ...$regions): EffectCondition => new EffectCondition(ConditionId::LinkAreaRegion, ['regions' => $regions]);
        $kind = static fn(string $k): EffectCondition => new EffectCondition(ConditionId::LinkAreaKind, ['kind' => $k]);
        $running = static fn(string $action): EffectCondition => new EffectCondition(ConditionId::ActionIsRunning, ['action' => $action]);

        return [
            // P005 調達担当官: 予算発行フェイズ中（B3）に 4 マヤ支払って 1 枚引く
            self::task('procurement_officer', Hook::BudgetFree, true, PartId::DrawCards, ['count' => 1], EffectCost::maya(4), [], [$pay, ResourceSymbol::HandDraw]),

            // 起動の修正（USE_MODS）: 各ラウンド 1 回。[Use] を選んだ選択にだけ効く
            // P019〜P021 フリート: 自分の全ての衛星の CREW / DEVICE / SOFTWARE の配備コスト -2
            self::useMod('fleet_commander', Hook::DeployCost, true, PartId::DeployCostMod, ['delta' => -2, 'scope' => 'ALL_OWN', 'type' => 'CREW']),
            self::useMod('fleet_engineer', Hook::DeployCost, true, PartId::DeployCostMod, ['delta' => -2, 'scope' => 'ALL_OWN', 'type' => 'DEVICE']),
            self::useMod('fleet_architect', Hook::DeployCost, true, PartId::DeployCostMod, ['delta' => -2, 'scope' => 'ALL_OWN', 'type' => 'SOFTWARE']),
            // P036 軽量化フレーム: 打ち上げる衛星に配備されているカード 1 枚につき打ち上げコスト -1（稼働条件なし）
            self::useMod('lightweight_frame', Hook::LaunchCost, false, PartId::LaunchCostMod, ['delta' => -1, 'scope' => 'LAUNCHING_SATELLITE', 'perAttachedCard' => true]),

            // 企画のオプション（91 の問いに含める）
            // P068 シャノンエントロピー: 企画で捨てるカードを、手札からも選べる（常時）
            new EffectSpec('shannon_entropy', Hook::ProposalOptions, EffectKind::Constant, true, false, false, PartId::ProposalDiscardScope),
            // P069 ロスコスモス: 企画アクションの後（PB）に 4 マヤ支払って予算 +1
            self::task('roscosmos', Hook::ProposalAfter, true, PartId::ChangeBudget, ['amount' => 1], EffectCost::maya(4), [], [$pay, ResourceSymbol::BudgetGain]),
            // P070 スピンオフ: 手札から追加で 1 枚捨てて +7 マヤ（捨てる額の修正 P077・R009 は効かない。FAQ 行 31・70）
            self::task('spin_off', Hook::ProposalOptions, true, PartId::GainMaya, ['amount' => 7], EffectCost::discard(1), [], [ResourceSymbol::HandChoose, ResourceSymbol::MayaGain]),
            // P072 ランチェスター: 引いたカードを見てから 4 / 7 / 10 マヤで残す枚数 +1 / +2 / +3
            self::task('lanchester', Hook::ProposalOptions, true, PartId::ProposalKeepPurchase, ['tiers' => [4 => 1, 7 => 2, 10 => 3]], EffectCost::mayaTier([4 => 1, 7 => 2, 10 => 3]), [], [$pay]),

            // P086 データダウンリンク: 自分の通信確立済みの区域に突入したとき、3 マヤで 1 枚引く
            self::task(
                'data_downlink',
                Hook::OrbitEntered,
                true,
                PartId::DrawCards,
                ['count' => 1],
                EffectCost::maya(3),
                [$this_, new EffectCondition(ConditionId::EnteredOwnLinkedArea)],
                [$pay, ResourceSymbol::HandDraw],
            ),
            // P087 データローリング: 循環フェイズ開始時に 1 枚引き、その後に手札から 1 枚捨てる（強制の 2 段。捨てても売却額は得ない）
            new EffectSpec(
                'data_rolling',
                Hook::OrbitStart,
                EffectKind::Activated,
                true,
                false,
                false,
                PartId::DiscardHand,
                ['drawFirst' => 1, 'count' => 1, 'justDrawn' => true],
                null,
                [],
                [new ResourceTouch(ResourceSymbol::HandChoose)],
                2,
            ),

            // 衛星カード EUROPA（R016〜R020）: 3 マヤで、自分で選んでいないアクションのボーナスを得る宣言（XD）
            self::declare('hammer_europa', 'PROPOSAL', $full, $running('PROPOSAL')),
            self::declare('retro_europa', 'DEPLOY', $full, $running('DEPLOY')),
            self::declare('scallop_europa', 'LINK', $full, $running('LINK')),
            self::declare('concorde_europa', 'LAUNCH_TURN', $full, $running('LAUNCH_TURN')),
            self::declare('stealth_europa', 'BUDGET', $full, $running('BUDGET')),

            // 衛星カード GANYMEDE（R021〜R025）: 通信確立コストの起動の修正（特殊通信確立にも効く。FAQ 行 74）
            self::useMod('hammer_ganymede', Hook::LinkCost, true, PartId::LinkCostMod, ['delta' => -2, 'scope' => 'LINKING_SATELLITE_IS_THIS'], [$this_, $region('A', 'C', 'E', 'G')]),
            self::useMod('retro_ganymede', Hook::LinkCost, true, PartId::LinkCostMod, ['delta' => -2, 'scope' => 'LINKING_SATELLITE_IS_THIS'], [$this_, $region('B', 'D', 'F', 'H')]),
            self::useMod('scallop_ganymede', Hook::LinkCost, true, PartId::LinkCostMod, ['delta' => -3, 'scope' => 'LINKING_SATELLITE_IS_THIS'], [$this_, $kind('LAND')]),
            self::useMod('concorde_ganymede', Hook::LinkCost, true, PartId::LinkCostMod, ['delta' => -2, 'scope' => 'LINKING_SATELLITE_IS_THIS'], [$this_, $kind('SEA')]),
            self::useMod('stealth_ganymede', Hook::LinkCost, true, PartId::LinkCostMod, ['delta' => -2, 'scope' => 'LINKING_SATELLITE_IS_THIS'], [$this_]),
        ];
    }

    /**
     * 任意（[Use] / [Skip]）・支払いありの起動効果。
     *
     * @param array<string, mixed> $params
     * @param list<EffectCondition> $conditions
     * @param list<ResourceSymbol> $symbols
     */
    private static function task(
        string $key,
        Hook $hook,
        bool $activeOnly,
        PartId $part,
        array $params,
        EffectCost $cost,
        array $conditions,
        array $symbols,
    ): EffectSpec {
        return new EffectSpec(
            $key,
            $hook,
            EffectKind::Activated,
            $activeOnly,
            true,
            false,
            $part,
            $params,
            $cost,
            $conditions,
            array_map(static fn(ResourceSymbol $s): ResourceTouch => new ResourceTouch($s), $symbols),
        );
    }

    /**
     * 起動の修正（選択は `USE_MODS`。資源に触れない）。
     *
     * @param array<string, mixed> $params
     * @param list<EffectCondition> $conditions
     */
    private static function useMod(string $key, Hook $hook, bool $activeOnly, PartId $part, array $params, array $conditions = []): EffectSpec
    {
        return new EffectSpec($key, $hook, EffectKind::Activated, $activeOnly, true, false, $part, $params, null, $conditions, []);
    }

    /** EUROPA: `BONUS_DECLARE` で 3 マヤ支払ってそのアクションのボーナスを得る宣言（R14 + C1(3)） */
    private static function declare(string $key, string $action, EffectCondition ...$conditions): EffectSpec
    {
        return new EffectSpec(
            $key,
            Hook::BonusDeclare,
            EffectKind::Activated,
            true,
            true,
            false,
            PartId::GrantActionBonus,
            ['action' => $action],
            EffectCost::maya(3),
            array_values($conditions),
            [new ResourceTouch(ResourceSymbol::MayaPay)],
        );
    }
}
