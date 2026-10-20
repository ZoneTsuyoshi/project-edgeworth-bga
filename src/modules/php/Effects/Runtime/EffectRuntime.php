<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Runtime;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Data\Database;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Data\RegionRepository;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionsE1;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionsE2;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionsE3;
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
use Bga\Games\projectedgeworth\Effects\Query\EngineOrbitModifiers;
use Bga\Games\projectedgeworth\Effects\Query\ModifierQuery;
use Bga\Games\projectedgeworth\Effects\Query\ProposalOptions;
use Bga\Games\projectedgeworth\Effects\Query\UseMods;
use Bga\Games\projectedgeworth\Flow\Action\ActionServices;
use Bga\Games\projectedgeworth\Flow\Machine\WindowGate;
use Bga\Games\projectedgeworth\Material\CardEffectCatalog;
use Bga\Games\projectedgeworth\Setup\LaunchPadPlacement;

/**
 * 本番の効果エンジンの組み立て（申し送り N-7）: 全カードの定義（`CardEffectCatalog`）、E-1〜E-6 の部品と条件の登録関数のすべて、
 * 問い合わせ（`EffectQueries`）、`ActionHooks`・`OrbitModifiers`、起動の修正（`UseMods`）、企画のオプション、窓のゲート。
 *
 * `ActionServices` は `ActionHooks`（= この組み立ての `hooks`）を要るので、`FlowContext` が `ActionServices` を作ってから `attach()` で結ぶ。
 * テストは `FakeGame` を使う（`tests/Support/Effects`）。ここは `Data/` のリポジトリにつなぐ。
 */
final class EffectRuntime
{
    public readonly DbGame $game;
    public readonly BoardLayout $layout;
    public readonly PartRegistry $parts;
    public readonly ConditionEvaluator $conditions;
    public readonly CardEffectCatalog $catalog;
    public readonly EffectQueries $queries;
    public readonly EngineActionHooks $hooks;
    public readonly EngineOrbitModifiers $orbitMods;
    public readonly UseMods $useMods;
    public readonly ProposalOptions $proposal;
    public readonly StepMods $stepMods;
    public readonly EventLedger $ledger;
    public readonly WindowEvents $events;
    public readonly WindowStore $store;
    public readonly WindowGate $gate;
    private ?EffectEngine $engine = null;

    public function __construct(public readonly Database $db, public readonly GlobalValues $globals, ?BoardLayout $layout = null)
    {
        $this->layout = $layout ?? LaunchPadPlacement::loadLayout(new RegionRepository($db), $globals);
        $this->game = new DbGame();
        $this->catalog = new CardEffectCatalog();
        $this->parts = PartsE3::register(PartsE2::register(PartsE1::register(new PartRegistry(), $this->game, $this->layout)));
        $this->conditions = ConditionsE3::register(
            ConditionsE2::register(ConditionsE1::register(new ConditionEvaluator(), $this->game, $this->layout), $this->game),
            $this->layout,
        );
        $modifiers = new ModifierQuery($this->game, $this->catalog, $this->parts, $this->conditions);
        $this->queries = new EffectQueries($modifiers);
        PartsE4::register($this->parts, $this->layout, $this->queries);
        $this->orbitMods = new EngineOrbitModifiers($this->queries);
        $this->useMods = new UseMods($modifiers, $this->queries, $this->game, $this->game);
        PartsE5::register($this->parts, $this->queries, $this->useMods);
        PartsE6::register($this->parts, $this->queries, $this->useMods, $this->layout);
        $this->proposal = new ProposalOptions($this->game, $this->game, $this->catalog, $this->parts);
        $this->hooks = new EngineActionHooks($this->queries, $this->game, $this->proposal);
        $this->stepMods = new StepMods($this);
        $this->ledger = new EventLedger($globals);
        $this->events = new WindowEvents($globals, $this->ledger);
        $this->store = new WindowStore($db, $globals);
        $this->gate = new EffectWindowGate($this);
    }

    public function attach(ActionServices $services): void
    {
        $this->game->attach($services);
    }

    /**
     * リクエストごとに 1 つの効果エンジン。窓が開いていれば DB から復元する（再読み込み・再接続に耐える）。
     */
    public function engine(): EffectEngine
    {
        if ($this->engine === null) {
            $this->engine = new EffectEngine($this->game, $this->game, $this->parts, $this->catalog, $this->conditions);
            $this->engine->restore($this->store->load());
        }

        return $this->engine;
    }

    /** 窓の状態を DB に書く（ログは先に取り出してあること） */
    public function save(): void
    {
        $snapshot = $this->engine()->snapshot();
        if ($snapshot !== null) {
            $this->store->save($snapshot);
        }
    }
}
