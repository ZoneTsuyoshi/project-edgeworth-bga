<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 禁止効果（011 の F01 `FORBID`）が止める行動。既定の実装では何も禁止しない。
 */
enum ForbiddenAction: string
{
    /** この衛星にプロジェクトカードを配備できない（P033 など） */
    case DeployTo = 'DEPLOY_TO_THIS';
    /** この衛星は転回できない（反射はする。FAQ 行 54） */
    case Turn = 'TURN_THIS';
    /** この衛星は通信確立できない（FAQ 行 56） */
    case Link = 'LINK_THIS';
}
