<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Board\AreaType;

/**
 * 通信確立を全員分まとめて一斉に反映する（007 §7-3、001 U-11・§7-5）。メイン・ボーナス・窓のステージで共通。
 *
 * 1. 支払い: 全員分を、処理前の所持金（と売却）で判定して払う。先駆コストは払う側が先に払い、受け取りは後回し（予約）。
 *    払えない依頼は取りやめる（売却もしない）。
 * 2. 配置: 同じ区域に同時に置くキューブは同じスロットに重ねる（LinkRepository::commit）。同時の通信確立は先駆コストを払い合わない
 *    （依頼のコストは処理前の盤面で作るため、同じコミットの相手は含まれない）。
 * 3. 先駆コストの受け取り: 全員の支払いと配置が済んでから受け取る。受け取ったマヤはこのコミットの支払いには使えない。
 * 4. 区域ボーナス: スロットの順位に従う。深海 = 1 番目に燃料 2 回復・2 番目に 1 回復、森林 = 1 番目に +5 マヤ、
 *    山岳 = 1 番目にカードを 1 枚引く、雪原 = 全員 +1 マヤ。席順に処理する。
 */
final class LinkCommitter
{
    public function __construct(private readonly ActionServices $services)
    {
    }

    /**
     * @param list<LinkRequest> $requests 同じプレイヤー・同じ区域の依頼を含めない
     */
    public function commit(array $requests): LinkCommit
    {
        $ordered = [];
        foreach ($this->services->seatOrder(array_map(static fn(LinkRequest $r): int => $r->playerId, $requests)) as $playerId) {
            foreach ($requests as $request) {
                if ($request->playerId === $playerId) {
                    $ordered[] = $request;
                }
            }
        }

        $paid = [];
        $payments = [];
        $failed = [];
        $credits = [];
        foreach ($ordered as $request) {
            $payment = $this->services->selling->tryPay($request->playerId, $request->cost->total(), $request->sell);
            if ($payment === null) {
                $failed[] = $request;
                continue;
            }
            $paid[] = $request;
            $payments[] = $payment;
            foreach ($request->cost->pioneer as $share) {
                $credits[$share['to']] = ($credits[$share['to']] ?? 0) + $share['amount'];
            }
        }
        if ($paid === []) {
            return new LinkCommit(null, [], $failed, []);
        }

        $commitNo = $this->services->globals->incCommitSeq();
        $links = $this->services->links->commit(
            array_map(static fn(LinkRequest $r): array => ['playerId' => $r->playerId, 'cell' => $r->cell], $paid),
            $this->services->globals->roundNo(),
            $commitNo,
        );

        $received = [];
        foreach ($this->services->seatOrder(array_keys($credits)) as $playerId) {
            $this->services->players->addMaya($playerId, $credits[$playerId]);
            $received[$playerId] = $credits[$playerId];
        }

        $entries = [];
        foreach ($paid as $i => $request) {
            $entries[] = new LinkCommitEntry($request, $payments[$i], $links[$i], $this->grantAreaBonus($request, $links[$i]->slot));
        }
        return new LinkCommit($commitNo, $entries, $failed, $received);
    }

    private function grantAreaBonus(LinkRequest $request, int $slot): AreaBonus
    {
        $type = $this->services->layout->areaType($request->cell);
        $first = $slot === 0;
        $maya = 0;
        $drawn = [];
        $fuel = 0;
        switch ($type) {
            case AreaType::FOREST:
                $maya = $first ? 5 : 0;
                break;
            case AreaType::SNOW:
                $maya = 1;
                break;
            case AreaType::MOUNTAIN:
                $drawn = $first ? $this->services->cards->drawTop($request->playerId, 1) : [];
                break;
            case AreaType::DEEP_SEA:
                $fuel = $this->recoverFuel($request->satelliteId, match ($slot) {
                    0 => 2,
                    1 => 1,
                    default => 0,
                });
                break;
            default:
                break;
        }
        if ($maya > 0) {
            $this->services->players->addMaya($request->playerId, $maya);
        }
        return new AreaBonus($maya, $drawn, $fuel);
    }

    /**
     * 燃料の回復（001 §4-4）。初期燃料庫より下には動かず、燃料切れ（消費 > 容量）の衛星は回復で動かせない。
     *
     * @return int 実際に回復した量
     */
    private function recoverFuel(int $satId, int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }
        $sat = $this->services->satellites->get($satId);
        $capacity = $this->services->fuelCapacity($satId);
        if (!$sat->isActive() || $sat->fuelUsed > $capacity) {
            return 0;
        }
        $after = $this->services->satellites->addFuelUsed($satId, -$amount, $capacity);
        return $sat->fuelUsed - $after;
    }
}
