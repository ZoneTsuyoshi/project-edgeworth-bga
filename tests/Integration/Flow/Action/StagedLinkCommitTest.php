<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Effects\Context\StagedLinkRequest;
use Bga\Games\projectedgeworth\Flow\Action\LinkCost;
use Bga\Games\projectedgeworth\Flow\Action\StagedLinkConverter;
use Bga\Games\projectedgeworth\Material\Hook;

/**
 * 効果による通信確立（R11）のステージを、本物の `LinkCommitter` と MariaDB でコミットする（007 §7-3、011 E-6）: 世代ごとのバリア、
 * 同じ世代の同じ区域は先駆コストなしで同じ置き場に重なる、次の世代は先駆コストを払う、払えない依頼は取りやめて `LINKED` を起こさない。
 */
final class StagedLinkCommitTest extends ActionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->startGame(3);
        foreach ($this->ids as $id) {
            $this->setMaya($id, 40);
        }
    }

    /** 区域の先駆コスト付きで、ステージ時（コミット前の盤面）のコストを作る */
    private function stage(int $playerId, int $satId, int $cell, bool $special): StagedLinkRequest
    {
        $services = $this->phases->services();
        $recipients = $this->phases->link()->pioneerRecipients($playerId, $cell);
        $cost = LinkCost::compute($services->layout->areaType($cell), 0, 0, $recipients, false, $special);

        return new StagedLinkRequest($playerId, $satId, $cell, 'P:50', $cost->toArray());
    }

    public function testSameGenerationStacksWithoutPioneerCostAndTheNextGenerationPaysIt(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $a = $this->sat(101, 0)->id;
        $b = $this->sat(102, 0)->id;
        $c = $this->sat(103, 0)->id;
        $committer = $this->phases->link()->committer();

        // 世代 1: 101 と 102 が同じ区域を同時にステージ（コストは区域 2 + 特殊 5 = 7。互いに先駆コストなし）
        $first = [$this->stage(101, $a, $sea, true), $this->stage(102, $b, $sea, true)];
        $commit = $committer->commit(StagedLinkConverter::requests($first));
        $events = StagedLinkConverter::events($commit, 1);

        $links = $this->phases->services()->links->inCell($sea);
        $this->assertSame([0, 0], array_map(static fn($l): int => $l->slot, $links), '同じ置き場に重なる');
        $this->assertSame(33, $this->maya(101));
        $this->assertSame(33, $this->maya(102));
        $this->assertSame([], $commit->pioneerReceived);
        $this->assertSame([Hook::Linked, Hook::Linked], array_map(static fn($e): Hook => $e->hook, $events));
        $this->assertSame([101, 102], array_map(static fn($e): ?int => $e->playerId, $events), '席順');
        $this->assertSame([$a, $b], array_map(static fn($e): ?int => $e->satelliteId, $events));
        $this->assertSame([$sea, $sea], array_map(static fn($e): ?int => $e->cell, $events));
        $this->assertSame([1, 1], array_map(static fn($e): int => $e->generation, $events));
        $this->assertTrue($events[0]->data['special']);

        // 世代 2: 103 が同じ区域をステージ。世代 1 のキューブがあるので先駆コスト 3 ずつ（2 + 6 + 5 = 13）
        $second = $this->stage(103, $c, $sea, true);
        $this->assertSame(13, LinkCost::fromArray($second->cost)->total());
        $commit = $committer->commit(StagedLinkConverter::requests([$second]));
        $this->assertSame(27, $this->maya(103));
        $this->assertSame(36, $this->maya(101));
        $this->assertSame(36, $this->maya(102));
        $this->assertSame([101 => 3, 102 => 3], $commit->pioneerReceived);
        $this->assertSame([0, 0, 2], array_map(static fn($l): int => $l->slot, $this->phases->services()->links->inCell($sea)));
        $this->assertCount(1, StagedLinkConverter::events($commit, 2));
    }

    public function testStagedLinkOnALaunchBaseCellStartsAtSlotOne(): void
    {
        // 作者回答 2026-10-20: 発射拠点が slot 0 を占める。効果による通信確立も slot 1 から
        $sea = $this->cellOf(AreaType::SEA);
        $this->pad(103, $sea);
        $a = $this->sat(101, 0)->id;
        $b = $this->sat(102, 0)->id;
        $committer = $this->phases->link()->committer();
        $committer->commit(StagedLinkConverter::requests([$this->stage(101, $a, $sea, true), $this->stage(102, $b, $sea, true)]));
        $this->assertSame([1, 1], array_map(static fn($l): int => $l->slot, $this->phases->services()->links->inCell($sea)));
    }

    public function testAnUnaffordableRequestIsDroppedAndRaisesNoLinkedEvent(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $a = $this->sat(101, 0)->id;
        $b = $this->sat(102, 0)->id;
        $this->setMaya(102, 6);                                   // 特殊通信確立は 7 マヤ
        $commit = $this->phases->link()->committer()->commit(StagedLinkConverter::requests([
            $this->stage(101, $a, $sea, true),
            $this->stage(102, $b, $sea, true),
        ]));
        $events = StagedLinkConverter::events($commit, 1);

        $this->assertCount(1, $events);
        $this->assertSame(101, $events[0]->playerId);
        $this->assertSame(6, $this->maya(102), '払えない依頼は何も払わない');
        $this->assertCount(1, $this->phases->services()->links->inCell($sea));
    }
}
