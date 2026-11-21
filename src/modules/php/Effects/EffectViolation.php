<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects;

/**
 * 窓の答えがルールを満たさないときの例外。状態 31 は、これを捕まえて穏当な文言でプレイヤーに返す（008 §2-5）。
 * サーバーは答えのたびに必ず検証し直す。
 */
final class EffectViolation extends \DomainException
{
    /**
     * @param string $message `clienttranslate()` で印を付けた英語の文言（`${name}` のプレースホルダーを含められる）
     * @param array<string, mixed> $args プレースホルダーに入れる値
     */
    public function __construct(string $message, private readonly array $args = [])
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function args(): array
    {
        return $this->args;
    }
}
