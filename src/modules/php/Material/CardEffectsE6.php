<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * E-6（通信確立。011 §8）の効果の定義: 効果による通信確立（R11 `ESTABLISH_LINK`）14 種。`touches` は 007 §10 の付表の「資源」列。
 *
 * すべて起動（`ACTIVATED`）・稼働中のみ・任意（[Use] / [Skip]）で、通信確立をステージに積む（コミットは状態 32 のバリア。007 §7-3）。
 * パラメータ（`EstablishLinkPart` が読む）:
 * - `costKind`: `SPECIAL`（特殊通信確立コスト = 通信確立コスト + 5。001 §11）/ `NORMAL`（P076 だけ。特殊コストなし。FAQ 行 30）。
 * - `actor`: 通信確立する衛星。`THIS`（このカードのある衛星）/ `OTHER_OWN`（自分の他の稼働中の衛星を選ぶ。居場所は問わない。FAQ 行 21）/
 *   `CHOSEN_OWN`（自分の稼働中の衛星を選ぶ。P076）。
 * - `area`: 対象区域の基準。`BELOW`（衛星の直下）/ `AHEAD`・`BEHIND`（進行方向の前方・後方の隣接区域。反射を考慮しない。FAQ 行 26・27）/
 *   `ADJACENT_LAND_OF_ACTOR`（衛星の直下の区域に隣接する陸区域。P060・P061 は 011 未確定 1 の回答、P076 は印刷のとおり）。
 *   通信確立の対象区域を広げる効果（M04。P052 など）は、どの基準にも常に足される（FAQ 行 23・24・25）。
 * - 発動の条件（K01・K04・K07・K08）は `conditions`。
 */
final class CardEffectsE6
{
    private function __construct()
    {
    }

    /**
     * @return list<EffectSpec>
     */
    public static function specs(): array
    {
        $this_ = new EffectCondition(ConditionId::EventSatellite, ['scope' => 'THIS']);
        $other = new EffectCondition(ConditionId::EventSatellite, ['scope' => 'OTHER_OWN']);
        $region = static fn(array $regions): EffectCondition => new EffectCondition(ConditionId::LinkAreaRegion, ['regions' => $regions]);
        $entered = static fn(array $regions): EffectCondition => new EffectCondition(ConditionId::RegionChanged, $regions === [] ? [] : ['regions' => $regions]);

        $special = ['costKind' => 'SPECIAL', 'actor' => 'THIS', 'area' => 'BELOW'];
        $relay = ['costKind' => 'SPECIAL', 'actor' => 'OTHER_OWN', 'area' => 'BELOW'];

        return [
            // P047 トルネードビット: この衛星が転回したとき、特殊通信確立コストを支払って、この衛星の直下の区域を通信確立してもよい
            self::link('tornado_bit', Hook::Turned, $special, [$this_]),

            // P050・P051 中継ネットワーク: この衛星が地域 [A][C][E] / [B][D][F] の区域を通信確立したとき（通信確立した区域の地域。FAQ 行 22）、
            // 特殊通信確立コストを支払って、自分の他の衛星（居場所は問わない。FAQ 行 21）の直下の区域を通信確立してもよい
            self::link('euro_africa_relay_network', Hook::Linked, $relay, [$this_, $region(['A', 'C', 'E'])]),
            self::link('americas_asia_relay_network', Hook::Linked, $relay, [$this_, $region(['B', 'D', 'F'])]),

            // P056・P057 広域送信機: この衛星が通信確立したとき、特殊通信確立コストを支払って、進行方向の前方 / 後方の隣接する区域を通信確立してもよい
            self::link('forward_wide_area_transmitter', Hook::Linked, ['costKind' => 'SPECIAL', 'actor' => 'THIS', 'area' => 'AHEAD'], [$this_]),
            self::link('rear_wide_area_transmitter', Hook::Linked, ['costKind' => 'SPECIAL', 'actor' => 'THIS', 'area' => 'BEHIND'], [$this_]),

            // P058・P059 遠隔通信装置: 自分の他の衛星が地域 [C][E] / [D][F] の区域を通信確立したとき、特殊通信確立コストを支払って、この衛星の直下の区域を通信確立してもよい
            self::link('crimson_remote_comm_unit', Hook::Linked, $special, [$other, $region(['C', 'E'])]),
            self::link('lapis_remote_comm_unit', Hook::Linked, $special, [$other, $region(['D', 'F'])]),

            // P060・P061 波形転送装置: この衛星が地域 [A][G] / [B][H] の区域を通信確立したとき、特殊通信確立コストを支払って、
            // この衛星の直下の区域に隣接する陸区域を 1 つ選んで通信確立してもよい（011 未確定 1）
            self::link('north_sea_waveform_relay', Hook::Linked, ['costKind' => 'SPECIAL', 'actor' => 'THIS', 'area' => 'ADJACENT_LAND_OF_ACTOR'], [$this_, $region(['A', 'G'])]),
            self::link('south_russia_waveform_relay', Hook::Linked, ['costKind' => 'SPECIAL', 'actor' => 'THIS', 'area' => 'ADJACENT_LAND_OF_ACTOR'], [$this_, $region(['B', 'H'])]),

            // P076 グラウンドリンク: 企画フェイズで、手札を 2 枚捨てて、自分の衛星 1 機に隣接する陸区域を 1 つ通信確立コスト（特殊コストなし。FAQ 行 30）を支払って通信確立してもよい
            new EffectSpec(
                'ground_link',
                Hook::ProposalAfter,
                EffectKind::Activated,
                true,
                true,
                false,
                PartId::EstablishLink,
                ['costKind' => 'NORMAL', 'actor' => 'CHOSEN_OWN', 'area' => 'ADJACENT_LAND_OF_ACTOR'],
                EffectCost::discard(2),
                [],
                [new ResourceTouch(ResourceSymbol::HandChoose), new ResourceTouch(ResourceSymbol::MayaPay), new ResourceTouch(ResourceSymbol::Link)],
            ),

            // P096・P097 アルファ / ベータリンク: 別の地域から地域 [A][C][F] / [B][D][G] の上空に突入したとき、特殊通信確立コストを支払って、この衛星の直下の区域を通信確立してもよい
            self::link('alpha_link', Hook::OrbitEntered, $special, [$this_, $entered(['A', 'C', 'F'])]),
            self::link('beta_link', Hook::OrbitEntered, $special, [$this_, $entered(['B', 'D', 'G'])]),

            // P098 バックリンク: 自分の通信確立済みの区域の上空に突入したとき、特殊通信確立コストを支払って、進行方向後方の隣接する区域を通信確立してもよい
            // （タイミングの印刷は誤りで、循環フェイズ中が正。FAQ 行 63）
            self::link('back_link', Hook::OrbitEntered, ['costKind' => 'SPECIAL', 'actor' => 'THIS', 'area' => 'BEHIND'], [$this_, new EffectCondition(ConditionId::EnteredOwnLinkedArea)]),

            // P099 フライバイリンク: 地域間を移動したとき（別の地域から突入したとき）、特殊通信確立コストを支払って、この衛星の直下の区域を通信確立してもよい
            self::link('flyby_link', Hook::OrbitEntered, $special, [$this_, $entered([])]),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @param list<EffectCondition> $conditions
     */
    private static function link(string $cardKey, Hook $hook, array $params, array $conditions): EffectSpec
    {
        return new EffectSpec(
            $cardKey,
            $hook,
            EffectKind::Activated,
            true,
            true,
            false,
            PartId::EstablishLink,
            $params,
            null,
            $conditions,
            [new ResourceTouch(ResourceSymbol::MayaPay), new ResourceTouch(ResourceSymbol::Link)],
        );
    }
}
