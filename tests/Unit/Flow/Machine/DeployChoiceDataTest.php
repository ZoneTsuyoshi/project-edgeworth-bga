<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow\Machine;

use Bga\Games\projectedgeworth\Effects\Runtime\ActionEvents;
use Bga\Games\projectedgeworth\Flow\Action\StepOutcome;
use Bga\Games\projectedgeworth\Flow\Action\StepResult;
use Bga\Games\projectedgeworth\Flow\Machine\ChoiceData;
use Bga\Games\projectedgeworth\Material\Hook;
use PHPUnit\Framework\TestCase;

/** 配備の選択（通常の配備 + 追加の配備 / 1 枚引く。作者回答 2026-10-20）の choice_data と、DEPLOYED イベント。 */
final class DeployChoiceDataTest extends TestCase
{
    public function testMainOnlyKeepsTheOldShape(): void
    {
        self::assertSame(['op' => 'deploy', 'card' => 4, 'sat' => 11], ChoiceData::deploy('deploy', 4, 11, null));
        self::assertSame(['op' => 'pass'], ChoiceData::deploy('pass', 0, 0, null));
    }

    public function testExtraDeployCarriesItsOwnModsAndTheMainOnesStayApart(): void
    {
        $data = ChoiceData::deploy('deploy', 4, 11, [7], ['P:19'], ['op' => 'deploy', 'card' => '9', 'sat' => '12', 'useMods' => ['P:20']]);
        self::assertSame([
            'op' => 'deploy', 'card' => 4, 'sat' => 11, 'sell' => [7], 'useMods' => ['P:19'],
            'extra' => ['op' => 'deploy', 'card' => 9, 'sat' => 12, 'useMods' => ['P:20']],
        ], $data);
    }

    public function testExtraDrawAndPassWithAnExtra(): void
    {
        self::assertSame(['op' => 'pass', 'extra' => ['op' => 'draw']], ChoiceData::deploy('pass', 0, 0, null, null, ['op' => 'draw']));
        self::assertSame(['op' => 'pass'], ChoiceData::deploy('pass', 0, 0, null, null, []));
    }

    public function testTwoDeploymentsRaiseTwoDeployedEvents(): void
    {
        $result = new StepResult([
            new StepOutcome(101, 'deploy', false, ['card' => 4, 'sat' => 11]),
            new StepOutcome(101, 'deploy', false, ['card' => 9, 'sat' => 11]),
            new StepOutcome(101, 'draw', false, ['cards' => [50]]),
            new StepOutcome(102, 'pass'),
        ]);
        $events = ActionEvents::fromCommit(2, $result);
        self::assertCount(2, $events);
        self::assertSame(Hook::Deployed, $events[0]->hook);
        self::assertSame([4, 9], array_map(static fn($e): ?int => $e->cardId, $events));
        self::assertSame([101, 101], array_map(static fn($e): ?int => $e->playerId, $events));
    }
}
