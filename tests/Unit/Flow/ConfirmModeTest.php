<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow;

use Bga\Games\projectedgeworth\Flow\ConfirmAction;
use Bga\Games\projectedgeworth\Flow\ConfirmMode;
use PHPUnit\Framework\TestCase;

/** 009 §5.3: 案 A / 案 B の差は plan() だけに閉じ込める。 */
final class ConfirmModeTest extends TestCase
{
    public function testPlanB(): void
    {
        self::assertSame(ConfirmAction::DeactivatePlayer, ConfirmMode::plan(true, false, ConfirmMode::MODE_B));
        self::assertSame(ConfirmAction::DeactivatePlayer, ConfirmMode::plan(true, true, ConfirmMode::MODE_B));
        self::assertSame(ConfirmAction::None, ConfirmMode::plan(false, false, ConfirmMode::MODE_B));
        self::assertSame(ConfirmAction::None, ConfirmMode::plan(false, true, ConfirmMode::MODE_B));
    }

    public function testPlanA(): void
    {
        self::assertSame(ConfirmAction::None, ConfirmMode::plan(true, false, ConfirmMode::MODE_A));
        self::assertSame(ConfirmAction::None, ConfirmMode::plan(false, false, ConfirmMode::MODE_A));
        self::assertSame(ConfirmAction::DeactivateAll, ConfirmMode::plan(true, true, ConfirmMode::MODE_A));
        self::assertSame(ConfirmAction::DeactivateAll, ConfirmMode::plan(false, true, ConfirmMode::MODE_A));
    }

    public function testDefaultIsPlanB(): void
    {
        self::assertSame(ConfirmMode::MODE_B, ConfirmMode::CURRENT);
    }
}
