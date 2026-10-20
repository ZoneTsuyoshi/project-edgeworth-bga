<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * E-2（強制の反応効果。011 §8）の効果の定義。強制・選択なし・1 段の反応効果で、計画 / 予算 / 配備 / 打ち上げ / 転回 / 通信確立 /
 * 循環 / 企画の窓フックと、`ACTION_BONUS_STEP`・`PROPOSAL_DRAWN` に結ぶ（011 §7 の表、`touches` は 007 §10 の付表の「資源」列）。
 *
 * E-2 の 36 種のうち、P013・P023・P039・R010 の 4 種は E-0 の代表 10 枚として `CardEffects::build()` に既にあり、ここには含めない
 * （残りの 32 種をここで持つ）。条件の部品は `Effects/Condition/ConditionsE2`、結果部品は `Effects/Parts/PartsE2` が実装する。
 */
final class CardEffectsE2
{
    private function __construct()
    {
    }

    /**
     * @return list<EffectSpec>
     */
    public static function build(): array
    {
        $m = ResourceSymbol::MayaGain;
        $b = ResourceSymbol::BudgetGain;
        $h = ResourceSymbol::HandDraw;
        $host = ResourceScope::HostSatellite;

        $this_ = new EffectCondition(ConditionId::EventSatellite, ['scope' => 'THIS']);
        $any = new EffectCondition(ConditionId::EventSatellite, ['scope' => 'ANY_OWN']);
        $type = static fn(string $t): EffectCondition => new EffectCondition(ConditionId::EventCardType, ['type' => $t]);

        return [
            // ---- CREW ----
            // P001 航空技師 / P002 航空指揮官: 計画フェイズの回収で、縦向きの II・III / IV・V を手札に戻す
            self::spec('P001', Hook::PlanningRetrieve, EffectKind::Activated, true, false, PartId::KeepPlottedActions, ['actions' => [2, 3]]),
            self::spec('P002', Hook::PlanningRetrieve, EffectKind::Activated, true, false, PartId::KeepPlottedActions, ['actions' => [4, 5]]),
            // P003 ロンサムラビット: アクション選択が他の誰とも被らなければ +2 マヤ
            self::spec('P003', Hook::PlanningReveal, EffectKind::Activated, true, false, PartId::GainMaya, ['amount' => 2], [new EffectCondition(ConditionId::NoActionOverlap)], [new ResourceTouch($m)]),
            // P007 折衝術士: アクション選択ボーナスを獲得するとき +2 マヤ
            self::spec('P007', Hook::BudgetBonusGain, EffectKind::Activated, true, false, PartId::GainMaya, ['amount' => 2], [new EffectCondition(ConditionId::IsBonusHolder)], [new ResourceTouch($m)]),
            // P009 ファーストペンギン: 配備されたとき 1 枚引く（自身の配備にだけ反応）
            self::spec('P009', Hook::Deployed, EffectKind::OnDeploy, false, true, PartId::DrawCards, ['count' => 1], [], [new ResourceTouch($h)]),
            // P025 先駆的ゴリラ: この衛星に SOFTWARE を配備したとき 2 枚引く
            self::spec('P025', Hook::Deployed, EffectKind::Activated, true, false, PartId::DrawCards, ['count' => 2], [$this_, $type('SOFTWARE')], [new ResourceTouch($h)]),
            // P027〜P029 プランナー: 自分のいずれかの衛星に CREW / DEVICE / SOFTWARE を配備したとき 1 枚引く
            self::spec('P027', Hook::Deployed, EffectKind::Activated, true, false, PartId::DrawCards, ['count' => 1], [$any, $type('CREW')], [new ResourceTouch($h)]),
            self::spec('P028', Hook::Deployed, EffectKind::Activated, true, false, PartId::DrawCards, ['count' => 1], [$any, $type('DEVICE')], [new ResourceTouch($h)]),
            self::spec('P029', Hook::Deployed, EffectKind::Activated, true, false, PartId::DrawCards, ['count' => 1], [$any, $type('SOFTWARE')], [new ResourceTouch($h)]),
            // P030〜P032 投資官: 同じく、予算 +1
            self::spec('P030', Hook::Deployed, EffectKind::Activated, true, false, PartId::ChangeBudget, ['amount' => 1], [$any, $type('CREW')], [new ResourceTouch($b)]),
            self::spec('P031', Hook::Deployed, EffectKind::Activated, true, false, PartId::ChangeBudget, ['amount' => 1], [$any, $type('DEVICE')], [new ResourceTouch($b)]),
            self::spec('P032', Hook::Deployed, EffectKind::Activated, true, false, PartId::ChangeBudget, ['amount' => 1], [$any, $type('SOFTWARE')], [new ResourceTouch($b)]),

            // ---- DEVICE ----
            // P037 姿勢制御装置: この衛星が転回したとき 1 枚引く
            self::spec('P037', Hook::Turned, EffectKind::Activated, true, false, PartId::DrawCards, ['count' => 1], [$this_], [new ResourceTouch($h)]),
            // P041 打ち上げ契約装置: 自分の衛星を打ち上げたとき予算 +1（稼働条件なし）
            self::spec('P041', Hook::Launched, EffectKind::Activated, false, false, PartId::ChangeBudget, ['amount' => 1], [$any], [new ResourceTouch($b)]),
            // P042 ダンスドッグロボット: 打ち上げ時、この衛星の DEVICE の枚数 1 枚につき予算 +1
            self::spec('P042', Hook::Launched, EffectKind::OnLaunch, false, true, PartId::ChangeBudget, ['amount' => 1, 'perDevice' => true], [], [new ResourceTouch($b), new ResourceTouch(ResourceSymbol::CompositionRead, $host)]),
            // P043 回生推進機: この衛星が転回したとき燃料 1 回復
            self::spec('P043', Hook::Turned, EffectKind::Activated, true, false, PartId::RecoverFuel, ['amount' => 1, 'target' => 'THIS'], [$this_], [new ResourceTouch(ResourceSymbol::FuelRecover, $host)]),
            // P049 太陽電池パネル: この衛星が通信確立するたび燃料 1 回復（常時）
            self::spec('P049', Hook::Linked, EffectKind::Constant, true, false, PartId::RecoverFuel, ['amount' => 1, 'target' => 'THIS'], [$this_], [new ResourceTouch(ResourceSymbol::FuelRecover, $host)]),

            // ---- SOFTWARE ----
            // P075 ファラデー: 企画フェイズでカードを引いたとき、引いた枚数 1 枚につき +1 マヤ（常時）
            self::spec('P075', Hook::ProposalDrawn, EffectKind::Constant, true, false, PartId::GainMaya, ['perCardDrawn' => 1], [], [new ResourceTouch($m)]),
            // P078 アポロダウン: 自分の衛星が着陸 / 着水したとき 1 枚引く
            self::spec('P078', Hook::Landed, EffectKind::Activated, true, false, PartId::DrawCards, ['count' => 1], [$any], [new ResourceTouch($h)]),
            // P083・P084 リフィル: 別の地域の指定の地域に突入したとき燃料 2 回復
            self::spec('P083', Hook::OrbitEntered, EffectKind::Activated, true, false, PartId::RecoverFuel, ['amount' => 2, 'target' => 'THIS'], [$this_, new EffectCondition(ConditionId::RegionChanged, ['regions' => ['A', 'C', 'H']])], [new ResourceTouch(ResourceSymbol::FuelRecover, $host)]),
            self::spec('P084', Hook::OrbitEntered, EffectKind::Activated, true, false, PartId::RecoverFuel, ['amount' => 2, 'target' => 'THIS'], [$this_, new EffectCondition(ConditionId::RegionChanged, ['regions' => ['D', 'F', 'H']])], [new ResourceTouch(ResourceSymbol::FuelRecover, $host)]),
            // P088 ハイゲンスダウン: 自分の衛星が着陸 / 着水したとき +8 マヤ
            self::spec('P088', Hook::Landed, EffectKind::Activated, true, false, PartId::GainMaya, ['amount' => 8], [$any], [new ResourceTouch($m)]),
            // P089 ランディングダウン: 陸区域に着陸したとき +15 マヤ / P092 スプラッシュダウン: 海区域に着水したとき +10 マヤ
            self::spec('P089', Hook::Landed, EffectKind::Activated, true, false, PartId::GainMaya, ['amount' => 15], [$any, new EffectCondition(ConditionId::LandedAreaKind, ['kind' => 'LAND'])], [new ResourceTouch($m)]),
            self::spec('P092', Hook::Landed, EffectKind::Activated, true, false, PartId::GainMaya, ['amount' => 10], [$any, new EffectCondition(ConditionId::LandedAreaKind, ['kind' => 'SEA'])], [new ResourceTouch($m)]),
            // P093 オービタル72: 自分の通信確立済みの区域に突入したとき +3 マヤ。印刷・002 は「起動」（011 §7・007 §10 の「常時」は誤り。各ラウンド 1 回）
            self::spec('P093', Hook::OrbitEntered, EffectKind::Activated, true, false, PartId::GainMaya, ['amount' => 3], [$this_, new EffectCondition(ConditionId::EnteredOwnLinkedArea)], [new ResourceTouch($m)]),

            // ---- 衛星カード ----
            // R001〜R004 MOON: 打ち上げ時（再打ち上げでも）、1 枚引く / 予算 +2 / 2 枚引く / 予算 +4
            self::spec('hammer_moon', Hook::Launched, EffectKind::OnLaunch, false, true, PartId::DrawCards, ['count' => 1], [], [new ResourceTouch($h)]),
            self::spec('retro_moon', Hook::Launched, EffectKind::OnLaunch, false, true, PartId::ChangeBudget, ['amount' => 2], [], [new ResourceTouch($b)]),
            self::spec('scallop_moon', Hook::Launched, EffectKind::OnLaunch, false, true, PartId::DrawCards, ['count' => 2], [], [new ResourceTouch($h)]),
            self::spec('concorde_moon', Hook::Launched, EffectKind::OnLaunch, false, true, PartId::ChangeBudget, ['amount' => 4], [], [new ResourceTouch($b)]),
            // R012 RETRO DEIMOS: III（打ち上げ / 転回）のボーナスのコミットで予算 +1（007 未確定 12、011 未確定 4）
            self::spec('retro_deimos', Hook::ActionBonusStep, EffectKind::Activated, true, false, PartId::ChangeBudget, ['amount' => 1, 'action' => 'LAUNCH_TURN'], [], [new ResourceTouch($b)]),
            // R013 SCALLOP DEIMOS: II（配備）のボーナスのコミットで 1 枚引く
            self::spec('scallop_deimos', Hook::ActionBonusStep, EffectKind::Activated, true, false, PartId::DrawCards, ['count' => 1, 'action' => 'DEPLOY'], [], [new ResourceTouch($h)]),
            // R015 STEALTH DEIMOS: 予算発行のボーナスで得るとき +3 マヤ
            self::spec('stealth_deimos', Hook::BudgetBonusGain, EffectKind::Activated, true, false, PartId::GainMaya, ['amount' => 3], [new EffectCondition(ConditionId::IsBonusHolder)], [new ResourceTouch($m)]),
        ];
    }

    /**
     * 強制（`optional` 偽）・支払いなし・1 段の効果を組み立てる。`$id` は `P001` 形式のプロジェクトカード ID か、衛星カードの `name_id`。
     *
     * @param array<string, mixed> $params
     * @param list<EffectCondition> $conditions
     * @param list<ResourceTouch> $touches
     */
    private static function spec(
        string $id,
        Hook $hook,
        EffectKind $kind,
        bool $activeOnly,
        bool $selfOnly,
        PartId $part,
        array $params,
        array $conditions = [],
        array $touches = [],
    ): EffectSpec {
        $key = preg_match('/^P\d{3}$/', $id) === 1 ? ProjectCards::get($id)->nameId : $id;

        return new EffectSpec($key, $hook, $kind, $activeOnly, false, $selfOnly, $part, $params, null, $conditions, $touches);
    }
}
