<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow\Machine;

use Bga\Games\projectedgeworth\Flow\Machine\ChoiceData;
use Bga\Games\projectedgeworth\Flow\Machine\Notices;
use PHPUnit\Framework\TestCase;

/** 010 §10.2 の通知名・args（I-10 のクライアントとの約束）。 */
final class NoticesTest extends TestCase
{
    private function notices(): Notices
    {
        return new Notices(static fn(int $id): string => "Player$id");
    }

    public function testChoiceMarkedHasNoContent(): void
    {
        $n = $this->notices()->choiceMarked(5, 22);
        self::assertSame('choiceMarked', $n->name);
        self::assertSame(['player_id' => 5, 'state_id' => 22], $n->args);
        self::assertNull($n->to);
        self::assertSame('', $n->message);
    }

    public function testChoiceRecordedIsPrivate(): void
    {
        $n = $this->notices()->choiceRecorded(5, 22, ['action' => 3]);
        self::assertSame(5, $n->to);
        self::assertSame(['state_id' => 22, 'summary' => ['action' => 3]], $n->args);
    }

    public function testDrawIsAPairOfPublicCountAndPrivateIds(): void
    {
        [$public, $private] = $this->notices()->cardsDrawn(5, [7, 8], 'hand', 'bonus', 50, 3);
        self::assertSame('cardsDrawn', $public->name);
        self::assertNull($public->to);
        self::assertArrayNotHasKey('cards', $public->args);
        self::assertSame(2, $public->args['count']);
        self::assertSame(50, $public->args['deck_count']);
        self::assertSame('handCardsAdded', $private->name);
        self::assertSame(5, $private->to);
        self::assertSame([7, 8], $private->args['cards']);
        self::assertSame([], $this->notices()->cardsDrawn(5, [], 'hand', 'bonus', 50, 3));
    }

    public function testMayaChangedPicksMessageBySign(): void
    {
        $gain = $this->notices()->mayaChanged(5, 6, 26, 'bonus');
        $pay = $this->notices()->mayaChanged(5, -4, 22, 'cost');
        self::assertNotNull($gain);
        self::assertNotNull($pay);
        self::assertStringContainsString('gains', $gain->message);
        self::assertStringContainsString('pays', $pay->message);
        self::assertSame(4, $pay->args['amount']);
        self::assertSame(22, $pay->args['maya']);
        self::assertSame(['reason_label'], $pay->args['i18n']);
        self::assertNull($this->notices()->mayaChanged(5, 0, 22, 'cost'));
    }

    public function testActionPassedMessages(): void
    {
        self::assertStringContainsString('passes', $this->notices()->actionPassed(1, 'deploy', false)->message);
        self::assertStringContainsString('abandons', $this->notices()->actionPassed(1, 'link', false)->message);
        $auto = $this->notices()->actionPassed(1, 'link', true);
        self::assertStringContainsString('no available action', $auto->message);
        self::assertTrue($auto->args['auto']);
    }

    public function testCardAndActionNamesAreEnglish(): void
    {

        self::assertNotSame('', Notices::cardName(1));
        self::assertNotSame('', Notices::actionName(5));
    }

    public function testChoiceDataBuildsTheShapeOf010(): void
    {
        self::assertSame(
            ['op' => 'deploy', 'card' => 3, 'sat' => 11, 'sell' => [4]],
            ChoiceData::deploy('deploy', 3, 11, [4]),
        );
        self::assertSame(['op' => 'deploy', 'card' => 3, 'sat' => 11], ChoiceData::deploy('deploy', 3, 11, null));
        self::assertSame(['op' => 'draw'], ChoiceData::deploy('draw', 0, 0, null));
        self::assertSame(['op' => 'launch', 'sat' => 11, 'dir' => 'NE'], ChoiceData::launch('launch', 11, 'NE', []));
        self::assertSame(['op' => 'turn', 'sat' => 11, 'dir' => 'SW'], ChoiceData::launch('turn', 11, 'SW', [9]));
        self::assertSame(['op' => 'budget'], ChoiceData::launch('budget', 0, '', null));
        self::assertSame(['op' => 'link', 'cell' => 20, 'sat' => 11], ChoiceData::link('link', 20, 11, null));
        self::assertSame(['op' => 'pass'], ChoiceData::link('pass', 0, 0, null));
        self::assertSame([], ChoiceData::ids(null));
    }
}
