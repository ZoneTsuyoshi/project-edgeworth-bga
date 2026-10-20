<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Bonus;

use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Material\ActionKind;
use Bga\Games\projectedgeworth\Material\Hook;

/**
 * EUROPA（R016〜R020）の宣言（窓 `XD`。007 未確定 6）の入口。各フェイズで、フェイズ開始効果の後・メインのステップの前に、
 * 条件を満たす全プレイヤーが同時に [Use] / [Skip] を決める。
 *
 * - `event()`: `BONUS_DECLARE` のイベント。全プレイヤーのプールに積む（`playerId` なし）。`chosenBy` はそのアクションを選んだプレイヤー
 *   （誰も選んでいなければ宣言できない。FAQ 行 62）、`holders` はすでにボーナスを得ているプレイヤー。
 * - `holders()`: 窓を閉じた後の、そのフェイズのボーナス保持者（自分で選んだ者 + 宣言した者）。`BUDGET_BONUS_GAIN` の `holders` や
 *   各ステップのボーナスの保持者に使う。宣言で得たボーナスにも P007・R012〜R015 は働く（007 未確定 2 の作者回答）。
 *   R011 だけは自分で選んだときに限る（`EngineActionHooks` が `GameView::declaredBonus()` で K12 に結ぶ）。
 */
final class BonusDeclaration
{
    private function __construct()
    {
    }

    /**
     * @param list<int> $chosenBy そのアクションを選んだプレイヤー
     */
    public static function event(ActionKind $action, array $chosenBy): GameEvent
    {
        return new GameEvent(Hook::BonusDeclare, null, null, null, null, null, 0, [
            'action' => $action->value,
            'chosenBy' => array_values($chosenBy),
            'holders' => array_values($chosenBy),
        ]);
    }

    /**
     * @param list<int> $chosenBy
     * @return list<int> 席順
     */
    public static function holders(GameView $view, ActionKind $action, array $chosenBy): array
    {
        return array_values(array_filter(
            $view->playerIds(),
            static fn(int $p): bool => in_array($p, $chosenBy, true) || $view->declaredBonus($p, $action->value),
        ));
    }
}
