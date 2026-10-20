<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Query;

/**
 * 企画（91）の残すカードの選択で使えるオプション 1 つ（011 §6-5）。P070 は `EXTRA_DISCARD`（手札から追加で 1 枚捨てて `reward` マヤ）、
 * P072 は `KEEP_PURCHASE`（`tiers` = 支払い額 => 残す枚数の増分）。使用済みの宿主は載せない。
 */
final readonly class ProposalOption
{
    public const EXTRA_DISCARD = 'EXTRA_DISCARD';
    public const KEEP_PURCHASE = 'KEEP_PURCHASE';

    /**
     * @param array<int, int> $tiers 支払い額 => 残す枚数の増分（KEEP_PURCHASE）
     */
    public function __construct(
        public string $hostKey,
        public string $cardKey,
        public string $kind,
        public int $reward = 0,
        public array $tiers = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['hostKey' => $this->hostKey, 'cardKey' => $this->cardKey, 'kind' => $this->kind, 'reward' => $this->reward, 'tiers' => $this->tiers];
    }
}
