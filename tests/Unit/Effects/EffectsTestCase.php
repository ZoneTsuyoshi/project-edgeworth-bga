<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects;

use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\EffectEngine;
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Pool\PoolItem;
use Bga\Games\projectedgeworth\Effects\Question\Question;
use Bga\Games\projectedgeworth\Material\CardEffectCatalog;
use Bga\Games\projectedgeworth\Material\EffectCatalog;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\ProjectType;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\FakeGame;
use ProjectEdgeworthTests\Support\Effects\SampleParts;

/**
 * 効果エンジンのテストの共通部品。
 */
abstract class EffectsTestCase extends TestCase
{
    protected function engine(FakeGame $game, ?PartRegistry $parts = null, ?EffectCatalog $catalog = null): EffectEngine
    {
        return new EffectEngine($game, $game, $parts ?? SampleParts::registry($game), $catalog ?? new CardEffectCatalog());
    }

    protected function deployed(int $player, int $sat, int $cardNo, ProjectType $type): GameEvent
    {
        return new GameEvent(Hook::Deployed, $player, $sat, $cardNo, $type);
    }

    /**
     * @param list<Question> $questions
     * @return list<string> 問いの種類
     */
    protected function types(array $questions): array
    {
        return array_map(static fn(Question $q): string => $q->type->value, $questions);
    }

    /**
     * @param list<Question> $questions
     * @return array<string, int> 問いの cardKey => entryId（EFFECT）
     */
    protected function entryIds(array $questions): array
    {
        $ids = [];
        foreach ($questions as $q) {
            foreach ($q->entries as $e) {
                $ids[$e['cardKey']] = $e['entryId'];
            }
            if ($q->entryId !== null && $q->cardKey !== null) {
                $ids[$q->cardKey] = $q->entryId;
            }
        }

        return $ids;
    }

    protected function item(int $id, string $kind, int $src, string $cardKey, int $player = 1, ?GameEvent $event = null): PoolItem
    {
        return new PoolItem($id, $player, $cardKey, $kind, $src, $event ?? new GameEvent(Hook::BudgetStart, $player));
    }
}
