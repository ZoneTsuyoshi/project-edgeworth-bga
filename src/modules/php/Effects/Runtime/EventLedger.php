<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Runtime;

use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Effects\Context\GameEvent;

/**
 * ドライバがコミットや公開で起こしたイベント（`DEPLOYED`・`LINKED`・`LANDED` など）を、窓を開くまで持つ台帳。
 * `flow_ctx` の `events` に JSON で保存するので、窓（30〜32）をまたいでも、再読み込みしても残る。
 * 窓の記号（`Hook::windowSymbol()`）ごとに取り出し、取り出した分は消える。フェイズが変わるとき `flow_ctx` ごと空になる（009 §6.3）。
 */
final class EventLedger
{
    public function __construct(private readonly GlobalValues $globals)
    {
    }

    public function record(GameEvent ...$events): void
    {
        if ($events === []) {
            return;
        }
        $context = $this->globals->flowContext();
        foreach ($events as $event) {
            $context['events'][] = $event->toArray();
        }
        $this->globals->setFlowContext($context);
    }

    /**
     * @return list<GameEvent>
     */
    public function peek(string $windowId): array
    {
        return array_map(
            static fn(array $a): GameEvent => GameEvent::fromArray($a),
            array_values(array_filter($this->rows(), static fn(array $a): bool => self::symbol($a) === $windowId)),
        );
    }

    /**
     * @return list<GameEvent>
     */
    public function take(string $windowId): array
    {
        $events = $this->peek($windowId);
        $this->discard($windowId);

        return $events;
    }

    public function discard(string $windowId): void
    {
        $context = $this->globals->flowContext();
        if (!isset($context['events'])) {
            return;
        }
        $context['events'] = array_values(array_filter($this->rows(), static fn(array $a): bool => self::symbol($a) !== $windowId));
        if ($context['events'] === []) {
            unset($context['events']);
        }
        $this->globals->setFlowContext($context);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = $this->globals->flowContext()['events'] ?? [];

        return array_values(array_map(static fn(mixed $r): array => (array) $r, (array) $rows));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function symbol(array $row): ?string
    {
        // 窓の記号が無い `ACTION_BONUS_STEP` は、台帳では 'AB' とする（`WindowEvents::ACTION_BONUS_WINDOW`）
        $hook = \Bga\Games\projectedgeworth\Material\Hook::from((string) $row['hook']);

        return $hook === \Bga\Games\projectedgeworth\Material\Hook::ActionBonusStep ? WindowEvents::ACTION_BONUS_WINDOW : $hook->windowSymbol();
    }
}
