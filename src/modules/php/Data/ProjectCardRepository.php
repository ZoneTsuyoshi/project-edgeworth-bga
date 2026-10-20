<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

use Bga\Games\projectedgeworth\Board\RandomSource;

/**
 * project_card テーブルの唯一の読み書き口（010 §4.4）。
 * 山（deck・discard）の order の向きに依存するコードをこのクラスの外に出さない。
 *
 * 非公開情報: idsIn() に Deck を渡さない。Hand・Drawn は本人の視点でだけ使う。
 * drawTop() の戻り値を公開通知に入れない。
 */
final class ProjectCardRepository
{
    public const CARD_COUNT = 99;
    public const MAX_DEPLOYED = 4;

    public function __construct(private readonly Database $db, private readonly RandomSource $random)
    {
    }

    /**
     * 99 行を deck に作る（setupNewGame 用）。順序は shuffleDeck() で決める。
     */
    public function createAll(): void
    {
        $values = [];
        for ($id = 1; $id <= self::CARD_COUNT; $id++) {
            $values[] = "($id, 'deck', 0, 0)";
        }
        $this->db->execute(
            'INSERT INTO `project_card` (`card_id`, `card_location`, `card_location_arg`, `card_order`) VALUES '
            . implode(', ', $values),
        );
    }

    public function countIn(CardLocation $location, int $arg = 0): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM `project_card` WHERE ' . $this->where($location, $arg),
        );
    }

    /**
     * 置き場所のカード ID（order の昇順。山では先頭が一番下、末尾が一番上）。
     * Deck は呼ばないこと。Hand・Drawn は本人の視点でだけ使うこと。
     *
     * @return list<int>
     */
    public function idsIn(CardLocation $location, int $arg = 0): array
    {
        $rows = $this->db->rows(
            'SELECT `card_id` FROM `project_card` WHERE ' . $this->where($location, $arg)
            . ' ORDER BY `card_order` ASC, `card_id` ASC',
        );
        return array_map(static fn(array $r): int => (int) $r['card_id'], $rows);
    }

    /**
     * カードの現在の置き場所。
     *
     * @return array{location: CardLocation, arg: int, order: int}
     */
    public function locationOf(int $cardId): array
    {
        $rows = $this->db->rows(
            "SELECT `card_location`, `card_location_arg`, `card_order` FROM `project_card` WHERE `card_id` = $cardId",
        );
        if ($rows === []) {
            throw new \InvalidArgumentException("card not found: $cardId");
        }
        return [
            'location' => CardLocation::from((string) $rows[0]['card_location']),
            'arg' => (int) $rows[0]['card_location_arg'],
            'order' => (int) $rows[0]['card_order'],
        ];
    }

    /**
     * 山札の上から n 枚を hand または drawn へ移し、引いた順の ID を返す。
     * 山札が足りなければ捨て札をシャッフルして山札にする（001 §2-1）。それでも足りなければ引けた分だけ。
     *
     * @return list<int>
     */
    public function drawTop(int $playerId, int $count, CardLocation $into = CardLocation::Hand): array
    {
        if ($into !== CardLocation::Hand && $into !== CardLocation::Drawn) {
            throw new \InvalidArgumentException('drawTop can only move cards into hand or drawn');
        }
        $drawn = [];
        while (count($drawn) < $count) {
            $top = $this->topId(CardLocation::Deck);
            if ($top === null) {
                if ($this->countIn(CardLocation::Discard) === 0) {
                    break;
                }
                $this->reshuffleDiscard();
                continue;
            }
            $this->place($top, $into, $playerId, $this->nextOrder($into, $playerId));
            $drawn[] = $top;
        }
        return $drawn;
    }

    /**
     * 山札の一番上のカード（動かさない）。空なら null。
     */
    public function peekTop(CardLocation $pile): ?int
    {
        $this->assertPile($pile);
        return $this->topId($pile);
    }

    /**
     * 任意の場所のカードを山の一番上へ。
     */
    public function putOnTop(int $cardId, CardLocation $pile = CardLocation::Deck): void
    {
        $this->assertPile($pile);
        $this->locationOf($cardId);
        $this->place($cardId, $pile, 0, $this->nextOrder($pile, 0));
    }

    /**
     * 任意の場所のカードを山の一番下へ（最小値 − 1。符号付き INT）。
     */
    public function putOnBottom(int $cardId, CardLocation $pile = CardLocation::Deck): void
    {
        $this->assertPile($pile);
        $this->locationOf($cardId);
        $min = $this->db->value('SELECT MIN(`card_order`) FROM `project_card` WHERE ' . $this->where($pile, 0));
        $this->place($cardId, $pile, 0, $min === null ? 0 : (int) $min - 1);
    }

    /**
     * 山札全体をシャッフルし、card_order を 0〜n−1 に書き直す。結果の順序は誰にも送らない。
     */
    public function shuffleDeck(): void
    {
        $ids = $this->shuffled($this->idsIn(CardLocation::Deck));
        foreach ($ids as $order => $id) {
            $this->place($id, CardLocation::Deck, 0, $order);
        }
    }

    /**
     * 捨て札全体をシャッフルして山札の下に敷く（山札が空なら山札そのものになる）。
     */
    public function reshuffleDiscard(): void
    {
        $ids = $this->shuffled($this->idsIn(CardLocation::Discard));
        $min = $this->db->value(
            'SELECT MIN(`card_order`) FROM `project_card` WHERE ' . $this->where(CardLocation::Deck, 0),
        );
        $start = $min === null ? 0 : (int) $min - count($ids);
        foreach ($ids as $i => $id) {
            $this->place($id, CardLocation::Deck, 0, $start + $i);
        }
    }

    /**
     * 引いた直後（drawn）のカードのうち、$keep を手札へ移す。残りは変えない。
     *
     * @param list<int> $keep
     */
    public function keepDrawn(int $playerId, array $keep): void
    {
        $this->assertOwned($keep, CardLocation::Drawn, $playerId);
        foreach ($keep as $id) {
            $this->place($id, CardLocation::Hand, $playerId, $this->nextOrder(CardLocation::Hand, $playerId));
        }
    }

    /**
     * 手札・引いた直後・配備済みなど任意の場所から捨て札へ。捨てた順に積む（後のものが上）。
     *
     * @param list<int> $ids
     */
    public function discard(array $ids): void
    {
        foreach ($ids as $id) {
            $this->locationOf($id);
            $this->place($id, CardLocation::Discard, 0, $this->nextOrder(CardLocation::Discard, 0));
        }
    }

    /**
     * 手札から衛星へ配備する。段は最上段 + 1。5 枚目は例外。
     */
    public function deploy(int $playerId, int $cardId, int $satId): int
    {
        $this->assertOwned([$cardId], CardLocation::Hand, $playerId);
        if ($this->countIn(CardLocation::Satellite, $satId) >= self::MAX_DEPLOYED) {
            throw new \DomainException("satellite $satId already has " . self::MAX_DEPLOYED . ' deployed cards');
        }
        $tier = $this->nextOrder(CardLocation::Satellite, $satId);
        $this->place($cardId, CardLocation::Satellite, $satId, $tier);
        return $tier;
    }

    /**
     * 衛星に配備されたカードを別の場所へ動かし、元の衛星の段を 1 から詰め直す。
     * 別の衛星へ動かすときは $to = Satellite、$arg = 行き先の sat_id（4 枚を超えるときは例外）。
     */
    public function undeploy(int $cardId, CardLocation $to, int $arg = 0): void
    {
        $from = $this->locationOf($cardId);
        if ($from['location'] !== CardLocation::Satellite) {
            throw new \DomainException("card $cardId is not deployed");
        }
        $toOtherSatellite = $to === CardLocation::Satellite && $arg !== $from['arg'];
        if ($toOtherSatellite && $this->countIn(CardLocation::Satellite, $arg) >= self::MAX_DEPLOYED) {
            throw new \DomainException("satellite $arg already has " . self::MAX_DEPLOYED . ' deployed cards');
        }
        $order = $this->nextOrder($to, $arg);
        $this->place($cardId, $to, $arg, $order);
        $this->compactTiers($from['arg']);
        if ($to === CardLocation::Satellite) {
            $this->compactTiers($arg);
        }
    }

    /**
     * 衛星に配備されたカードを段の昇順（下から）で。
     *
     * @return list<int>
     */
    public function deployedIds(int $satId): array
    {
        return $this->idsIn(CardLocation::Satellite, $satId);
    }

    public function activation(int $cardId): int
    {
        return (int) $this->db->value("SELECT `card_activation` FROM `project_card` WHERE `card_id` = $cardId");
    }

    public function setActivation(int $cardId, int $value): void
    {
        $this->db->execute("UPDATE `project_card` SET `card_activation` = $value WHERE `card_id` = $cardId");
    }

    /**
     * 全カードの起動効果の状態を未使用に戻す（RoundStart。009 §7）。
     */
    public function resetActivations(): void
    {
        $this->db->execute('UPDATE `project_card` SET `card_activation` = 0');
    }

    /**
     * @return array<mixed>|null
     */
    public function memo(int $cardId): ?array
    {
        return Sql::decode($this->db->value("SELECT `card_memo` FROM `project_card` WHERE `card_id` = $cardId"));
    }

    /**
     * @param array<mixed>|null $memo
     */
    public function setMemo(int $cardId, ?array $memo): void
    {
        $this->db->execute(
            'UPDATE `project_card` SET `card_memo` = ' . Sql::nullableJson($this->db, $memo) . " WHERE `card_id` = $cardId",
        );
    }

    private function compactTiers(int $satId): void
    {
        foreach ($this->deployedIds($satId) as $i => $id) {
            $this->place($id, CardLocation::Satellite, $satId, $i + 1);
        }
    }

    private function topId(CardLocation $pile): ?int
    {
        $id = $this->db->value(
            'SELECT `card_id` FROM `project_card` WHERE ' . $this->where($pile, 0)
            . ' ORDER BY `card_order` DESC, `card_id` DESC LIMIT 1',
        );
        return $id === null ? null : (int) $id;
    }

    private function nextOrder(CardLocation $location, int $arg): int
    {
        $max = $this->db->value('SELECT MAX(`card_order`) FROM `project_card` WHERE ' . $this->where($location, $arg));
        if ($max === null) {
            return $location === CardLocation::Satellite ? 1 : 0;
        }
        return (int) $max + 1;
    }

    private function place(int $cardId, CardLocation $location, int $arg, int $order): void
    {
        $this->db->execute(
            "UPDATE `project_card` SET `card_location` = '{$location->value}', `card_location_arg` = $arg, "
            . "`card_order` = $order WHERE `card_id` = $cardId",
        );
    }

    private function where(CardLocation $location, int $arg): string
    {
        return "`card_location` = '{$location->value}' AND `card_location_arg` = $arg";
    }

    private function assertPile(CardLocation $pile): void
    {
        if ($pile !== CardLocation::Deck && $pile !== CardLocation::Discard) {
            throw new \InvalidArgumentException('not a pile: ' . $pile->value);
        }
    }

    /**
     * @param list<int> $ids
     */
    private function assertOwned(array $ids, CardLocation $location, int $arg): void
    {
        foreach ($ids as $id) {
            $at = $this->locationOf($id);
            if ($at['location'] !== $location || $at['arg'] !== $arg) {
                throw new \DomainException("card $id is not in {$location->value}:$arg");
            }
        }
    }

    /**
     * Fisher-Yates。
     *
     * @param list<int> $ids
     * @return list<int>
     */
    private function shuffled(array $ids): array
    {
        for ($i = count($ids) - 1; $i > 0; $i--) {
            $j = $this->random->rand(0, $i);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        return $ids;
    }
}
