<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\ProjectEdgeworth\Data\HeldNotificationRepository;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class HeldNotificationRepositoryTest extends DatabaseTestCase
{
    public function testPushKeepsChronologicalOrderAndArgs(): void
    {
        $held = new HeldNotificationRepository($this->db);
        $first = $held->push(10, 'mayaChanged', 'negative', ['delta' => -3, 'note' => "it's \"quoted\" 日本語"]);
        $second = $held->push(11, 'cardsDrawn', '', ['count' => 2]);
        $this->assertGreaterThan($first, $second);

        $all = $held->all();
        $this->assertCount(2, $all);
        $this->assertSame([10, 'mayaChanged', 'negative'], [$all[0]->originPlayerId, $all[0]->type, $all[0]->variant]);
        $this->assertSame(['delta' => -3, 'note' => "it's \"quoted\" 日本語"], $all[0]->args);
        $this->assertSame('', $all[1]->variant);
        $this->assertSame(2, $held->count());
    }

    public function testClear(): void
    {
        $held = new HeldNotificationRepository($this->db);
        $held->push(10, 'x', '', []);
        $held->clear();
        $this->assertSame(0, $held->count());
        $this->assertSame([], $held->all());
    }
}
