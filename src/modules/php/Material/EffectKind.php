<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 効果タイプ（002 §3.4）。
 */
enum EffectKind: string
{
    case Constant = 'CONSTANT'; // 常時
    case Activated = 'ACTIVATED'; // 起動（各ラウンド 1 回まで）
    case OnDeploy = 'ON_DEPLOY'; // 配備時
    case OnLaunch = 'ON_LAUNCH'; // 打ち上げ時
}
