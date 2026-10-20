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
use Bga\Games\projectedgeworth\Flow\Machine\FlowContext;
use Bga\Games\projectedgeworth\Flow\Machine\Machine;
use Bga\Games\projectedgeworth\Setup\StartingHand;
use ProjectEdgeworthTests\Integration\Setup\SetupTestCase;
use ProjectEdgeworthTests\Support\SeededRandom;
use ProjectEdgeworthTests\Support\StateWalker;

/** 状態機械を通しで回す結合テストの共通部品。2 人（101・102）でセットアップ済みにして、Machine と StateWalker を用意する。 */
abstract class MachineTestCase extends SetupTestCase
{
    protected Machine $machine;
    protected StateWalker $walker;
    protected PlayerValues $playerValues;
    protected ProjectCardRepository $cards;

    protected function startGame(StartingHand $hand = StartingHand::None, int $seed = 5): void
    {
        $this->runSetup(2);
        $this->machine = new Machine(new FlowContext(
            $this->db,
            $this->globals,
            new SeededRandom($seed),
            static fn(int $id): string => "Player$id",
        ));
        $this->walker = new StateWalker($this->machine, $hand);
        $this->playerValues = new PlayerValues($this->db);
        $this->cards = new ProjectCardRepository($this->db, new SeededRandom(1));
    }

    /**
     * @return list<int>
     */
    protected function hand(int $playerId): array
    {
        return $this->cards->idsIn(CardLocation::Hand, $playerId);
    }
}
