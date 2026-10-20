<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * アクションフェイズの 2 つのステップ（001 §7-1）。メインは全員、ボーナスはそのアクションを自分で選んだプレイヤーだけ。
 */
enum StepMode: string
{
    case Main = 'main';
    case Bonus = 'bonus';
}
