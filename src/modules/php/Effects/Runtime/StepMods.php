<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Runtime;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\EffectViolation;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\ProjectCards;

/**
 * 起動の修正（`USE_MODS`。P019〜P021・P036・R021〜R025。011 §6-3）を、状態 61・71・81・82 の選択に同梱するための入口。
 *
 * - `options()`: その選択（配備のカードと配備先、打ち上げる衛星、通信確立の区域と衛星）で使える起動の修正の候補。args に載せる。
 * - `validate()`: 確定時に、選択の `useMods`（宿主の鍵の配列）を検証する。候補にない鍵・重複は `RuleViolation`。
 * 使用済みを付けるのは、そのコストを実際に払うコミットの時点（`PhaseDrivers`）。
 */
final class StepMods
{
    public function __construct(private readonly EffectRuntime $runtime)
    {
    }

    /**
     * @param int $actionNo 2 = 配備、3 = 打ち上げ、4 = 通信確立
     * @param array<mixed> $subject 配備: card・sat・pending（同じ選択で先に同じ衛星へ配備する枚数）、打ち上げ: sat、通信確立: cell・sat
     * @return list<array{hostKey: string, cardKey: string, reduction: int}>
     */
    public function options(int $actionNo, int $playerId, array $subject): array
    {
        $services = $this->runtime->game->s();
        try {
            $sat = $services->satellites->get((int) ($subject['sat'] ?? 0));
        } catch (\InvalidArgumentException) {
            return [];
        }
        if ($sat->playerId !== $playerId) {
            return [];
        }
        $mods = $this->runtime->useMods;
        $attached = $services->cards->countIn(CardLocation::Satellite, $sat->id) + ($actionNo === 2 ? (int) ($subject['pending'] ?? 0) : 0);
        if ($actionNo === 2) {
            $card = (int) ($subject['card'] ?? 0);
            if ($card < 1 || $card > 99) {
                return [];
            }
            $def = ProjectCards::get(sprintf('P%03d', $card));
            $q = new QueryContext($playerId, $sat->id, $card, $def->type, attachedCount: $attached);
            $self = new EffectHost('P', $card, $def->nameId, $playerId, $sat->id, $sat->shape, 0, $sat->isActive(), false);

            return $mods->options(Hook::DeployCost, $q, $self);
        }
        if ($actionNo === 3) {
            return $mods->options(Hook::LaunchCost, new QueryContext($playerId, $sat->id, attachedCount: $attached));
        }
        if ($actionNo === 4) {
            return $mods->options(Hook::LinkCost, new QueryContext($playerId, $sat->id, cell: (int) ($subject['cell'] ?? 0)));
        }

        return [];
    }

    /**
     * 確定内容の `useMods` を検証して返す（なければ空）。
     *
     * @param array<mixed> $data
     * @return list<string>
     * @throws RuleViolation
     */
    public function validate(int $actionNo, int $playerId, array $data): array
    {
        $chosen = $data['useMods'] ?? [];
        if (!is_array($chosen) || $chosen === []) {
            return [];
        }
        $expected = [2 => 'deploy', 3 => 'launch', 4 => 'link'][$actionNo] ?? null;
        if (($data['op'] ?? null) !== $expected) {
            throw new RuleViolation('invalid modifier to use');
        }
        try {
            return $this->runtime->useMods->validate(array_values($chosen), $this->options($actionNo, $playerId, $data));
        } catch (EffectViolation $violation) {
            throw new RuleViolation($violation->getMessage());
        }
    }

    /**
     * 配備の確定内容（通常の配備と、ボーナス保持者の追加の配備）の `useMods` を、枚ごとに検証して全部の鍵を返す。
     * 同じ鍵を 2 枚に使うことはできない（起動は各ラウンド 1 回）。追加の配備が同じ衛星のときは、先の 1 枚を付いている枚数に数える。
     *
     * @param array<mixed> $data
     * @return list<string>
     * @throws RuleViolation
     */
    public function validateDeploy(int $playerId, array $data): array
    {
        $keys = $this->validate(2, $playerId, $data);
        $extra = $data['extra'] ?? null;
        if (is_array($extra) && ($extra['useMods'] ?? []) !== []) {
            $same = ($data['op'] ?? null) === 'deploy' && ($data['sat'] ?? null) === ($extra['sat'] ?? false);
            $more = $this->validate(2, $playerId, $extra + ['pending' => $same ? 1 : 0]);
            if (array_intersect($keys, $more) !== []) {
                throw new RuleViolation('invalid modifier to use');
            }
            $keys = array_merge($keys, $more);
        }

        return $keys;
    }

    /**
     * args 用: 候補と、使った場合のコスト（使わない場合の合計 `$total` から、その修正だけ / 全部使った場合を引く。下限 0）。
     *
     * @param list<array{hostKey: string, cardKey: string, reduction: int}> $options
     * @return array{options: list<array<string, mixed>>, all: int}
     */
    public static function describe(array $options, int $total): array
    {
        return [
            'options' => array_map(
                static fn(array $o): array => $o + ['cost' => max(0, $total - $o['reduction'])],
                $options,
            ),
            'all' => max(0, $total - array_sum(array_column($options, 'reduction'))),
        ];
    }
}
