<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

/**
 * 選択がルールを満たさないときの例外。状態クラスは、これを捕まえて穏当な文言でプレイヤーに返す（008 §2-5）。
 * サーバーは確定のたびに必ず検証し直す。クライアントの表示は補助。
 */
final class RuleViolation extends \DomainException
{
}
