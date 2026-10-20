<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\projectedgeworth\Data\ChoiceRepository;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class ChoiceRepositoryTest extends DatabaseTestCase
{
    private const STATE = 22;

    private ChoiceRepository $choices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->choices = new ChoiceRepository($this->db);
    }

    public function testConfirmedPlayersExposeOnlyIds(): void
    {
        $this->choices->submit(11, self::STATE, ['action' => 3], [10, 11, 12]);
        $this->assertSame([11], $this->choices->confirmedPlayers(self::STATE));
        $this->assertTrue($this->choices->isConfirmed(11, self::STATE));
        $this->assertFalse($this->choices->isConfirmed(10, self::STATE));
        $this->assertFalse($this->choices->allConfirmed(self::STATE, [10, 11, 12]));
    }

    public function testOwnChoiceRoundTripsJsonAndOthersSeeNothing(): void
    {
        $data = ['op' => 'deploy', 'card' => 12, 'sat' => 4, 'sell' => [3, 4]];
        $this->choices->submit(10, self::STATE, $data, [10, 11]);
        $this->assertSame($data, $this->choices->ownChoice(10, self::STATE));
        $this->assertNull($this->choices->ownChoice(11, self::STATE));
        $this->assertNull($this->choices->ownChoice(10, 99));
    }

    public function testResubmitOverwritesAndBumpsVersion(): void
    {
        $this->choices->submit(10, self::STATE, ['action' => 1], [10]);
        $this->assertSame(1, $this->choices->version(10, self::STATE));
        $this->choices->submit(10, self::STATE, ['action' => 5], [10]);
        $this->assertSame(2, $this->choices->version(10, self::STATE));
        $this->assertSame(['action' => 5], $this->choices->ownChoice(10, self::STATE));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM `choice`'));
    }

    public function testNonParticipantCannotSubmit(): void
    {
        $this->expectException(\DomainException::class);
        $this->choices->submit(12, self::STATE, ['action' => 1], [10, 11]);
    }

    public function testAllConfirmedAndRevealAll(): void
    {
        $this->choices->submit(10, self::STATE, ['action' => 1], [10, 11]);
        $this->choices->submit(11, self::STATE, ['op' => 'pass'], [10, 11], true);
        $this->assertTrue($this->choices->allConfirmed(self::STATE, [10, 11]));
        $this->assertTrue($this->choices->allConfirmed(self::STATE, []));
        $this->assertFalse($this->choices->isAuto(10, self::STATE));
        $this->assertTrue($this->choices->isAuto(11, self::STATE));
        $this->assertSame([10 => ['action' => 1], 11 => ['op' => 'pass']], $this->choices->revealAll(self::STATE));
    }

    public function testStatesAreIndependentAndClearOnlyTouchesOne(): void
    {
        $this->choices->submit(10, 22, ['action' => 1], [10]);
        $this->choices->submit(10, 61, ['op' => 'pass'], [10]);
        $this->choices->clear(22);
        $this->assertSame([], $this->choices->confirmedPlayers(22));
        $this->assertSame([10], $this->choices->confirmedPlayers(61));
    }
}
