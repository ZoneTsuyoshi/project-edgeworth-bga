<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

/**
 * 001 §7-2 の例: 予算が 17、18、20、18 で、17 のプレイヤーが I を選んだ場合、23（= 17 + 6）、18、20、18 を得る。
 */
final class BudgetPhaseTest extends ActionTestCase
{
    public function testMainGivesBudgetToEveryoneAndBonusAddsSixToHolders(): void
    {
        $this->startGame(4);
        $budgets = [17, 18, 20, 18];
        foreach ($this->ids as $i => $id) {
            $this->phases->services()->players->setBudget($id, $budgets[$i]);
            $this->setMaya($id, 0);
        }
        $this->globals->setBonusHolders([1 => [101]]);

        $main = $this->phases->budget()->applyMain();
        $this->assertSame([101 => 17, 102 => 18, 103 => 20, 104 => 18], $main);
        $bonus = $this->phases->budget()->applyBonus();
        $this->assertSame([101 => 6], $bonus);

        $this->assertSame([23, 18, 20, 18], array_map(fn(int $id): int => $this->maya($id), $this->ids));
    }

    public function testBudgetChangesBeforeMainAreReflected(): void
    {
        $this->startGame(2);
        $this->setMaya(101, 0);
        $this->phases->services()->players->addBudget(101, 3);
        $this->phases->budget()->applyMain();
        $this->assertSame(18, $this->maya(101));
    }

    public function testHookAddsToMainOnlyNotToBonus(): void
    {
        $this->startGame(2);
        $this->setMaya(101, 0);
        $this->hooks->budgetExtra = 2;
        $this->globals->setBonusHolders([1 => [101]]);
        $this->phases->budget()->applyMain();
        $this->assertSame(17, $this->maya(101));
        $this->phases->budget()->applyBonus();
        $this->assertSame(23, $this->maya(101));
    }
}
