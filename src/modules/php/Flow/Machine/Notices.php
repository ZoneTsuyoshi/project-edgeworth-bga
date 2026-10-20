<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\Satellite;
use Bga\Games\projectedgeworth\Material\ActionCards;
use Bga\Games\projectedgeworth\Material\ProjectCards;

/**
 * 通知の組み立て（010 §10.2 の N1〜N33）。通知名・args・ログ文言（英語原文）はここの 1 か所に集める
 * （clienttranslate のリテラルを翻訳の抽出に見つけさせるため。010 §10.1）。BGA に依存しない。
 *
 * 窓の中の保留通知（N27〜N29、010 §10.5）と本人宛ての N22 は、効果エンジンの結線（申し送り N-4）で追加する。
 */
final class Notices
{
    /**
     * @param \Closure(int): string $nameOf プレイヤー ID => プレイヤー名
     */
    public function __construct(private readonly \Closure $nameOf)
    {
    }

    /** 衛星の表示名（形状の英語名。形状はプレイヤー内で一意） */
    public static function satName(Satellite $satellite): string
    {
        return ucfirst(strtolower($satellite->shape->value));
    }

    public static function cardName(int $cardNumber): string
    {
        return ProjectCards::get(sprintf('P%03d', $cardNumber))->nameEn;
    }

    public static function actionName(int $actionNo): string
    {
        return ActionCards::get(sprintf('A%03d', $actionNo))->nameEn;
    }

    /** N1 */
    public function launchBasePlaced(int $playerId, int $cell): Notice
    {
        return new Notice('launchBasePlaced', clienttranslate('${player_name} places their launch base'), $this->player($playerId) + [
            'cell' => $cell,
        ]);
    }

    /** N2: 内容は載せない */
    public function choiceMarked(int $playerId, int $stateId): Notice
    {
        return new Notice('choiceMarked', '', ['player_id' => $playerId, 'state_id' => $stateId]);
    }

    /**
     * N3: 本人だけ。
     *
     * @param array<mixed> $summary
     */
    public function choiceRecorded(int $playerId, int $stateId, array $summary): Notice
    {
        return new Notice('choiceRecorded', '', ['state_id' => $stateId, 'summary' => $summary], $playerId);
    }

    /** N4 */
    public function roundStarted(int $round): Notice
    {
        return new Notice('roundStarted', clienttranslate('Round ${round_no} begins'), ['round_no' => $round]);
    }

    /**
     * N5
     *
     * @param array<int, array{toHand: list<int>, toSideways: list<int>}> $moves プレイヤー ID => 動き
     */
    public function actionCardsCollected(array $moves): Notice
    {
        return new Notice('actionCardsCollected', '', ['moves' => $moves]);
    }

    /** N6 */
    public function actionRevealed(int $playerId, int $actionNo): Notice
    {
        return new Notice('actionRevealed', clienttranslate('${player_name} chose ${action_name}'), $this->player($playerId) + [
            'action_no' => $actionNo,
            'action_name' => self::actionName($actionNo),
            'i18n' => ['action_name'],
        ]);
    }

    /**
     * N7
     *
     * @param int|string $phase アクション番号 1〜5、または 'orbit'
     * @param list<int> $holders アクション選択ボーナスの保持者
     */
    public function phaseStarted(int|string $phase, array $holders): Notice
    {
        $name = is_int($phase) ? self::actionName($phase) : clienttranslate('Orbit');
        return new Notice('phaseStarted', clienttranslate('${phase_name} phase begins'), [
            'phase' => $phase,
            'phase_name' => $name,
            'holders' => $holders,
            'i18n' => ['phase_name'],
        ]);
    }

    /**
     * N8（全員。枚数だけ）と N9（本人。ID）の対。
     *
     * @param list<int> $cards
     * @param string $zone hand / drawn
     * @return list<Notice>
     */
    public function cardsDrawn(int $playerId, array $cards, string $zone, string $reason, int $deckCount, int $discardCount): array
    {
        if ($cards === []) {
            return [];
        }
        return [
            new Notice('cardsDrawn', clienttranslate('${player_name} draws ${count} card(s)'), $this->player($playerId) + [
                'count' => count($cards),
                'zone' => $zone,
                'reason' => $reason,
                'deck_count' => $deckCount,
                'discard_count' => $discardCount,
            ]),
            new Notice('handCardsAdded', '', ['cards' => $cards, 'zone' => $zone, 'reason' => $reason], $playerId),
        ];
    }

    /**
     * N10: 捨てたカードの ID は公開（捨て札は表向き）。
     *
     * @param list<int> $cards
     * @param string $reason sell / proposal / mulligan / effect / deckRefill
     */
    public function cardsDiscarded(int $playerId, array $cards, string $reason, int $discardCount): Notice
    {
        $message = match ($reason) {
            'sell' => clienttranslate('${player_name} sold ${count} card(s)'),
            'mulligan' => clienttranslate('${player_name} mulligans ${count} card(s)'),
            'proposal', 'effect' => clienttranslate('${player_name} discards ${count} card(s)'),
            default => '',
        };
        return new Notice('cardsDiscarded', $message, $this->player($playerId) + [
            'cards' => $cards,
            'count' => count($cards),
            'reason' => $reason,
            'discard_count' => $discardCount,
        ]);
    }

    /** N11 */
    public function deckReshuffled(int $deckCount): Notice
    {
        return new Notice('deckReshuffled', clienttranslate('The discard pile is shuffled into a new deck'), [
            'deck_count' => $deckCount,
        ]);
    }

    /**
     * N12
     *
     * @param string $reason budget / bonus / sell / landing / areaBonus / cost / effect / cashOut / pioneer
     */
    public function mayaChanged(int $playerId, int $delta, int $maya, string $reason): ?Notice
    {
        if ($delta === 0) {
            return null;
        }
        $message = $delta > 0
            ? clienttranslate('${player_name} gains ${amount} Maya (${reason_label})')
            : clienttranslate('${player_name} pays ${amount} Maya (${reason_label})');
        return new Notice('mayaChanged', $message, $this->player($playerId) + [
            'delta' => $delta,
            'amount' => abs($delta),
            'maya' => $maya,
            'reason' => $reason,
            'reason_label' => self::reasonLabel($reason),
            'i18n' => ['reason_label'],
        ]);
    }

    /** N13 */
    public function mayaTransferred(int $from, int $to, int $amount, int $mayaFrom, int $mayaTo): Notice
    {
        return new Notice('mayaTransferred', clienttranslate('${from_name} pays ${amount} Maya of pioneer cost to ${to_name}'), [
            'from_player_id' => $from,
            'from_name' => ($this->nameOf)($from),
            'to_player_id' => $to,
            'to_name' => ($this->nameOf)($to),
            'amount' => $amount,
            'maya_from' => $mayaFrom,
            'maya_to' => $mayaTo,
        ]);
    }

    /** N14 */
    public function budgetChanged(int $playerId, int $delta, int $budget, string $reason): Notice
    {
        return new Notice('budgetChanged', clienttranslate('${player_name} raises Budget by ${delta} (${reason_label})'), $this->player($playerId) + [
            'delta' => $delta,
            'budget' => $budget,
            'reason_label' => self::reasonLabel($reason),
            'i18n' => ['reason_label'],
        ]);
    }

    /** N15 */
    public function cardDeployed(int $playerId, int $card, Satellite $sat, int $level, int $fuelCapacity, int $cost, bool $fromHand): Notice
    {
        return new Notice('cardDeployed', clienttranslate('${player_name} deploys ${card_name} to ${sat_name}'), $this->player($playerId) + [
            'card' => $card,
            'card_name' => self::cardName($card),
            'sat_id' => $sat->id,
            'sat_name' => self::satName($sat),
            'level' => $level,
            'fuel_capacity' => $fuelCapacity,
            'cost' => $cost,
            'from_hand' => $fromHand,
            'i18n' => ['card_name', 'sat_name'],
        ]);
    }

    /** N16 */
    public function satelliteLaunched(int $playerId, Satellite $sat, int $cell, string $dir, int $cost): Notice
    {
        return new Notice('satelliteLaunched', clienttranslate('${player_name} launches ${sat_name}'), $this->player($playerId) + [
            'sat_id' => $sat->id,
            'sat_name' => self::satName($sat),
            'cell' => $cell,
            'dir' => $dir,
            'cost' => $cost,
            'i18n' => ['sat_name'],
        ]);
    }

    /** N17 */
    public function satelliteTurned(int $playerId, Satellite $sat, string $dir, bool $same): Notice
    {
        return new Notice('satelliteTurned', clienttranslate('${player_name} turns ${sat_name}'), $this->player($playerId) + [
            'sat_id' => $sat->id,
            'sat_name' => self::satName($sat),
            'dir' => $dir,
            'same' => $same,
            'i18n' => ['sat_name'],
        ]);
    }

    /**
     * N18
     *
     * @param list<array{sat_id: int, from_cell: int, cell: int, dir: string, reflected: bool}> $moves
     */
    public function satellitesMoved(array $moves): Notice
    {
        return new Notice('satellitesMoved', clienttranslate('Satellites move one area'), ['moves' => $moves]);
    }

    /**
     * N19
     *
     * @param int $delta 消費は正、回復は負
     */
    public function fuelChanged(Satellite $sat, int $delta, int $fuelUsed, int $fuelCapacity, string $reason): ?Notice
    {
        if ($delta === 0) {
            return null;
        }
        $message = $delta > 0
            ? clienttranslate('${sat_name} uses ${amount} fuel (${reason_label})')
            : clienttranslate('${sat_name} recovers ${amount} fuel (${reason_label})');
        return new Notice('fuelChanged', $message, [
            'player_id' => $sat->playerId,
            'player_name' => ($this->nameOf)($sat->playerId),
            'sat_id' => $sat->id,
            'sat_name' => self::satName($sat),
            'delta' => $delta,
            'amount' => abs($delta),
            'fuel_used' => $fuelUsed,
            'fuel_capacity' => $fuelCapacity,
            'reason' => $reason,
            'reason_label' => self::reasonLabel($reason),
            'i18n' => ['sat_name', 'reason_label'],
        ]);
    }

    /**
     * N20
     *
     * @param list<int> $satIds
     */
    public function nearMiss(int $cell, array $satIds): Notice
    {
        return new Notice('nearMiss', clienttranslate('Near miss! The satellites in the same area use 2 extra fuel'), [
            'cell' => $cell,
            'sats' => $satIds,
        ]);
    }

    /** N21 */
    public function satelliteLanded(Satellite $sat, int $cell, bool $sea, int $maya): Notice
    {
        $message = $sea ? clienttranslate('${sat_name} splashes down') : clienttranslate('${sat_name} lands');
        return new Notice('satelliteLanded', $message, [
            'player_id' => $sat->playerId,
            'player_name' => ($this->nameOf)($sat->playerId),
            'sat_id' => $sat->id,
            'sat_name' => self::satName($sat),
            'cell' => $cell,
            'sea' => $sea,
            'maya' => $maya,
            'i18n' => ['sat_name'],
        ]);
    }

    /**
     * N23
     *
     * @param string $areaLabel 区域の種類の英語名（翻訳対象）
     */
    public function linkEstablished(
        int $playerId,
        int $cell,
        string $areaLabel,
        int $slot,
        int $level,
        int $satId,
        bool $special,
        int $commit,
        int $cubesLeft,
    ): Notice {
        return new Notice('linkEstablished', clienttranslate('${player_name} establishes a link at ${area_label}'), $this->player($playerId) + [
            'cell' => $cell,
            'area_label' => $areaLabel,
            'slot' => $slot,
            'level' => $level,
            'sat_id' => $satId,
            'special' => $special,
            'commit' => $commit,
            'cubes_left' => $cubesLeft,
            'i18n' => ['area_label'],
        ]);
    }

    /**
     * N24
     *
     * @param string $kind deploy / launch / link / bonus / keep
     */
    public function actionPassed(int $playerId, string $kind, bool $auto): Notice
    {
        if ($auto) {
            $message = clienttranslate('${player_name} has no available action');
        } elseif ($kind === 'link') {
            $message = clienttranslate('${player_name} abandons the link action');
        } else {
            $message = clienttranslate('${player_name} passes');
        }
        return new Notice('actionPassed', $message, $this->player($playerId) + ['kind' => $kind, 'auto' => $auto]);
    }

    /**
     * N25: 本人だけ。
     *
     * @param list<int> $kept
     */
    public function drawnCardsKept(int $playerId, array $kept): Notice
    {
        return new Notice('drawnCardsKept', '', ['kept' => $kept], $playerId);
    }

    /**
     * N26
     *
     * @param list<int> $events
     */
    public function windowClosed(string $windowId, array $events): Notice
    {
        return new Notice('windowClosed', '', ['window_id' => $windowId, 'events' => $events]);
    }

    /**
     * N30
     *
     * @param list<int> $cards
     */
    public function handCashedOut(int $playerId, array $cards, int $mayaGain, int $maya): Notice
    {
        return new Notice('handCashedOut', clienttranslate('${player_name}\'s hand was sold for ${maya_gain} Maya at game end'), $this->player($playerId) + [
            'cards' => $cards,
            'maya_gain' => $mayaGain,
            'maya' => $maya,
        ]);
    }

    /**
     * N31
     *
     * @param list<int> $players
     * @param list<string> $reasons satellites / links
     */
    public function gameEndTriggered(array $players, array $reasons): Notice
    {
        return new Notice('gameEndTriggered', clienttranslate('The game ends after this round'), [
            'players' => $players,
            'reasons' => $reasons,
        ]);
    }

    /**
     * N32
     *
     * @param array<mixed> $detail
     * @param array<mixed> $missions
     */
    public function finalScore(int $playerId, int $fame, array $detail, array $missions): Notice
    {
        return new Notice('finalScore', clienttranslate('${player_name} scores ${fame} fame'), $this->player($playerId) + [
            'fame' => $fame,
            'detail' => $detail,
            'missions' => $missions,
        ]);
    }

    /**
     * N33
     *
     * @param array<int, array{handCount: int, drawnCount: int, cubesLeft: int}> $players
     */
    public function countersUpdate(int $deckCount, int $discardCount, array $players): Notice
    {
        return new Notice('countersUpdate', '', [
            'deck_count' => $deckCount,
            'discard_count' => $discardCount,
            'players' => $players,
        ]);
    }

    /** 区域の種類の英語名（N23 の area_label。翻訳の抽出用にリテラルで持つ） */
    public static function areaLabel(string $areaType): string
    {
        return match ($areaType) {
            'sea' => clienttranslate('Sea'),
            'land' => clienttranslate('Land'),
            'deep_sea' => clienttranslate('Deep sea'),
            'reef' => clienttranslate('Reef'),
            'forest' => clienttranslate('Forest'),
            'mountain' => clienttranslate('Mountain'),
            'snow' => clienttranslate('Snowfield'),
            default => $areaType,
        };
    }

    /** N12・N14・N19 の reason_label（翻訳の抽出用にリテラルで持つ） */
    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'budget' => clienttranslate('Budget'),
            'bonus' => clienttranslate('Action bonus'),
            'sell' => clienttranslate('selling cards'),
            'landing' => clienttranslate('splashdown'),
            'areaBonus' => clienttranslate('area bonus'),
            'cost' => clienttranslate('cost'),
            'effect' => clienttranslate('effect'),
            'cashOut' => clienttranslate('game end'),
            'pioneer' => clienttranslate('pioneer cost'),
            'launch' => clienttranslate('launch'),
            'orbit' => clienttranslate('orbit'),
            'nearMiss' => clienttranslate('near miss'),
            'deepSea' => clienttranslate('deep sea'),
            default => $reason,
        };
    }

    /**
     * @return array{player_id: int, player_name: string}
     */
    private function player(int $playerId): array
    {
        return ['player_id' => $playerId, 'player_name' => ($this->nameOf)($playerId)];
    }
}
