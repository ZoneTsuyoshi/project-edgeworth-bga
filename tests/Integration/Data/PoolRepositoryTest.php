<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\projectedgeworth\Data\PoolRepository;
use Bga\Games\projectedgeworth\Data\PoolStatus;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class PoolRepositoryTest extends DatabaseTestCase
{
    private PoolRepository $pool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pool = new PoolRepository($this->db);
    }

    public function testAddAndReadBack(): void
    {
        $id = $this->pool->add(10, 'DE', 'P', 13, 'ground_link', 'DEPLOYED', ['sat' => 4, 'gen' => 0], 2);
        $entry = $this->pool->get($id);
        $this->assertSame([10, 'DE', 'P', 13, 'ground_link', 'DEPLOYED'], [
            $entry->playerId, $entry->window, $entry->kind, $entry->src, $entry->effect, $entry->event,
        ]);
        $this->assertSame(['sat' => 4, 'gen' => 0], $entry->context);
        $this->assertSame(2, $entry->defaultOrder);
        $this->assertNull($entry->chosenOrder);
        $this->assertSame(PoolStatus::Pending, $entry->status);
        $this->assertNull($entry->state);
    }

    public function testEntriesAreOrderedByChosenOrderThenDefault(): void
    {
        $a = $this->pool->add(10, 'DE', 'S', 1, 'a', 'DEPLOYED', null, 1);
        $b = $this->pool->add(10, 'DE', 'P', 2, 'b', 'DEPLOYED', null, 2);
        $c = $this->pool->add(10, 'DE', 'P', 3, 'c', 'DEPLOYED', null, 3);
        $this->pool->add(11, 'DE', 'P', 4, 'd', 'DEPLOYED', null, 1);
        $ids = fn(): array => array_map(static fn($e): int => $e->id, $this->pool->forPlayer(10));
        $this->assertSame([$a, $b, $c], $ids());
        $this->pool->setChosenOrder($c, 0);
        $this->assertSame([$c, $a, $b], $ids());
        $this->expectException(\DomainException::class);
        $this->pool->setChosenOrder($c, 5);
    }

    public function testStatusAndStateAndCounts(): void
    {
        $a = $this->pool->add(10, 'DE', 'P', 2, 'b', 'LINKED', null, 1);
        $b = $this->pool->add(10, 'DE', 'P', 3, 'c', 'LINKED', null, 2);
        $this->pool->setStatus($a, PoolStatus::Done);
        $this->pool->setStatus($b, PoolStatus::Asked);
        $this->pool->setState($b, ['step' => 2, 'picked' => [1, 2]]);
        $this->assertSame(['step' => 2, 'picked' => [1, 2]], $this->pool->get($b)->state);
        $this->assertSame(1, $this->pool->countByStatus(PoolStatus::Done));
        $this->assertSame(0, $this->pool->countByStatus(PoolStatus::Pending, 10));
        $this->assertCount(1, $this->pool->forPlayer(10, PoolStatus::Asked));
        $this->assertCount(2, $this->pool->all());
    }

    public function testClearEmptiesThePool(): void
    {
        $this->pool->add(10, 'DE', 'P', 2, 'b', 'LINKED', null, 1);
        $this->pool->clear();
        $this->assertSame(0, $this->pool->count());
    }

    public function testRejectsUnknownKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->pool->add(10, 'DE', 'X', 2, 'b', 'LINKED', null, 1);
    }
}
