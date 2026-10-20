<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * E-5（配備。011 §8）の効果の定義: 手札からの配備（R09）、山札からの配備（R10）、付け替え（R12）。`touches` は 007 §10 の付表の「資源」列。
 *
 * E-5 の 6 種のうち、P011（双配備技師）と P035（ギャンブルシステム）は E-0 の代表として `CardEffects::build()` に既にあるので、
 * ここには含めない（残りの 4 種をここで持つ）。
 *
 * - 追加配備コスト（稼働中の衛星への +5）は、P011・P045・P074（「配備コストを支払って」。FAQ 行 20）に必要で、R005（FAQ 行 69）と
 *   P035（「配備コストを支払わずに」）には不要。
 * - 配備は `DEPLOYED` を起こして連鎖する。P022 の付け替えは配備ではないので何も起こさない（FAQ 行 9）。
 */
final class CardEffectsE5
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
        $hand = ResourceSymbol::HandChoose;
        $comp = ResourceSymbol::Composition;
        $host = ResourceScope::HostSatellite;
        $all = ResourceScope::AllSatellites;

        return [
            // P022 ジャグラー: 配備したとき、自分の待機中の衛星のプロジェクトカードを全て好きなように付け替える（強制。配備時の効果は発動しない）
            new EffectSpec(
                'juggler',
                Hook::Deployed,
                EffectKind::OnDeploy,
                false,
                false,
                true,
                PartId::RearrangeStandby,
                [],
                null,
                [],
                [new ResourceTouch($comp, $all)],
            ),

            // P045 軌道整備ドッグ: この衛星が転回したとき、配備コスト（稼働中の追加配備コストを含む）を支払って、この衛星に 1 枚配備してもよい
            new EffectSpec(
                'orbital_repair_dock',
                Hook::Turned,
                EffectKind::Activated,
                true,
                true,
                false,
                PartId::DeployFromHand,
                ['targetScope' => 'THIS', 'surcharge' => true],
                null,
                [new EffectCondition(ConditionId::EventSatellite, ['scope' => 'THIS'])],
                [new ResourceTouch($pay), new ResourceTouch($hand), new ResourceTouch($comp, $host)],
            ),

            // P074 ゼロデイデプロイ: 企画フェイズで 1 枚捨てたら、1 枚選んで配備コスト（追加配備コストを含む）を支払って自分の好きな衛星に配備してもよい
            new EffectSpec(
                'zero_day_deploy',
                Hook::ProposalAfter,
                EffectKind::Activated,
                true,
                true,
                false,
                PartId::DeployFromHand,
                ['targetScope' => 'ANY_OWN', 'surcharge' => true],
                EffectCost::discard(1),
                [],
                [new ResourceTouch($pay), new ResourceTouch($hand), new ResourceTouch($comp, $all)],
            ),

            // R005 STEALTH（MOON）: 打ち上げ時、手札から 2 枚まで配備コストを支払ってこの衛星に配備してもよい（追加配備コスト不要。FAQ 行 69）。
            // 配備したカードの配備時・打ち上げ時効果は発動する
            new EffectSpec(
                'stealth_moon',
                Hook::Launched,
                EffectKind::OnLaunch,
                false,
                true,
                true,
                PartId::DeployFromHand,
                ['targetScope' => 'THIS', 'surcharge' => false, 'maxCards' => 2, 'fireLaunch' => true],
                null,
                [],
                [new ResourceTouch($pay), new ResourceTouch($hand), new ResourceTouch($comp, $host)],
            ),
        ];
    }
}
