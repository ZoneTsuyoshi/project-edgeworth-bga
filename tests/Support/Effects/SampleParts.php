<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\projectedgeworth\Effects\Parts\ForbidPart;
use Bga\Games\projectedgeworth\Effects\Parts\ModifierPart;
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Query\CostBreakdown;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Outcome;
use Bga\Games\projectedgeworth\Effects\Parts\PartCall;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\Field;
use Bga\Games\projectedgeworth\Effects\Question\FieldType;
use Bga\Games\projectedgeworth\Material\EffectSpec;
use Bga\Games\projectedgeworth\Material\PartId;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\ProjectType;

/**
 * `CardEffects` の代表 10 枚の部品を、`FakeGame` に書き込むだけの簡易版で用意する（本物の部品は E-1 以降）。
 */
final class SampleParts
{
    public static function registry(FakeGame $game): PartRegistry
    {
        $registry = new PartRegistry();
        $registry->register(PartId::GainMaya, new ScriptedResultPart(function (PartCall $c) use ($game): Outcome {
            $game->maya[$c->host->playerId] += (int) $c->spec->params['amount'];
            $game->note('maya', (int) $c->spec->params['amount']);

            return Outcome::none();
        }));
        $registry->register(PartId::ChangeBudget, new ScriptedResultPart(function (PartCall $c) use ($game): Outcome {
            $game->budget[$c->host->playerId] += (int) $c->spec->params['amount'];
            $game->note('budget', (int) $c->spec->params['amount']);

            return Outcome::none();
        }));
        $registry->register(PartId::DrawCards, new ScriptedResultPart(function (PartCall $c) use ($game): Outcome {
            $count = isset($c->spec->params['countDevice'])
                ? $game->deviceCountOn((int) $c->host->satelliteId)
                : (int) $c->spec->params['count'];
            $game->note('draw', $game->draw($c->host->playerId, $count));

            return Outcome::none();
        }));
        $registry->register(PartId::DeployFromHand, new ScriptedResultPart(
            function (PartCall $c, ?Answer $a) use ($game): Outcome {
                $cards = $a?->fields['card'] ?? [];
                foreach ($cards as $no) {
                    $game->removeFromHand($c->host->playerId, (int) $no);
                    $game->note('deployFromHand', (int) $no);
                }

                return Outcome::none();
            },
            static fn(PartCall $c): array => [new Field('card', FieldType::Cards, array_values(array_filter(
                $game->hand($c->host->playerId),
                static fn(int $no): bool => ProjectCards::get(sprintf('P%03d', $no))->type === ProjectType::Device,
            )), 1, 1)],
            static fn(PartCall $c): bool => array_filter(
                $game->hand($c->host->playerId),
                static fn(int $no): bool => ProjectCards::get(sprintf('P%03d', $no))->type === ProjectType::Device,
            ) !== [],
        ));
        $registry->register(PartId::MoveSatellite, new ScriptedResultPart(
            function (PartCall $c, ?Answer $a) use ($game): Outcome {
                $game->note('move', (int) ($a?->fields['areas'] ?? 0));

                return Outcome::none();
            },
            static fn(PartCall $c): array => [new Field('areas', FieldType::Amount, [1, 2, 3, 4], 1, 4)],
        ));
        $registry->register(PartId::DeployDeckTop, new ScriptedResultPart(function () use ($game): Outcome {
            $game->note('deployDeckTop', $game->deck[0] ?? 0);

            return Outcome::none();
        }));
        $registry->register(PartId::TransferFuel, new ScriptedResultPart(
            function (PartCall $c, ?Answer $a) use ($game): Outcome {
                $game->note('transfer', (int) ($a?->fields['amount'] ?? 0));

                return Outcome::none();
            },
            static fn(PartCall $c): array => [new Field('amount', FieldType::Amount, [], 0, 3)],
        ));
        $registry->register(PartId::DeployCostMod, new class implements ModifierPart {
            public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
            {
                return ($spec->params['scope'] ?? '') !== 'THIS_SATELLITE' || $query->satelliteId === $host->satelliteId;
            }

            public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
            {
                return $value instanceof CostBreakdown ? $value->withModifier($spec->cardKey, (int) $spec->params['delta']) : $value;
            }
        });
        $registry->register(PartId::Forbid, new class implements ForbidPart {
            public function forbids(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
            {
                return $query->satelliteId === $host->satelliteId && $query->action?->value === ($spec->params['action'] ?? null);
            }
        });

        return $registry;
    }
}
