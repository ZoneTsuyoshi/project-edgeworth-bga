<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Board\BoardLayoutBuilder;
use Bga\Games\projectedgeworth\Board\BoardPlacement;
use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Data\Satellite;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionsE1;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionsE2;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionsE3;
use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\Context\StagedLinkRequest;
use Bga\Games\projectedgeworth\Effects\EffectEngine;
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE1;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE2;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE3;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE4;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE5;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE6;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Effects\Query\EngineActionHooks;
use Bga\Games\projectedgeworth\Effects\Query\ModifierQuery;
use Bga\Games\projectedgeworth\Effects\Query\ProposalOptions;
use Bga\Games\projectedgeworth\Effects\Query\UseMods;
use Bga\Games\projectedgeworth\Flow\Action\StagedLinkConverter;
use Bga\Games\projectedgeworth\Material\CardEffectCatalog;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * E-6 のカードのテスト用に、本物のカード定義・E-1〜E-6 の部品と条件・盤面（Earth の標準面）・問い合わせを `FakeGame` につなぐ。
 * プレイヤー 1・2、山札は 101 から。衛星は `place()` で位置・向き・燃料庫を与えて稼働させる。
 *
 * 盤面（Earth の標準面、4 枚）の区域 ID の目安: 行 r・列 c は r × 12 + c。(x, y) は偶数行が x = 2c、奇数行が x = 2c + 1。
 * 地域は A が 0〜2・12〜14・24〜26、B が 3〜5・15〜17・27〜29。例: 14 (5,1) から北東へ進むと 3 (6,0) で A → B。
 */
final class E6Fixture
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
        PartsE6::register($this->parts, $this->queries, $this->useMods, $this->layout);
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

    /**
     * 状態 32 のバリアの 1 周を再現する: ステージされた通信確立を、`StagedLinkConverter` で作った依頼のとおりに払って（先駆コストの受け取りを含む）
     * 置き、コミットで起きた `LINKED`（世代 `$generation`）をエンジンに積む。払えない依頼は取りやめる（本物の `LinkCommitter` と同じ）。
     *
     * @param list<StagedLinkRequest> $staged
     */
    public function commit(EffectEngine $engine, array $staged, int $generation): void
    {
        $credits = [];
        $placed = [];
        foreach (StagedLinkConverter::requests($staged) as $request) {
            if ($this->game->maya($request->playerId) < $request->cost->total()) {
                continue;
            }
            $this->game->payMaya($request->playerId, $request->cost->total());
            foreach ($request->cost->pioneer as $share) {
                $credits[$share['to']] = ($credits[$share['to']] ?? 0) + $share['amount'];
            }
            $placed[] = $request;
        }
        foreach ($placed as $request) {
            $this->game->linkedCells[$request->playerId][] = $request->cell;
        }
        foreach ($credits as $playerId => $amount) {
            $this->game->gainMaya($playerId, $amount);
        }
        foreach ($placed as $request) {
            $engine->collect(new GameEvent(Hook::Linked, $request->playerId, $request->satelliteId, cell: $request->cell, generation: $generation));
        }
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
