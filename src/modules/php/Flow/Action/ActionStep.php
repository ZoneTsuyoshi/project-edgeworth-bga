<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

/**
 * 「全員が同時に選び、全員確定で一斉に処理する」ステップのルール（009 §5、008 §2-1）。
 * 計画フェイズの選択、II・III・IV のメイン / ボーナス、V の残すカードの選択がこの形。
 *
 * 選択内容（choice_data。010 §9.3）は配列で渡す。検証は確定のたびに行い、処理の直前にも行う。
 */
interface ActionStep
{
    /**
     * このステップの参加者（席順）。
     *
     * @return list<int>
     */
    public function participants(StepMode $mode): array;

    /**
     * 参加者のうち、実行できる行動があるか。なければ入場時に自動で [Pass] 相当を確定する（008 §2-7）。
     */
    public function canAct(int $playerId, StepMode $mode): bool;

    /**
     * 選択を検証する。満たさなければ RuleViolation。状態は変えない。
     *
     * @param array<mixed> $data
     * @throws RuleViolation
     */
    public function validate(int $playerId, StepMode $mode, array $data): void;

    /**
     * zombie・自動確定の既定の選択（009 §9.2）。
     *
     * @return array<mixed>
     */
    public function defaultChoice(int $playerId, StepMode $mode): array;

    /**
     * 全員分の確定内容を一斉に反映する。確定がない参加者は defaultChoice として扱う。
     *
     * @param array<int, array<mixed>> $choices プレイヤー ID => 確定内容
     */
    public function resolve(StepMode $mode, array $choices): StepResult;
}
