<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * E-1（常時の修正・禁止。011 §8）の効果の定義。011 §7 の表の 1 行が 1 つの `EffectSpec`。`CardEffects::build()` が取り込む。
 *
 * 対象は 35 種（P008、P010、P012、P014〜P018、P026、P033、P040、P046、P052〜P055、P062〜P067、P071、P073、P077、P079、P080、
 * P085、P094、R006〜R009、R011、R014）。このうち P010 は E-0 の代表として `CardEffects` が先に持っているので、ここには含めない
 * （重複させない）。007 §10 の「資源」列は、P026 の `Cr` 以外はすべて「—」。
 *
 * 常時の修正・禁止は選択がない（`optional = false`）。R011・R014 は効果タイプが起動（`ACTIVATED`）だが、007 §10 の選択が「—」で
 * [Use] / [Skip] を聞かない強制の修正（007 §5-1 の起動の修正の一覧に入っていない）として定義する。
 */
final class CardEffectsE1
{
    private function __construct()
    {
    }

    /**
     * @return list<EffectSpec>
     */
    public static function specs(): array
    {
        $cr = [new ResourceTouch(ResourceSymbol::CompositionRead, ResourceScope::AllSatellites)];
        $full = new EffectCondition(ConditionId::FullTypes);
        $region = static fn(string ...$regions): EffectCondition => new EffectCondition(ConditionId::BelowRegion, ['regions' => $regions]);

        return [
            // CREW
            // P008 統合運用士: CREW・DEVICE・SOFTWARE が揃う自分の衛星 1 機につき、予算発行のメインで得るマヤ +2
            self::mod('integrated_operator', Hook::BudgetMainGain, PartId::BudgetGainMod, true, ['perFullSatellite' => 2]),
            // P012 ドッキング整備技師: この衛星への配備コスト -3（稼働中のみ）
            self::mod('docking_mechanic', Hook::DeployCost, PartId::DeployCostMod, true, ['delta' => -3, 'scope' => 'THIS_SATELLITE']),
            // P014〜P016: この衛星への CREW / DEVICE / SOFTWARE の配備コスト -3
            self::mod('crew_coordinator', Hook::DeployCost, PartId::DeployCostMod, false, ['delta' => -3, 'scope' => 'THIS_SATELLITE', 'type' => 'CREW']),
            self::mod('device_installer', Hook::DeployCost, PartId::DeployCostMod, false, ['delta' => -3, 'scope' => 'THIS_SATELLITE', 'type' => 'DEVICE']),
            self::mod('system_architect', Hook::DeployCost, PartId::DeployCostMod, false, ['delta' => -3, 'scope' => 'THIS_SATELLITE', 'type' => 'SOFTWARE']),
            // P017 主任機器士: この衛星への DEVICE の配備コスト -4（稼働中のみ）
            self::mod('chief_device_officer', Hook::DeployCost, PartId::DeployCostMod, true, ['delta' => -4, 'scope' => 'THIS_SATELLITE', 'type' => 'DEVICE']),
            // P018 スクワドロン補佐官: 自分の全ての衛星の配備コスト -1（稼働中のみ）
            self::mod('squadron_aide', Hook::DeployCost, PartId::DeployCostMod, true, ['delta' => -1, 'scope' => 'ALL_OWN']),
            // P026 フレンチブルドッグ: このカードを配備するとき、対象の衛星に付いているカード 1 枚につき配備コスト -2（自己の修正）
            new EffectSpec(
                'french_bulldog',
                Hook::DeployCost,
                EffectKind::OnDeploy,
                false,
                false,
                true,
                PartId::DeployCostMod,
                ['delta' => -2, 'scope' => 'SELF_CARD', 'perAttachedCard' => true],
                null,
                [],
                $cr,
            ),
            // P033 第七王子: この衛星にプロジェクトカードを配備できない
            self::mod('seventh_prince', Hook::Allowed, PartId::Forbid, false, ['action' => 'DEPLOY_TO_THIS']),

            // DEVICE
            // P040 射出補助装置: 自分の全ての衛星の打ち上げコスト -3（非稼働でも）
            self::mod('launch_assist_unit', Hook::LaunchCost, PartId::LaunchCostMod, false, ['delta' => -3, 'scope' => 'ALL_OWN']),
            // P046 固定軌道装置: この衛星は転回できない（反射はする）
            self::mod('fixed_orbit_unit', Hook::Allowed, PartId::Forbid, true, ['action' => 'TURN_THIS']),
            // P052 大陸アンテナ、P053 面舵アンテナ、P054 取舵アンテナ
            self::mod('continental_antenna', Hook::LinkTargets, PartId::LinkTargetExpand, true, ['shape' => 'ADJACENT_LAND']),
            self::mod('starboard_antenna', Hook::LinkTargets, PartId::LinkTargetExpand, true, ['shape' => 'RIGHT2']),
            self::mod('port_antenna', Hook::LinkTargets, PartId::LinkTargetExpand, true, ['shape' => 'LEFT2']),
            // P055 ステルス艦: 他プレイヤーへの先駆コストが 0
            self::mod('stealth_ship', Hook::LinkPioneer, PartId::PioneerWaiver, true),
            // P062 塵芥海洋アンテナ（地域 E・G）、P063 智利海洋アンテナ（地域 F・H）: 隣接する海区域。地域は衛星の直下（007 未確定 13）
            self::mod('dust_ocean_antenna', Hook::LinkTargets, PartId::LinkTargetExpand, true, ['shape' => 'ADJACENT_SEA'], [$region('E', 'G')]),
            self::mod('chile_ocean_antenna', Hook::LinkTargets, PartId::LinkTargetExpand, true, ['shape' => 'ADJACENT_SEA'], [$region('F', 'H')]),
            // P064 前方ビームアンテナ、P065 後方ビームアンテナ
            self::mod('forward_beam_antenna', Hook::LinkTargets, PartId::LinkTargetExpand, true, ['shape' => 'FRONT3']),
            self::mod('rear_beam_antenna', Hook::LinkTargets, PartId::LinkTargetExpand, true, ['shape' => 'BACK3']),
            // P066 電波遮断装置: この衛星は通信確立できない（効果の対象にも選べない）
            self::mod('signal_blocker', Hook::Allowed, PartId::Forbid, true, ['action' => 'LINK_THIS']),

            // SOFTWARE
            // P067 チュリュモフ: 企画のメイン・ボーナスで引く枚数 +1
            self::mod('churyumov', Hook::ProposalCounts, PartId::ProposalCountMod, true, ['mainDraw' => 1, 'bonusDraw' => 1]),
            // P071 チャンドラセカール: 企画のボーナスの引く枚数・残す枚数が各 +1
            self::mod('chandrasekhar', Hook::ProposalCounts, PartId::ProposalCountMod, true, ['bonusDraw' => 1, 'bonusKeep' => 1]),
            // P073 テスラメモリー: 企画で手札に残す枚数 +1
            self::mod('tesla_memory', Hook::ProposalCounts, PartId::ProposalCountMod, true, ['keep' => 1]),
            // P077 オライオンリワード: 売却額 +2
            self::mod('orion_reward', Hook::SellPrice, PartId::SellPriceMod, true, ['delta' => 2]),
            // P079 ゼログラビティα（地域 A・H）、P080 ゼログラビティβ（地域 B・E）: 揃った衛星は、燃料消費の時点で直下がその地域なら燃料を消費しない
            self::mod('zero_gravity_alpha', Hook::FuelConsume, PartId::FuelConsumeMod, true, [], [$full, $region('A', 'H')]),
            self::mod('zero_gravity_beta', Hook::FuelConsume, PartId::FuelConsumeMod, true, [], [$full, $region('B', 'E')]),
            // P085 ケスラー回避システム: ニアミスの燃料消費が 1
            self::mod('kessler_avoidance_system', Hook::NearMiss, PartId::NearMissFuelMod, true, ['value' => 1]),
            // P094 デコンフリクト: CREW・DEVICE・SOFTWARE が揃った衛星はニアミスしない
            self::mod('deconflict', Hook::NearMiss, PartId::NearMissSuppress, true, [], [$full]),

            // 衛星カード（色別プラン面。効果はすべて ACTIVE_ONLY）
            // R006 HAMMER・PHOBOS: 自分の全ての衛星の打ち上げコスト -3（自身の打ち上げには効かない。FAQ 行 67）
            self::mod('hammer_phobos', Hook::LaunchCost, PartId::LaunchCostMod, true, ['delta' => -3, 'scope' => 'ALL_OWN']),
            // R007 RETRO・PHOBOS: 自分の全ての衛星の配備コスト -1
            self::mod('retro_phobos', Hook::DeployCost, PartId::DeployCostMod, true, ['delta' => -1, 'scope' => 'ALL_OWN']),
            // R008 SCALLOP・PHOBOS: 自分の全ての衛星の通信確立コスト -1
            self::mod('scallop_phobos', Hook::LinkCost, PartId::LinkCostMod, true, ['delta' => -1, 'scope' => 'ALL_OWN']),
            // R009 CONCORDE・PHOBOS: 売却額 +2
            self::mod('concorde_phobos', Hook::SellPrice, PartId::SellPriceMod, true, ['delta' => 2]),
            // R011 HAMMER・DEIMOS: 企画のボーナスが「6 枚引いて 3 枚残す」。自分が企画を選んで得たボーナスのときだけ（K12。FAQ 行 71）
            self::mod(
                'hammer_deimos',
                Hook::ProposalCounts,
                PartId::ProposalCountMod,
                true,
                ['bonusBase' => ['draw' => 6, 'keep' => 3]],
                [new EffectCondition(ConditionId::OwnActionChoice)],
                EffectKind::Activated,
            ),
            // R014 CONCORDE・DEIMOS: 通信確立のボーナスで、直下に隣接する 6 区域すべてを対象に加える（FAQ 行 72）
            self::mod(
                'concorde_deimos',
                Hook::LinkTargets,
                PartId::LinkTargetExpand,
                true,
                ['shape' => 'ALL_ADJACENT6', 'bonusOnly' => true],
                [],
                EffectKind::Activated,
            ),
        ];
    }

    /**
     * 選択のない常時の修正・禁止 1 件（資源なし）。
     *
     * @param array<string, mixed> $params
     * @param list<EffectCondition> $conditions
     */
    private static function mod(
        string $key,
        Hook $hook,
        PartId $part,
        bool $activeOnly,
        array $params = [],
        array $conditions = [],
        EffectKind $kind = EffectKind::Constant,
    ): EffectSpec {
        return new EffectSpec($key, $hook, $kind, $activeOnly, false, false, $part, $params, null, $conditions, []);
    }
}
