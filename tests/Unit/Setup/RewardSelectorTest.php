<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Setup;

use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Material\RewardCards;
use Bga\Games\projectedgeworth\Material\RewardMark;
use Bga\Games\projectedgeworth\Setup\RewardSelection;
use Bga\Games\projectedgeworth\Setup\RewardSelector;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\SeededRandom;

final class RewardSelectorTest extends TestCase
{
    private const SEEDS = 200;

    public function testInitialGamePicksOneStar1AndOneStar2(): void
    {
        foreach ([BoardFace::STANDARD, BoardFace::ADVANCED] as $face) {
            for ($seed = 0; $seed < self::SEEDS; $seed++) {
                $marks = $this->marks(RewardSelector::select(RewardSelection::InitialGame, $face, new SeededRandom($seed)));
                $this->assertSame(['STAR1', 'STAR2'], $marks);
            }
        }
    }

    public function testCubeAndOtherPicksOneOfEachClass(): void
    {
        foreach ([BoardFace::STANDARD, BoardFace::ADVANCED] as $face) {
            for ($seed = 0; $seed < self::SEEDS; $seed++) {
                $classes = array_map(
                    static fn(string $id): string => RewardCards::get($id)->class->value,
                    RewardSelector::select(RewardSelection::CubeAndOther, $face, new SeededRandom($seed)),
                );
                sort($classes);
                $this->assertSame(['CUBE', 'OTHER'], $classes);
            }
        }
    }

    public function testRandomPicksTwoDistinctFaces(): void
    {
        for ($seed = 0; $seed < self::SEEDS; $seed++) {
            $ids = RewardSelector::select(RewardSelection::Random, BoardFace::ADVANCED, new SeededRandom($seed));
            $this->assertCount(2, $ids);
            $this->assertNotSame($ids[0], $ids[1]);
        }
    }

    public function testAdvancedOnlyFacesAreExcludedFromStandard(): void
    {
        foreach (RewardSelection::cases() as $selection) {
            for ($seed = 0; $seed < self::SEEDS; $seed++) {
                foreach (RewardSelector::select($selection, BoardFace::STANDARD, new SeededRandom($seed)) as $id) {
                    $this->assertNotSame(RewardMark::AdvancedOnly, RewardCards::get($id)->mark, "{$id} seed {$seed}");
                }
            }
        }
    }

    public function testAdvancedOnlyFacesCanAppearInAdvanced(): void
    {
        $seen = false;
        for ($seed = 0; $seed < self::SEEDS * 5 && !$seen; $seed++) {
            foreach (RewardSelector::select(RewardSelection::Random, BoardFace::ADVANCED, new SeededRandom($seed)) as $id) {
                $seen = $seen || RewardCards::get($id)->mark === RewardMark::AdvancedOnly;
            }
        }
        $this->assertTrue($seen);

        $seen = false;
        for ($seed = 0; $seed < self::SEEDS * 5 && !$seen; $seed++) {
            foreach (RewardSelector::select(RewardSelection::CubeAndOther, BoardFace::ADVANCED, new SeededRandom($seed)) as $id) {
                $seen = $seen || RewardCards::get($id)->mark === RewardMark::AdvancedOnly;
            }
        }
        $this->assertTrue($seen);
    }

    public function testResultIsSortedAndReproducible(): void
    {
        foreach (RewardSelection::cases() as $selection) {
            $a = RewardSelector::select($selection, BoardFace::ADVANCED, new SeededRandom(42));
            $b = RewardSelector::select($selection, BoardFace::ADVANCED, new SeededRandom(42));
            $this->assertSame($a, $b);
            $sorted = $a;
            sort($sorted);
            $this->assertSame($sorted, $a);
        }
    }

    public function testEveryCandidateIsReachable(): void
    {
        $seen = [];
        for ($seed = 0; $seed < 500; $seed++) {
            foreach (RewardSelector::select(RewardSelection::Random, BoardFace::STANDARD, new SeededRandom($seed)) as $id) {
                $seen[$id] = true;
            }
        }
        $expected = array_filter(RewardCards::all(), static fn($c): bool => $c->mark !== RewardMark::AdvancedOnly);
        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($seen));
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    private function marks(array $ids): array
    {
        $marks = array_map(static fn(string $id): string => RewardCards::get($id)->mark->value, $ids);
        sort($marks);
        return $marks;
    }
}
