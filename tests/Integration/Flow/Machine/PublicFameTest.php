<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Machine;

use Bga\Games\projectedgeworth\Data\MissionRepository;
use Bga\Games\projectedgeworth\Flow\Machine\FlowContext;
use Bga\Games\projectedgeworth\Flow\Machine\Machine;
use Bga\Games\projectedgeworth\Flow\Machine\PublicView;
use Bga\Games\projectedgeworth\Flow\StateId;
use ProjectEdgeworthTests\Support\SeededRandom;
use ProjectEdgeworthTests\Support\StateWalker;

/** ゲームオプション 105（ミッション以外の名声を公開）。getAllDatas の publicFame と通知 N36（010）。 */
final class PublicFameTest extends MachineTestCase
{
    private function startVisible(): void
    {
        $this->startGame();
        $this->machine = new Machine(new FlowContext(
            $this->db,
            $this->globals,
            new SeededRandom(5),
            static fn(int $id): string => "Player$id",
            publicFame: true,
        ));
        $this->walker = new StateWalker($this->machine);
    }

    public function testHiddenHasNoPublicFameKeyAndNoNotice(): void
    {
        $this->startGame();
        $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        self::assertArrayNotHasKey('publicFame', (new PublicView($this->machine->ctx))->build(101));
        self::assertSame([], $this->walker->noticesNamed('publicFameUpdated'));
    }

    public function testVisibleReturnsFameWithoutMissionsAndSendsNotice(): void
    {
        $this->startVisible();
        $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        $this->playerValues->setMaya(101, 25);

        $view = (new PublicView($this->machine->ctx))->build(999);
        self::assertArrayHasKey('publicFame', $view);
        self::assertSame([101, 102], array_keys($view['publicFame']));
        foreach ($view['publicFame'] as $playerId => $fame) {
            self::assertSame(['total', 'detail'], array_keys($fame));
            self::assertArrayNotHasKey('V', $fame['detail']);
            self::assertSame(['total', 'I', 'II', 'III', 'IV', 'VI', 'VII', 'VIII', 'IX'], array_keys($fame['detail']));
            self::assertSame($fame['total'], $fame['detail']['total']);
            $sum = 0;
            foreach ($fame['detail'] as $key => $section) {
                $sum += $key === 'total' ? 0 : $section['points'];
            }
            self::assertSame($fame['total'], $sum);
            self::assertCount(2, (new MissionRepository($this->db))->forPlayer($playerId));
        }
        // 手札は換金しない: 25 マヤ → II = 2（手札 5 枚の 15 マヤは加えない）
        self::assertSame(['points' => 2, 'maya' => 25], $view['publicFame'][101]['detail']['II']);
        self::assertSame(0, $view['publicFame'][101]['detail']['IX']['points']);

        $notices = $this->walker->noticesNamed('publicFameUpdated');
        self::assertNotEmpty($notices);
        self::assertNull($notices[0]->to);
        self::assertSame('', $notices[0]->message);
        self::assertSame(['fame'], array_keys($notices[0]->args));
        self::assertSame([101, 102], array_keys($notices[0]->args['fame']));
    }
}
