<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Setup;

use Bga\Games\ProjectEdgeworth\Board\BoardFace;
use Bga\Games\ProjectEdgeworth\Board\RandomSource;
use Bga\Games\ProjectEdgeworth\Material\RewardCardDefinition;
use Bga\Games\ProjectEdgeworth\Material\RewardCards;
use Bga\Games\ProjectEdgeworth\Material\RewardClass;
use Bga\Games\ProjectEdgeworth\Material\RewardMark;

/**
 * 褒賞 2 面の選択（001 §3-3、002 §7）。24 面を独立に扱う（010 §6.3）。
 * ✕印（ADVANCED_ONLY）の面は Advanced（裏面）のときだけ候補に入る。
 */
final class RewardSelector
{
    private function __construct()
    {
    }

    /**
     * @return list<string> 選んだ面のカード ID（T 番号の昇順）
     */
    public static function select(RewardSelection $selection, BoardFace $face, RandomSource $rng): array
    {
        $pool = array_values(array_filter(
            RewardCards::all(),
            static fn(RewardCardDefinition $c): bool => $face === BoardFace::ADVANCED || $c->mark !== RewardMark::AdvancedOnly,
        ));
        $of = static fn(callable $predicate): array => array_values(array_filter($pool, $predicate));

        $picked = match ($selection) {
            RewardSelection::InitialGame => [
                self::pickOne($of(static fn(RewardCardDefinition $c): bool => $c->mark === RewardMark::Star1), $rng),
                self::pickOne($of(static fn(RewardCardDefinition $c): bool => $c->mark === RewardMark::Star2), $rng),
            ],
            RewardSelection::CubeAndOther => [
                self::pickOne($of(static fn(RewardCardDefinition $c): bool => $c->class === RewardClass::Cube), $rng),
                self::pickOne($of(static fn(RewardCardDefinition $c): bool => $c->class === RewardClass::Other), $rng),
            ],
            RewardSelection::Random => self::pickTwo($pool, $rng),
        };

        $ids = array_map(static fn(RewardCardDefinition $c): string => $c->id, $picked);
        sort($ids);
        return $ids;
    }

    /**
     * @param list<RewardCardDefinition> $candidates
     */
    private static function pickOne(array $candidates, RandomSource $rng): RewardCardDefinition
    {
        return $candidates[$rng->rand(0, count($candidates) - 1)];
    }

    /**
     * @param list<RewardCardDefinition> $candidates
     * @return list<RewardCardDefinition>
     */
    private static function pickTwo(array $candidates, RandomSource $rng): array
    {
        $first = $rng->rand(0, count($candidates) - 1);
        $rest = $candidates;
        array_splice($rest, $first, 1);
        return [$candidates[$first], self::pickOne($rest, $rng)];
    }
}
