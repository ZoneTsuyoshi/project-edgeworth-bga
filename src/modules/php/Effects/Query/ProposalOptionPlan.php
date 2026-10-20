<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Query;

/**
 * 企画の選択で使える効果の一覧（011 §6-5 の `ProposalPlan` の `options`）。修正部品 M07・M08 が畳み込む値。
 * `handDiscard` は P068（捨てるカードを手札からも選べる）が効いているか。
 */
final readonly class ProposalOptionPlan
{
    /**
     * @param list<ProposalOption> $options
     */
    public function __construct(
        public bool $handDiscard = false,
        public array $options = [],
    ) {
    }

    public function withHandDiscard(): self
    {
        return new self(true, $this->options);
    }

    public function withOption(ProposalOption $option): self
    {
        return new self($this->handDiscard, [...$this->options, $option]);
    }

    public function option(string $hostKey): ?ProposalOption
    {
        foreach ($this->options as $option) {
            if ($option->hostKey === $hostKey) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'handDiscard' => $this->handDiscard,
            'options' => array_map(static fn(ProposalOption $o): array => $o->toArray(), $this->options),
        ];
    }
}
