<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Machine;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Machine\FlowContext;
use Bga\Games\projectedgeworth\Flow\Machine\Machine;
use Bga\Games\projectedgeworth\Flow\Machine\PublicView;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Setup\StartingHand;
use PHPUnit\Framework\Attributes\DataProvider;
use ProjectEdgeworthTests\Support\SeededRandom;
use ProjectEdgeworthTests\Support\StateWalker;

/** 初期手札のドラフト（013）: 12 → 15 → 16 → 15 … → 17。 */
final class DraftTest extends MachineTestCase
{
    /** @var list<int> */
    private array $ids = [];

    /** @return array<string, array{int}> */
    public static function playerCounts(): array
    {
        return ['2 players' => [2], '3 players' => [3], '4 players' => [4]];
    }

    private function startDraft(int $count, int $seed = 5): int
    {
        $this->runSetup($count, [104 => 3]);
        $this->ids = array_map(static fn($p): int => $p->id, $this->players);
        $this->machine = new Machine(new FlowContext($this->db, $this->globals, new SeededRandom($seed), static fn(int $id): string => "Player$id"));
        $this->walker = new StateWalker($this->machine, StartingHand::Draft);
        $this->playerValues = new PlayerValues($this->db);
        $this->cards = new ProjectCardRepository($this->db, new SeededRandom(1));
        return $this->walker->run(StateId::SETUP_HAND_ROUTE);
    }

    /** @return list<int> */
    private function pack(int $playerId): array
    {
        return $this->cards->idsIn(CardLocation::Pack, $playerId);
    }

    /** 全員が確定して 16 へ進める。$pick(playerId): 選ぶカード（null は zombie）。次の入力待ちの状態を返す。 */
    private function round(callable $pick): int
    {
        $flow = $this->machine->choice(StateId::SETUP_DRAFT_PICK);
        foreach ($this->ids as $id) {
            $card = $pick($id);
            $card === null ? $flow->zombie($id) : $flow->confirm($id, ['card' => $card]);
        }
        return $this->walker->resume(StateId::SETUP_DRAFT_PICK, 'allConfirmed');
    }

    public function testSetupDealsIntoPacksAndRoutesToDraft(): void
    {
        $waiting = $this->startDraft(2);
        self::assertSame(StateId::SETUP_DRAFT_PICK, $waiting);
        self::assertSame([StateId::SETUP_HAND_ROUTE, StateId::SETUP_DRAFT_PICK], $this->walker->visited);
        foreach ($this->ids as $id) {
            self::assertCount(5, $this->pack($id));
            self::assertSame([], $this->hand($id));
        }
        $args = $this->machine->choice(StateId::SETUP_DRAFT_PICK)->args();
        self::assertSame(1, $args['pickNo']);
        self::assertSame(4, $args['pickTotal']);
        self::assertSame(5, $args['packSize']);
        self::assertSame($this->pack(101), $args['_private'][101]['pack']);
        self::assertSame([], $args['_private'][101]['hand']);
    }

    #[DataProvider('playerCounts')]
    public function testPacksCirculateToTheLeftAndTheLastCardIsReceived(int $count): void
    {
        $waiting = $this->startDraft($count);
        $initial = [];
        foreach ($this->ids as $seat => $id) {
            $initial[$seat] = $this->pack($id);
        }
        $all = array_merge(...$initial);
        for ($k = 1; $k <= 4; $k++) {
            self::assertSame(StateId::SETUP_DRAFT_PICK, $waiting);
            self::assertSame($k, $this->machine->choice(StateId::SETUP_DRAFT_PICK)->args()['pickNo']);
            $expected = [];
            foreach ($this->ids as $seat => $id) {
                $from = $initial[(($seat - ($k - 1)) % $count + $count) % $count];
                self::assertSame(array_values(array_intersect($this->pack($id), $from)), $this->pack($id), "round $k seat $seat");
                self::assertCount(6 - $k, $this->pack($id));
                $expected[$id] = $this->pack($id)[($k * 2) % (6 - $k)];
            }
            $waiting = $this->round(static fn(int $id): int => $expected[$id]);
        }
        self::assertNotSame(StateId::SETUP_DRAFT_PICK, $waiting);
        self::assertContains(StateId::SETUP_FINISH, $this->walker->visited);
        self::assertSame(4, count(array_filter($this->walker->visited, static fn(int $s): bool => $s === StateId::SETUP_DRAFT_PICK)));
        self::assertSame(4, count(array_filter($this->walker->visited, static fn(int $s): bool => $s === StateId::SETUP_DRAFT_PASS)));
        self::assertNotContains(StateId::SETUP_MULLIGAN, $this->walker->visited);

        $hands = [];
        foreach ($this->ids as $seat => $id) {
            self::assertCount(5, $this->hand($id));
            self::assertSame(0, $this->cards->countIn(CardLocation::Pack, $id));
            $hands = array_merge($hands, $this->hand($id));
        }
        self::assertEqualsCanonicalizing($all, $hands, 'cards are conserved');
        self::assertSame(99 - 5 * $count, $this->cards->countIn(CardLocation::Deck));

        $autos = array_values(array_filter($this->walker->noticesNamed('handCardsAdded'), static fn($n): bool => $n->args['auto'] === true));
        self::assertCount($count, $autos);
        foreach ($autos as $notice) {
            $seat = array_search($notice->to, $this->ids, true);
            self::assertContains($notice->args['cards'][0], $initial[(($seat - 4) % $count + $count) % $count]);
            self::assertSame('draft', $notice->args['reason']);
            self::assertSame('You receive ${card_name}, the last card passed to you', $notice->message);
        }
    }

    public function testValidation(): void
    {
        $this->startDraft(2);
        $flow = $this->machine->choice(StateId::SETUP_DRAFT_PICK);
        $other = $this->pack(102)[0];
        foreach ([['card' => $other], ['card' => 9999], [], ['card' => 'x']] as $data) {
            try {
                $flow->confirm(101, $data);
                self::fail('expected a RuleViolation');
            } catch (RuleViolation) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame([], $this->machine->choice(StateId::SETUP_DRAFT_PICK)->args()['confirmed']);
        // 選び直しできる
        $flow->confirm(101, ['card' => $this->pack(101)[1]]);
        $flow->confirm(101, ['card' => $this->pack(101)[2]]);
        self::assertSame(['card' => $this->pack(101)[2]], $this->machine->choice(StateId::SETUP_DRAFT_PICK)->args()['_private'][101]['myChoice']);
        // 次の回: 前の回に取って手札にあるカードは選べない
        $first = $this->pack(101)[0];
        $this->round(static fn(int $id): ?int => null);
        self::assertContains($first, $this->hand(101));
        $this->expectException(RuleViolation::class);
        $this->machine->choice(StateId::SETUP_DRAFT_PICK)->confirm(101, ['card' => $first]);
    }

    public function testZombieTakesTheFirstCardAndThePackStillPasses(): void
    {
        $this->startDraft(3);
        $first = [];
        foreach ($this->ids as $id) {
            $first[$id] = $this->pack($id)[0];
        }
        $before101 = $this->pack(101);
        $this->round(static fn(int $id): ?int => null);
        foreach ($this->ids as $id) {
            self::assertSame([$first[$id]], $this->hand($id));
        }
        // 101 のパックの残りは左隣 102 へ
        self::assertSame(array_slice($before101, 1), $this->pack(102));
    }

    public function testEnteringAgainClearsThePreviousConfirmation(): void
    {
        $this->startDraft(2);
        $flow = $this->machine->choice(StateId::SETUP_DRAFT_PICK);
        $flow->confirm(101, ['card' => $this->pack(101)[0]]);
        self::assertSame([101], $this->machine->choice(StateId::SETUP_DRAFT_PICK)->args()['confirmed']);
        $this->round(fn(int $id): int => $this->pack($id)[0]);
        $args = $this->machine->choice(StateId::SETUP_DRAFT_PICK)->args();
        self::assertSame([], $args['confirmed']);
        self::assertNull($args['_private'][101]['myChoice']);
        self::assertSame(2, $args['pickNo']);
        self::assertSame(4, $args['packSize']);
        self::assertSame([], $this->walker->staleArgsStates);
    }

    public function testSellingDuringDraft(): void
    {
        $this->startDraft(2);
        // 1 回目: 手札が空で、パックのカードは売れない
        try {
            $this->machine->sell(101, [$this->pack(101)[0]], StateId::SETUP_DRAFT_PICK);
            self::fail('pack cards cannot be sold');
        } catch (RuleViolation) {
            self::addToAssertionCount(1);
        }
        // 毎回、取ったカードを全部売っても進む（最後の 1 枚だけが手札に残る）
        for ($k = 1; $k <= 4; $k++) {
            $this->round(fn(int $id): int => $this->pack($id)[0]);
            if ($k === 4) {
                break;
            }
            foreach ($this->ids as $id) {
                $this->machine->sell($id, $this->hand($id), StateId::SETUP_DRAFT_PICK);
                self::assertSame([], $this->hand($id));
            }
        }
        foreach ($this->ids as $id) {
            self::assertCount(2, $this->hand($id), 'the last pick and the last card remain');
            self::assertSame(0, $this->cards->countIn(CardLocation::Pack, $id));
        }
        self::assertSame(6, $this->cards->countIn(CardLocation::Discard));
    }

    public function testPublicViewAndNoticesDoNotLeakPacks(): void
    {
        $this->startDraft(3);
        $view = new PublicView($this->machine->ctx);
        $mine = $view->build(101);
        self::assertSame($this->pack(101), $mine['myPack']);
        self::assertSame([], $view->build(999)['myPack']);
        foreach ([101, 102, 999] as $viewer) {
            foreach ($view->build($viewer)['playerData'] as $data) {
                self::assertSame(5, $data['packCount']);
                self::assertSame(0, $data['handCount']);
            }
        }
        $json = json_encode($this->machine->choice(StateId::SETUP_DRAFT_PICK)->args());
        $public = $this->machine->choice(StateId::SETUP_DRAFT_PICK)->args();
        unset($public['_private']);
        self::assertSame(['participants', 'confirmed', 'pickNo', 'pickTotal', 'packSize'], array_keys($public));
        self::assertIsString($json);

        $this->round(fn(int $id): int => $this->pack($id)[0]);
        $names = $this->walker->noticeNames();
        self::assertSame(['handCardsAdded', 'handCardsAdded', 'handCardsAdded'], array_slice(array_values(array_filter($names, static fn(string $n): bool => $n === 'handCardsAdded')), 0, 3));
        $passed = $this->walker->noticesNamed('draftPassed');
        self::assertCount(1, $passed);
        self::assertNull($passed[0]->to);
        self::assertSame(['pick_no' => 1, 'pick_total' => 4, 'last' => false], $passed[0]->args);
        $received = $this->walker->noticesNamed('draftPackReceived');
        self::assertCount(3, $received);
        foreach ($received as $notice) {
            self::assertNotNull($notice->to);
            self::assertSame($this->pack($notice->to), $notice->args['pack']);
            self::assertSame(2, $notice->args['pick_no']);
        }
        foreach ($this->walker->notices as $notice) {
            if ($notice->to === null) {
                self::assertArrayNotHasKey('cards', $notice->args, $notice->name);
                self::assertArrayNotHasKey('pack', $notice->args, $notice->name);
            }
        }
        $counters = $this->walker->noticesNamed('countersUpdate');
        self::assertSame(4, $counters[0]->args['players'][101]['packCount']);
        self::assertSame(1, $counters[0]->args['players'][101]['handCount']);
        // N9 は本人宛て 1 件ずつ。順序は N9 → N38 → N39 → N33
        $order = array_values(array_filter($names, static fn(string $n): bool => in_array($n, ['handCardsAdded', 'draftPassed', 'draftPackReceived', 'countersUpdate'], true)));
        self::assertSame(['handCardsAdded', 'handCardsAdded', 'handCardsAdded', 'draftPassed', 'draftPackReceived', 'draftPackReceived', 'draftPackReceived', 'countersUpdate'], $order);
    }

    public function testPacksOfDifferentSizeAreRejected(): void
    {
        $this->startDraft(2);
        $this->db->execute("UPDATE `project_card` SET `card_location` = 'discard', `card_location_arg` = 0 WHERE `card_id` = " . $this->pack(101)[0]);
        $this->expectException(\LogicException::class);
        $this->machine->choice(StateId::SETUP_DRAFT_PICK)->args();
    }
}
