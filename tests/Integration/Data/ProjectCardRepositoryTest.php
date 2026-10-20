<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use ProjectEdgeworthTests\Support\DatabaseTestCase;
use ProjectEdgeworthTests\Support\SeededRandom;

final class ProjectCardRepositoryTest extends DatabaseTestCase
{
    private ProjectCardRepository $cards;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cards = new ProjectCardRepository($this->db, new SeededRandom(7));
        $this->cards->createAll();
    }

    public function testCreateAllMatchesMaterialCount(): void
    {
        $this->assertSame(count(ProjectCards::all()), ProjectCardRepository::CARD_COUNT);
        $this->assertSame(99, $this->cards->countIn(CardLocation::Deck));
        $this->assertSame(0, $this->cards->countIn(CardLocation::Discard));
    }

    public function testShuffleKeepsAllCardsAndIsReproducible(): void
    {
        $this->cards->shuffleDeck();
        $first = $this->cards->idsIn(CardLocation::Deck);
        $this->assertCount(99, $first);
        $this->assertSame(range(1, 99), $this->sorted($first));
        $this->assertNotSame(range(1, 99), $first);
        $this->assertSame(range(0, 98), array_map(fn(int $id): int => $this->cards->locationOf($id)['order'], $first));

        // 同じ種なら同じ並びになる
        $this->cards->shuffleDeck();
        $second = $this->cards->idsIn(CardLocation::Deck);
        $this->assertSame(range(1, 99), $this->sorted($second));
        $again = new ProjectCardRepository($this->db, new SeededRandom(7));
        $this->db->execute("UPDATE `project_card` SET `card_order` = 0");
        $again->shuffleDeck();
        $this->assertSame($first, $again->idsIn(CardLocation::Deck));
    }

    public function testDrawTopTakesFromTheTopInOrder(): void
    {
        $this->cards->shuffleDeck();
        $order = $this->cards->idsIn(CardLocation::Deck);
        $drawn = $this->cards->drawTop(10, 3);
        $this->assertSame([$order[98], $order[97], $order[96]], $drawn);
        $this->assertSame(96, $this->cards->countIn(CardLocation::Deck));
        $this->assertSame(3, $this->cards->countIn(CardLocation::Hand, 10));
        $this->assertSame(0, $this->cards->countIn(CardLocation::Hand, 11));
    }

    public function testDrawnKeepsDrawOrderAndKeepMovesToHand(): void
    {
        $this->cards->shuffleDeck();
        $drawn = $this->cards->drawTop(10, 4, CardLocation::Drawn);
        $this->assertSame($drawn, $this->cards->idsIn(CardLocation::Drawn, 10));
        $this->cards->keepDrawn(10, [$drawn[0], $drawn[2]]);
        $this->assertSame([$drawn[1], $drawn[3]], $this->cards->idsIn(CardLocation::Drawn, 10));
        $this->assertSame($this->sorted([$drawn[0], $drawn[2]]), $this->sorted($this->cards->idsIn(CardLocation::Hand, 10)));
    }

    public function testKeepDrawnRejectsCardNotDrawnByThatPlayer(): void
    {
        $this->cards->shuffleDeck();
        $mine = $this->cards->drawTop(10, 1, CardLocation::Drawn);
        $this->expectException(\DomainException::class);
        $this->cards->keepDrawn(11, $mine);
    }

    public function testDrawReshufflesDiscardWhenDeckRunsOut(): void
    {
        $this->cards->shuffleDeck();
        $hand = $this->cards->drawTop(10, 97);
        $remaining = $this->cards->idsIn(CardLocation::Deck);
        $this->cards->discard(array_slice($hand, 0, 50));
        $this->assertSame(2, $this->cards->countIn(CardLocation::Deck));

        $drawn = $this->cards->drawTop(11, 5);
        $this->assertCount(5, $drawn);
        // 元の山札の 2 枚が先に引かれ、残りは捨て札から作った山から引かれる
        $this->assertSame([$remaining[1], $remaining[0]], array_slice($drawn, 0, 2));
        $this->assertSame(0, $this->cards->countIn(CardLocation::Discard));
        $this->assertSame(47, $this->cards->countIn(CardLocation::Deck));
        $this->assertSame(5, $this->cards->countIn(CardLocation::Hand, 11));
    }

    public function testDrawStopsWhenNothingLeft(): void
    {
        $this->cards->shuffleDeck();
        $this->cards->drawTop(10, 99);
        $this->assertSame([], $this->cards->drawTop(11, 2));
    }

    public function testPutOnTopAndBottom(): void
    {
        $this->cards->shuffleDeck();
        $hand = $this->cards->drawTop(10, 2);
        $this->cards->putOnBottom($hand[0]);
        $this->cards->putOnTop($hand[1]);
        $deck = $this->cards->idsIn(CardLocation::Deck);
        $this->assertSame($hand[0], $deck[0]);
        $this->assertSame($hand[1], $deck[count($deck) - 1]);
        $this->assertSame($hand[1], $this->cards->peekTop(CardLocation::Deck));
        $this->assertSame(-1, $this->cards->locationOf($hand[0])['order']);
        $this->assertSame([$hand[1]], $this->cards->drawTop(11, 1));
    }

    public function testDiscardStacksNewestOnTop(): void
    {
        $this->cards->shuffleDeck();
        $hand = $this->cards->drawTop(10, 3);
        $this->cards->discard([$hand[1], $hand[0]]);
        $this->cards->discard([$hand[2]]);
        $this->assertSame([$hand[1], $hand[0], $hand[2]], $this->cards->idsIn(CardLocation::Discard));
        $this->assertSame($hand[2], $this->cards->peekTop(CardLocation::Discard));
    }

    public function testDeployBuildsTiersAndRejectsFifthCard(): void
    {
        $this->cards->shuffleDeck();
        $hand = $this->cards->drawTop(10, 5);
        foreach ([0, 1, 2, 3] as $i) {
            $this->assertSame($i + 1, $this->cards->deploy(10, $hand[$i], 55));
        }
        $this->assertSame(array_slice($hand, 0, 4), $this->cards->deployedIds(55));
        $this->expectException(\DomainException::class);
        $this->cards->deploy(10, $hand[4], 55);
    }

    public function testDeployRequiresCardInOwnHand(): void
    {
        $this->cards->shuffleDeck();
        $hand = $this->cards->drawTop(10, 1);
        $this->expectException(\DomainException::class);
        $this->cards->deploy(11, $hand[0], 55);
    }

    public function testUndeployCompactsTiersAndCanMoveToAnotherSatellite(): void
    {
        $this->cards->shuffleDeck();
        $hand = $this->cards->drawTop(10, 4);
        foreach ($hand as $id) {
            $this->cards->deploy(10, $id, 55);
        }
        $this->cards->undeploy($hand[1], CardLocation::Discard);
        $this->assertSame([$hand[0], $hand[2], $hand[3]], $this->cards->deployedIds(55));
        $this->assertSame([1, 2, 3], array_map(fn(int $id): int => $this->cards->locationOf($id)['order'], $this->cards->deployedIds(55)));

        $this->cards->undeploy($hand[0], CardLocation::Satellite, 66);
        $this->assertSame([$hand[2], $hand[3]], $this->cards->deployedIds(55));
        $this->assertSame([1, 2], array_map(fn(int $id): int => $this->cards->locationOf($id)['order'], $this->cards->deployedIds(55)));
        $this->assertSame([$hand[0]], $this->cards->deployedIds(66));
    }

    public function testActivationAndMemo(): void
    {
        $this->cards->setActivation(5, 1);
        $this->cards->setActivation(6, 2);
        $this->assertSame(1, $this->cards->activation(5));
        $this->cards->resetActivations();
        $this->assertSame(0, $this->cards->activation(5));
        $this->assertSame(0, $this->cards->activation(6));

        $this->assertNull($this->cards->memo(5));
        $this->cards->setMemo(5, ['n' => 'ゆ"\'ー']);
        $this->assertSame(['n' => 'ゆ"\'ー'], $this->cards->memo(5));
    }

    public function testPrimaryKeyKeepsEachCardInOnePlace(): void
    {
        $this->expectException(\PDOException::class);
        $this->db->execute("INSERT INTO `project_card` (`card_id`, `card_location`) VALUES (1, 'deck')");
    }

    public function testLocationOfUnknownCard(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->cards->locationOf(500);
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);
        return $ids;
    }
}
