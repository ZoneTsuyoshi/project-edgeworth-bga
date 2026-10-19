<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects;

/**
 * 窓の答えがルールを満たさないときの例外。状態 31 は、これを捕まえて穏当な文言でプレイヤーに返す（008 §2-5）。
 * サーバーは答えのたびに必ず検証し直す。
 */
final class EffectViolation extends \DomainException
{
}
