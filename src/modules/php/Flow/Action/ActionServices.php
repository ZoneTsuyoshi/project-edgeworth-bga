<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Board\RandomSource;
use Bga\Games\projectedgeworth\Data\ActionCardRepository;
use Bga\Games\projectedgeworth\Data\ChoiceRepository;
use Bga\Games\projectedgeworth\Data\Database;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;
use Bga\Games\projectedgeworth\Data\RegionRepository;
use Bga\Games\projectedgeworth\Data\SatelliteRepository;
use Bga\Games\projectedgeworth\Material\ProjectCardDefinition;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\SatelliteCards;
use Bga\Games\projectedgeworth\Setup\LaunchPadPlacement;

/**
 * 計画・アクションフェイズのルールが共有する部品（リポジトリ・盤面・フック）。BGA の Table に依存しない。
 * 状態クラス（I-07）は ActionServices::load() で 1 つ作り、ActionPhases に渡す。
 */
final class ActionServices
{
    public readonly SellRules $selling;

    public function __construct(
        public readonly GlobalValues $globals,
        public readonly PlayerValues $players,
        public readonly ProjectCardRepository $cards,
        public readonly SatelliteRepository $satellites,
        public readonly LinkRepository $links,
        public readonly ActionCardRepository $actionCards,
        public readonly ChoiceRepository $choices,
        public readonly BoardLayout $layout,
        public readonly ActionHooks $hooks,
    ) {
        $this->selling = new SellRules($cards, $players, $hooks);
    }

    /**
     * 保存済みの盤面（region・board_face）を復元して作る。
     */
    public static function load(
        Database $db,
        GlobalValues $globals,
        RandomSource $random,
        ?ActionHooks $hooks = null,
    ): self {
        return new self(
            $globals,
            new PlayerValues($db),
            new ProjectCardRepository($db, $random),
            new SatelliteRepository($db),
            new LinkRepository($db),
            new ActionCardRepository($db),
            new ChoiceRepository($db),
            LaunchPadPlacement::loadLayout(new RegionRepository($db), $globals),
            $hooks ?? new NoActionHooks(),
        );
    }

    /**
     * プレイヤー ID を席順に並べ替える（重複は除く）。
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public function seatOrder(array $ids): array
    {
        return array_values(array_filter($this->players->playerIds(), static fn(int $id): bool => in_array($id, $ids, true)));
    }

    /**
     * アクション選択ボーナスを持つプレイヤー（席順）。
     *
     * @return list<int>
     */
    public function bonusHolders(int $actionNo): array
    {
        return $this->seatOrder($this->globals->bonusHolders()[$actionNo] ?? []);
    }

    /**
     * 燃料の容量 C = 衛星カードの燃料庫 + 配備されたカードの燃料庫（001 §4-4）。
     */
    public function fuelCapacity(int $satId): int
    {
        $sat = $this->satellites->get($satId);
        $capacity = SatelliteCards::get($sat->cardId)->fuelSlots;
        foreach ($this->cards->deployedIds($satId) as $cardId) {
            $capacity += self::project($cardId)->fuelSlots;
        }
        return $capacity;
    }

    public static function project(int $cardNumber): ProjectCardDefinition
    {
        return ProjectCards::get(sprintf('P%03d', $cardNumber));
    }
}
