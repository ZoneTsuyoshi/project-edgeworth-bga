<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\ProjectEdgeworth\Data\ActionCardRepository;
use Bga\Games\ProjectEdgeworth\Data\ActionCardState;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class ActionCardRepositoryTest extends DatabaseTestCase
{
    private ActionCardRepository $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new ActionCardRepository($this->db);
        $this->actions->createFor(10);
        $this->actions->createFor(11);
    }

    public function testCreateGivesFiveCardsInHand(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], $this->actions->handOf(10));
        $this->assertSame(ActionCardState::Hand, $this->actions->state(11, 3));
    }

    public function testRevealMakesCardUprightAndOnlyOnePerPlayer(): void
    {
        $this->actions->reveal(10, 4);
        $this->assertSame(ActionCardState::Upright, $this->actions->state(10, 4));
        $this->assertSame([1, 2, 3, 5], $this->actions->handOf(10));
        $this->assertSame(ActionCardState::Hand, $this->actions->state(11, 4));
        $this->expectException(\DomainException::class);
        $this->actions->reveal(10, 2);
    }

    public function testRevealRequiresCardInHand(): void
    {
        $this->actions->reveal(10, 4);
        $this->actions->retrieve();
        $this->expectException(\DomainException::class);
        $this->actions->reveal(10, 4);
    }

    public function testRetrieveFollowsRule001Section6(): void
    {
        // ラウンド 1: III を選ぶ -> 縦。回収で横
        $this->actions->reveal(10, 3);
        $this->actions->retrieve();
        $this->assertSame(ActionCardState::Sideways, $this->actions->state(10, 3));
        $this->assertSame([1, 2, 4, 5], $this->actions->handOf(10));

        // ラウンド 2: I を選ぶ。回収で、前ラウンドの横は手札へ、I は手札へ戻る
        $this->actions->reveal(10, 1);
        $this->actions->retrieve();
        $this->assertSame([1, 2, 3, 4, 5], $this->actions->handOf(10));

        // ラウンド 3: II を選ぶ -> 横。次の回収で、前の横が戻り II が横になる
        $this->actions->reveal(10, 2);
        $this->actions->retrieve();
        $this->actions->reveal(10, 5);
        $this->actions->retrieve();
        $this->assertSame(ActionCardState::Sideways, $this->actions->state(10, 5));
        $this->assertSame(ActionCardState::Hand, $this->actions->state(10, 2));
    }

    public function testAllReturnsEveryPlayer(): void
    {
        $this->actions->reveal(11, 2);
        $all = $this->actions->all();
        $this->assertSame([10, 11], array_keys($all));
        $this->assertSame(ActionCardState::Upright, $all[11][2]);
        $this->assertCount(5, $all[10]);
    }
}
