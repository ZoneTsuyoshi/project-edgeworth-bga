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
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE1;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Effects\Query\EngineActionHooks;
use Bga\Games\projectedgeworth\Effects\Query\EngineOrbitModifiers;
use Bga\Games\projectedgeworth\Effects\Query\ModifierQuery;
use Bga\Games\projectedgeworth\Flow\OrbitSatellite;
use Bga\Games\projectedgeworth\Material\CardEffectCatalog;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * E-1（常時の修正・禁止）以降のカードのテスト用に、本物のカード定義（`CardEffectCatalog`）と本物の部品・条件・盤面（Earth の標準面、
 * 4×2 ボード、P = 0）をつないだ問い合わせ一式を組み立てる。
 *
 * Earth の標準面の区域 ID（行 r、列 c → r × 12 + c）。地域は上段が左から A B C D（列 0〜2 が A、3〜5 が B …）、下段が E F G H。
 * 区域 29（B・海、x = 10、y = 2）はまわりに陸と海が混ざる: E 30 海、W 28 陸、NE 17 海、NW 16 陸、SE 41 海、SW 40 陸。
 */
final class E1Fixture
{
    /** B 地域の海。まわりの 6 区域が上のとおり */
    public const CELL_B_SEA = 29;
    /** 上端の行（y = 0）の B 地域の海。x = 8 */
    public const CELL_TOP = 4;

    public readonly FakeGame $game;
    public readonly BoardLayout $layout;
    public readonly EffectQueries $queries;
    public readonly EngineActionHooks $hooks;
    public readonly EngineOrbitModifiers $orbit;

    public function __construct()
    {
        $this->game = (new FakeGame())->player(1)->player(2);
        $this->layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $parts = PartsE1::register(new PartRegistry(), $this->game, $this->layout);
        $conditions = ConditionsE1::register(new ConditionEvaluator(), $this->game, $this->layout);
        $this->queries = new EffectQueries(new ModifierQuery($this->game, new CardEffectCatalog(), $parts, $conditions));
        $this->hooks = new EngineActionHooks($this->queries);
        $this->orbit = new EngineOrbitModifiers($this->queries);
    }

    /**
     * 衛星を足して ID を返す。`cardKey` は衛星カードの name_id（省略時は MOON 面）。配備するカードは `deploy()` で足す。
     */
    public function satellite(
        SatelliteShape $shape = SatelliteShape::Hammer,
        bool $active = true,
        ?string $cardKey = null,
        int $player = 1,
    ): int {
        return $this->game->satellite($player, $shape, $active, $cardKey);
    }

    /**
     * @param list<string> $cardIds
     */
    public function deploy(int $satId, array $cardIds): void
    {
        foreach ($cardIds as $cardId) {
            $this->game->deploy($satId, $cardId);
        }
    }

    /** `ActionHooks` が受け取る衛星の行（稼働中なら `$cell` の位置で `$dir` を向く） */
    public function row(int $satId, int $cell = self::CELL_B_SEA, Direction $dir = Direction::NE, bool $active = true, int $player = 1): Satellite
    {
        return new Satellite(
            $satId,
            $player,
            SatelliteColor::Red,
            SatelliteShape::Hammer,
            SatellitePlan::Moon,
            'R001R',
            $active ? $cell : null,
            $active ? $dir : null,
            0,
            false,
            0,
        );
    }

    /** 循環フェイズの衛星（移動後の位置） */
    public function orbitSat(int $satId, int $cell, Direction $dir = Direction::NE, int $player = 1): OrbitSatellite
    {
        return new OrbitSatellite($satId, $player, $cell, $dir, 0, 3);
    }
}
