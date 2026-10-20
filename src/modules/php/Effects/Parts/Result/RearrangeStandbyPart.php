<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Result;

use Bga\Games\ProjectEdgeworth\Data\ProjectCardRepository;
use Bga\Games\ProjectEdgeworth\Effects\LogEntry;
use Bga\Games\ProjectEdgeworth\Effects\Outcome;
use Bga\Games\ProjectEdgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartCall;
use Bga\Games\ProjectEdgeworth\Effects\Query\EffectQueries;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\Field;
use Bga\Games\ProjectEdgeworth\Effects\Question\FieldType;
use Bga\Games\ProjectEdgeworth\Effects\Question\ValidationResult;
use Bga\Games\ProjectEdgeworth\Flow\Action\ForbiddenAction;

/**
 * R12 `REARRANGE_STANDBY`（P022）: 自分の待機中（非稼働）の衛星に配備されているプロジェクトカードを、好きなように付け替える（強制）。
 * 配備イベントは起こさず、配備時の効果・起動の反応効果は発動しない（FAQ 行 9）。待機中の衛星に配備されているカードは P022 自身を含めて全て対象
 * （011 未確定 No.5）。対象は非稼働の衛星のカードだけで、稼働中の衛星のカードは動かせない（FAQ 行 10）。
 *
 * 問い `assignment`: カード番号 => 置き先の衛星 ID（対象のカード全てについて）。検証:
 * 全てのカードが過不足なく割り当てられ、置き先は待機中の自分の衛星、1 機 4 枚まで、配備できない衛星（F01 `DEPLOY_TO_THIS`、P033）へは
 * 新たに動かせない（もともとそこにあるカードは動かさなくてよい。付け替え前の状態で判定する）。
 * 衛星に残るカードの順は変えず、動いてきたカードは番号順に上に積む。zombie の既定は現在の配置のまま。
 */
final class RearrangeStandbyPart extends AbstractResultPart
{
    public function __construct(private readonly EffectQueries $queries)
    {
    }

    public function isAvailable(PartCall $call): bool
    {
        return $this->current($call) !== [];
    }

    public function buildFields(PartCall $call): array
    {
        $current = $this->current($call);
        $candidates = [];
        foreach ($current as $cardNo => $satelliteId) {
            $candidates[] = ['card' => $cardNo, 'sat' => $satelliteId];
        }

        return [new Field('assignment', FieldType::Assignment, $candidates, 0, 0, $current)];
    }

    public function validate(PartCall $call, Answer $answer): ValidationResult
    {
        $assignment = $this->normalize($answer->fields['assignment'] ?? null);
        $current = $this->current($call);
        if ($assignment === null || array_keys($assignment) !== array_keys($current)) {
            return ValidationResult::error('every card on a standby satellite must be assigned exactly once');
        }
        $standby = $this->standbyIds($call);
        $counts = [];
        foreach ($assignment as $cardNo => $satelliteId) {
            if (!in_array($satelliteId, $standby, true)) {
                return ValidationResult::error('cards can only be placed on your standby satellites');
            }
            $counts[$satelliteId] = ($counts[$satelliteId] ?? 0) + 1;
            if (
                $satelliteId !== $current[$cardNo]
                && !$this->queries->allowed(new QueryContext($call->host->playerId, $satelliteId, action: ForbiddenAction::DeployTo))
            ) {
                return ValidationResult::error('cards cannot be placed on this satellite');
            }
        }
        foreach ($counts as $count) {
            if ($count > ProjectCardRepository::MAX_DEPLOYED) {
                return ValidationResult::error('a satellite holds up to 4 cards');
            }
        }

        return ValidationResult::ok();
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $assignment = $this->normalize($answer?->fields['assignment'] ?? null) ?? $this->current($call);
        $view = $call->ctx->view;
        $placement = [];
        foreach ($this->standbyIds($call) as $satelliteId) {
            $staying = array_values(array_filter(
                $view->deployedCards($satelliteId),
                static fn(int $cardNo): bool => ($assignment[$cardNo] ?? null) === $satelliteId,
            ));
            $arriving = array_keys(array_filter($assignment, static fn(int $to, int $cardNo): bool => $to === $satelliteId && !in_array($cardNo, $staying, true), ARRAY_FILTER_USE_BOTH));
            sort($arriving);
            $placement[$satelliteId] = [...$staying, ...$arriving];
        }
        // 燃料庫の数が変わりうる衛星（待機中は c = 0 なので実際には動かないが、規則どおり包む。N-1）
        FuelAdjust::aroundMany($call->ctx, array_keys($placement), static function () use ($call, $placement): void {
            $call->ctx->mutator->rearrangeDeployed($placement);
        });

        return new Outcome([], [new LogEntry('cardsRearranged', [
            'player_id' => $call->host->playerId,
            'card_key' => $call->spec->cardKey,
            'placement' => $placement,
        ])]);
    }

    public function defaultAnswer(PartCall $call): ?Answer
    {
        return new Answer(['assignment' => $this->current($call)]);
    }

    public function preview(PartCall $call): array
    {
        return ['satellites' => $this->standbyIds($call)];
    }

    /**
     * @return list<int> 自分の待機中（非稼働）の衛星
     */
    private function standbyIds(PartCall $call): array
    {
        $view = $call->ctx->view;
        $result = [];
        foreach ($view->satelliteIds($call->host->playerId) as $id) {
            $sat = $view->satelliteState($id);
            if ($sat !== null && !$sat->isActive()) {
                $result[] = $id;
            }
        }

        return $result;
    }

    /**
     * いまの配置: カード番号 => 衛星 ID（待機中の衛星のカード全て。カード番号の昇順）。
     *
     * @return array<int, int>
     */
    private function current(PartCall $call): array
    {
        $current = [];
        foreach ($this->standbyIds($call) as $satelliteId) {
            foreach ($call->ctx->view->deployedCards($satelliteId) as $cardNo) {
                $current[$cardNo] = $satelliteId;
            }
        }
        ksort($current);

        return $current;
    }

    /**
     * 答えの `assignment`（JSON で鍵が文字列になる）をカード番号 => 衛星 ID にそろえる。形が違えば null。
     *
     * @return array<int, int>|null
     */
    private function normalize(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $result = [];
        foreach ($value as $cardNo => $satelliteId) {
            if (!is_numeric($cardNo) || !is_numeric($satelliteId)) {
                return null;
            }
            $result[(int) $cardNo] = (int) $satelliteId;
        }
        ksort($result);

        return $result;
    }
}
