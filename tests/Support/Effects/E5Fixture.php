<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\ProjectEdgeworth\Board\BoardFace;
use Bga\Games\ProjectEdgeworth\Board\BoardLayout;
use Bga\Games\ProjectEdgeworth\Board\BoardLayoutBuilder;
use Bga\Games\ProjectEdgeworth\Board\BoardPlacement;
use Bga\Games\ProjectEdgeworth\Board\Direction;
use Bga\Games\ProjectEdgeworth\Data\Satellite;
use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionsE1;
use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionsE2;
use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionsE3;
use Bga\Games\ProjectEdgeworth\Effects\EffectEngine;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartRegistry;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE1;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE2;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE3;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE4;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE5;
use Bga\Games\ProjectEdgeworth\Effects\Query\EffectQueries;
use Bga\Games\ProjectEdgeworth\Effects\Query\EngineActionHooks;
use Bga\Games\ProjectEdgeworth\Effects\Query\ModifierQuery;
use Bga\Games\ProjectEdgeworth\Effects\Query\ProposalOptions;
use Bga\Games\ProjectEdgeworth\Effects\Query\UseMods;
use Bga\Games\ProjectEdgeworth\Material\CardEffectCatalog;
use Bga\Games\ProjectEdgeworth\Material\SatelliteColor;
use Bga\Games\ProjectEdgeworth\Material\SatellitePlan;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * E-5 のカードのテスト用に、本物のカード定義・E-1〜E-5 の部品と条件・盤面（Earth の標準面）・問い合わせを `FakeGame` につなぐ。
 * プレイヤー 1・2、山札は 101 から。衛星は `place()` で位置・向き・燃料庫を与えて稼働させる。
 *
 * 盤面（Earth の標準面、4 枚）の区域 ID の目安: 行 r・列 c は r × 12 + c。(x, y) は偶数行が x = 2c、奇数行が x = 2c + 1。
 * 地域は A が 0〜2・12〜14・24〜26、B が 3〜5・15〜17・27〜29。例: 14 (5,1) から北東へ進むと 3 (6,0) で A → B。
 */
final class E5Fixture
{
    public readonly FakeGame $game;
    public readonly BoardLayout $layout;
    public readonly PartRegistry $parts;
    public readonly ConditionEvaluator $conditions;
    public readonly EffectQueries $queries;
    public readonly EngineActionHooks $hooks;
    public readonly UseMods $useMods;
    public readonly ProposalOptions $proposal;

    public function __construct(int $maya = 20)
    {
        $this->game = (new FakeGame())->player(1, $maya)->player(2, $maya);
        $this->game->deck = [101, 102, 103, 104, 105, 106, 107];
        $this->layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $this->parts = PartsE3::register(PartsE2::register(PartsE1::register(new PartRegistry(), $this->game, $this->layout)));
        $this->conditions = ConditionsE3::register(
            ConditionsE2::register(ConditionsE1::register(new ConditionEvaluator(), $this->game, $this->layout), $this->game),
            $this->layout,
        );
        $catalog = new CardEffectCatalog();
        $modifiers = new ModifierQuery($this->game, $catalog, $this->parts, $this->conditions);
        $this->queries = new EffectQueries($modifiers);
        PartsE4::register($this->parts, $this->layout, $this->queries);
        $this->hooks = new EngineActionHooks($this->queries, $this->game);
        $this->useMods = new UseMods($modifiers, $this->queries, $this->game, $this->game);
        PartsE5::register($this->parts, $this->queries, $this->useMods);
        $this->proposal = new ProposalOptions($this->game, $this->game, $catalog, $this->parts);
    }

    public function engine(): EffectEngine
    {
        return new EffectEngine($this->game, $this->game, $this->parts, new CardEffectCatalog(), $this->conditions);
    }

    /**
     * 衛星を足して ID を返す。`cardKey` は衛星カードの name_id（省略時は効果のない名前）。
     *
     * @param list<string> $cards 配備するプロジェクトカード（'P013' など）
     */
    public function satellite(int $player = 1, array $cards = [], bool $active = true, ?string $cardKey = null, SatelliteShape $shape = SatelliteShape::Hammer): int
    {
        $id = $this->game->satellite($player, $shape, $active, $cardKey);
        foreach ($cards as $card) {
            $this->game->deploy($id, $card);
        }

        return $id;
    }

    /**
     * 衛星を稼働させて（位置・向き・衛星カードの燃料庫の数・消費量）、ID を返す。
     *
     * @param list<string> $cards 配備するプロジェクトカード
     */
    public function active(int $cell, Direction $dir, array $cards = [], int $baseFuel = 4, int $used = 0, int $player = 1): int
    {
        $id = $this->satellite($player, $cards);
        $this->game->place($id, $cell, $dir, $baseFuel, $used);

        return $id;
    }

    /** 地域 `$region` の陸（`$land` 真）か海の区域を 1 つ返す */
    public function cell(string $region, bool $land): int
    {
        foreach ($this->layout->cellIds() as $cell) {
            $type = $this->layout->areaType($cell);
            if ($this->layout->region($cell)->value === $region && ($land ? $type->isLand() : $type->isSea())) {
                return $cell;
            }
        }
        throw new \LogicException("no cell in $region");
    }

    /** `ActionHooks` が受け取る衛星の行 */
    public function row(int $satId, int $cell, bool $active = true, int $player = 1): Satellite
    {
        return new Satellite(
            $satId,
            $player,
            SatelliteColor::Red,
            SatelliteShape::Hammer,
            SatellitePlan::Moon,
            'R001R',
            $active ? $cell : null,
            $active ? Direction::NE : null,
            0,
            false,
            0,
        );
    }
}
