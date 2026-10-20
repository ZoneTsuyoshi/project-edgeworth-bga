<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Stats;

use Bga\Games\projectedgeworth\Flow\Machine\FinalScoreOutcome;
use Bga\Games\projectedgeworth\Flow\Machine\Notice;

/**
 * 統計の更新規則（仕様書 010 §14）。BGA に依存しない。送る通知の内容から更新を導き、StatStore に書く。
 * 統計は通知を送る側の処理から導くので、各フェイズのクラスは統計を意識しない（Game::peSend が呼ぶ）。
 * 名声の統計は最終得点（121）の結果（FinalScoreOutcome）から書き、「Final situation」の内訳と同じ値になる。
 */
final class StatsRecorder
{
    /** end_reason（value_labels の添字） */
    public const END_REASON_NONE = 0;
    public const END_REASON_SATELLITES = 1;
    public const END_REASON_LINKS = 2;
    public const END_REASON_BOTH = 3;

    /** 名声の区分（FameCalculator の detail のキー）=> 統計のキー */
    public const FAME_SECTIONS = [
        'I' => 'fame_budget',
        'II' => 'fame_maya',
        'III' => 'fame_links',
        'IV' => 'fame_regions',
        'V' => 'fame_missions',
        'VI' => 'fame_satellites',
        'VII' => 'fame_projects',
        'VIII' => 'fame_rewards',
        'IX' => 'fame_end',
    ];

    private const TABLE_INT = ['rounds_played', 'end_reason', 'links_total', 'near_misses'];

    private const PLAYER_INT = [
        'fame_budget', 'fame_maya', 'fame_links', 'fame_regions', 'fame_missions', 'fame_satellites', 'fame_projects',
        'fame_rewards', 'fame_end', 'fame_total',
        'links_count', 'links_abandoned', 'special_links', 'pioneer_paid', 'pioneer_received',
        'cards_deployed', 'cards_sold', 'cards_discarded', 'satellites_launched', 'satellites_turned',
        'landings', 'splashdowns', 'fuel_used', 'fuel_recovered', 'near_miss_count', 'maya_earned', 'maya_spent',
        'budget_final', 'actions_chosen_1', 'actions_chosen_2', 'actions_chosen_3', 'actions_chosen_4', 'actions_chosen_5',
        'bonus_gained', 'effects_used', 'effects_skipped', 'active_satellites_final', 'cubes_left_final',
    ];

    public function __construct(private readonly StatStore $store)
    {
    }

    /**
     * 全ての統計を初期化する（setupNewGame）。
     */
    public function init(): void
    {
        foreach (self::TABLE_INT as $name) {
            $this->store->tableInit($name, 0);
        }
        foreach (self::PLAYER_INT as $name) {
            $this->store->playerInit($name, 0);
        }
    }

    /**
     * 送る通知から統計を更新する。全員宛て（to が null）の通知だけを数える
     * （本人宛ての先送り・手札の通知は、全員宛ての通知と二重になるため）。
     *
     * @param list<Notice> $notices
     */
    public function recordNotices(array $notices): void
    {
        foreach ($notices as $n) {
            if ($n->to === null) {
                $this->recordNotice($n);
            }
        }
    }

    /**
     * 最終得点（121）。名声の内訳と合計、最終の予算・稼働中の衛星・残りのキューブ。
     */
    public function recordFinal(FinalScoreOutcome $outcome): void
    {
        foreach ($outcome->details as $playerId => $detail) {
            foreach (self::FAME_SECTIONS as $section => $stat) {
                $this->store->playerSet($stat, $playerId, self::points($detail, $section));
            }
            $this->store->playerSet('fame_total', $playerId, $outcome->scores[$playerId] ?? 0);
            /** @var array{budget?: int} $budget */
            $budget = (array) ($detail['I'] ?? []);
            $this->store->playerSet('budget_final', $playerId, (int) ($budget['budget'] ?? 0));
            /** @var array{sats?: list<mixed>} $sats */
            $sats = (array) ($detail['VI'] ?? []);
            $this->store->playerSet('active_satellites_final', $playerId, count($sats['sats'] ?? []));
            $this->store->playerSet('cubes_left_final', $playerId, $outcome->cubesLeft[$playerId] ?? 0);
        }
    }

    /**
     * @param array<string, mixed> $detail
     */
    private static function points(array $detail, string $section): int
    {
        /** @var array{points?: int} $row */
        $row = (array) ($detail[$section] ?? []);
        return (int) ($row['points'] ?? 0);
    }

    private function recordNotice(Notice $n): void
    {
        $a = $n->args;
        $playerId = (int) ($a['player_id'] ?? 0);
        switch ($n->name) {
            case 'roundStarted':
                $this->store->tableSet('rounds_played', (int) ($a['round_no'] ?? 0));
                break;
            case 'actionRevealed':
                $actionNo = (int) ($a['action_no'] ?? 0);
                if ($actionNo >= 1 && $actionNo <= 5) {
                    $this->store->playerInc('actions_chosen_' . $actionNo, $playerId, 1);
                }
                break;
            case 'phaseStarted':
                foreach ((array) ($a['holders'] ?? []) as $holder) {
                    $this->store->playerInc('bonus_gained', (int) $holder, 1);
                }
                break;
            case 'cardsDiscarded':
                $count = (int) ($a['count'] ?? 0);
                if (($a['reason'] ?? '') === 'sell') {
                    $this->store->playerInc('cards_sold', $playerId, $count);
                } elseif (($a['reason'] ?? '') === 'proposal') {
                    $this->store->playerInc('cards_discarded', $playerId, $count);
                }
                break;
            case 'mayaChanged':
                $delta = (int) ($a['delta'] ?? 0);
                $this->store->playerInc($delta > 0 ? 'maya_earned' : 'maya_spent', $playerId, abs($delta));
                break;
            case 'mayaTransferred':
                $amount = (int) ($a['amount'] ?? 0);
                $from = (int) ($a['from_player_id'] ?? 0);
                $to = (int) ($a['to_player_id'] ?? 0);
                $this->store->playerInc('pioneer_paid', $from, $amount);
                $this->store->playerInc('maya_spent', $from, $amount);
                $this->store->playerInc('pioneer_received', $to, $amount);
                $this->store->playerInc('maya_earned', $to, $amount);
                break;
            case 'cardDeployed':
                $this->store->playerInc('cards_deployed', $playerId, 1);
                break;
            case 'satelliteLaunched':
                $this->store->playerInc('satellites_launched', $playerId, 1);
                break;
            case 'satelliteTurned':
                $this->store->playerInc('satellites_turned', $playerId, 1);
                break;
            case 'fuelChanged':
                $delta = (int) ($a['delta'] ?? 0);
                $this->store->playerInc($delta > 0 ? 'fuel_used' : 'fuel_recovered', $playerId, abs($delta));
                break;
            case 'nearMiss':
                $this->store->tableInc('near_misses', 1);
                foreach ((array) ($a['players'] ?? []) as $involved) {
                    $this->store->playerInc('near_miss_count', (int) $involved, 1);
                }
                break;
            case 'satelliteLanded':
                $this->store->playerInc(($a['sea'] ?? false) === true ? 'splashdowns' : 'landings', $playerId, 1);
                break;
            case 'linkEstablished':
                $this->store->tableInc('links_total', 1);
                $this->store->playerInc('links_count', $playerId, 1);
                if (($a['special'] ?? false) === true) {
                    $this->store->playerInc('special_links', $playerId, 1);
                }
                break;
            case 'actionPassed':
                // 通信確立のキューブの放棄（自動で見送った場合は数えない）
                if (($a['kind'] ?? '') === 'link' && ($a['auto'] ?? false) === false) {
                    $this->store->playerInc('links_abandoned', $playerId, 1);
                }
                break;
            case 'effectUsed':
                $this->store->playerInc('effects_used', $playerId, 1);
                break;
            case 'effectSkipped':
                $this->store->playerInc('effects_skipped', $playerId, 1);
                break;
            case 'gameEndTriggered':
                $reasons = (array) ($a['reasons'] ?? []);
                $this->store->tableSet('end_reason', self::endReason(
                    in_array('satellites', $reasons, true),
                    in_array('links', $reasons, true),
                ));
                break;
            default:
                break;
        }
    }

    public static function endReason(bool $satellites, bool $links): int
    {
        return match (true) {
            $satellites && $links => self::END_REASON_BOTH,
            $satellites => self::END_REASON_SATELLITES,
            $links => self::END_REASON_LINKS,
            default => self::END_REASON_NONE,
        };
    }
}
