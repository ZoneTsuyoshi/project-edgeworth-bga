<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 起動の修正（USE_MODS）を、問い合わせの間だけ効かせられるフック。配備では 1 枚ごとに使う鍵を切り替える
 * （通常の配備と追加の配備で、同じ起動を 2 枚に二重に効かせない。008 §3-6）。
 */
interface UseModsAware
{
    /**
     * 以後の問い合わせで使う起動の修正の宿主の鍵。空配列で解除する。
     *
     * @param list<string> $hostKeys
     */
    public function useMods(array $hostKeys): void;
}
