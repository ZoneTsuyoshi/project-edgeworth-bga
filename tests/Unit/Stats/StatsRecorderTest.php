<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Stats;

use Bga\Games\projectedgeworth\Flow\Machine\FinalScoreOutcome;
use Bga\Games\projectedgeworth\Flow\Machine\Notice;
use Bga\Games\projectedgeworth\Stats\StatsRecorder;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\ArrayStatStore;

/** 010 §14: 通知の内容から統計を導く規則（配列の StatStore で確かめる）。 */
final class StatsRecorderTest extends TestCase
{
    private ArrayStatStore $store;
    private StatsRecorder $stats;

    protected function setUp(): void
    {
        $this->store = new ArrayStatStore();
        $this->stats = new StatsRecorder($this->store);
        $this->stats->init();
    }

    /**
     * @param array<string, mixed> $args
     */
    private function notice(string $name, array $args = [], ?int $to = null): Notice
    {
        return new Notice($name, '', $args, $to);
    }

    public function testInitZeroesEverything(): void
    {
        self::assertSame(0, $this->store->table['rounds_played']);
        self::assertSame(0, $this->store->player('fame_total', 101));
        self::assertSame(0, $this->store->player('actions_chosen_5', 102));
    }

    /** stats.jsonc に定義したキーと init するキーが一致する */
    public function testStatsDefinitionMatchesInit(): void
    {
        $json = (string) file_get_contents(dirname(__DIR__, 3) . '/src/stats.jsonc');
        $json = (string) preg_replace('#^\s*//.*$#m', '', $json);
        /** @var array{table: array<string, array{id: int}>, player: array<string, array{id: int}>} $defs */
        $defs = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $store = new ArrayStatStore();
        (new StatsRecorder($store))->init();
        self::assertEqualsCanonicalizing(array_keys($defs['table']), array_keys($store->table));
        self::assertEqualsCanonicalizing(array_keys($defs['player']), array_keys($store->player));
        $ids = array_merge(array_column($defs['table'], 'id'), array_column($defs['player'], 'id'));
        self::assertSame($ids, array_values(array_unique($ids)));
        self::assertGreaterThanOrEqual(10, min($ids));
    }

    public function testPlayerCounters(): void
    {
        $this->stats->recordNotices([
            $this->notice('actionRevealed', ['player_id' => 101, 'action_no' => 4]),
            $this->notice('phaseStarted', ['phase' => 4, 'holders' => [101, 102]]),
            $this->notice('phaseStarted', ['phase' => 'orbit', 'holders' => []]),
            $this->notice('cardsDiscarded', ['player_id' => 101, 'count' => 2, 'reason' => 'sell']),
            $this->notice('cardsDiscarded', ['player_id' => 101, 'count' => 3, 'reason' => 'proposal']),
            $this->notice('cardsDiscarded', ['player_id' => 101, 'count' => 1, 'reason' => 'effect']),
            $this->notice('mayaChanged', ['player_id' => 101, 'delta' => 6]),
            $this->notice('mayaChanged', ['player_id' => 101, 'delta' => -4]),
            $this->notice('mayaTransferred', ['from_player_id' => 101, 'to_player_id' => 102, 'amount' => 2]),
            $this->notice('cardDeployed', ['player_id' => 101]),
            $this->notice('satelliteLaunched', ['player_id' => 101]),
            $this->notice('satelliteTurned', ['player_id' => 101]),
            $this->notice('fuelChanged', ['player_id' => 101, 'delta' => 3]),
            $this->notice('fuelChanged', ['player_id' => 101, 'delta' => -1]),
            $this->notice('satelliteLanded', ['player_id' => 101, 'sea' => true]),
            $this->notice('satelliteLanded', ['player_id' => 101, 'sea' => false]),
            $this->notice('linkEstablished', ['player_id' => 101, 'special' => true]),
            $this->notice('linkEstablished', ['player_id' => 102, 'special' => false]),
            $this->notice('actionPassed', ['player_id' => 101, 'kind' => 'link', 'auto' => false]),
            $this->notice('actionPassed', ['player_id' => 101, 'kind' => 'link', 'auto' => true]),
            $this->notice('actionPassed', ['player_id' => 101, 'kind' => 'deploy', 'auto' => false]),
            $this->notice('effectUsed', ['player_id' => 101]),
            $this->notice('effectSkipped', ['player_id' => 102]),
        ]);
        $s = $this->store;
        self::assertSame(1, $s->player('actions_chosen_4', 101));
        self::assertSame(1, $s->player('bonus_gained', 101));
        self::assertSame(1, $s->player('bonus_gained', 102));
        self::assertSame(2, $s->player('cards_sold', 101));
        self::assertSame(3, $s->player('cards_discarded', 101));
        self::assertSame(6, $s->player('maya_earned', 101));
        self::assertSame(4 + 2, $s->player('maya_spent', 101), '先駆コストの支払いを含む');
        self::assertSame(2, $s->player('maya_earned', 102), '先駆コストの受け取りを含む');
        self::assertSame(2, $s->player('pioneer_paid', 101));
        self::assertSame(2, $s->player('pioneer_received', 102));
        self::assertSame(1, $s->player('cards_deployed', 101));
        self::assertSame(1, $s->player('satellites_launched', 101));
        self::assertSame(1, $s->player('satellites_turned', 101));
        self::assertSame(3, $s->player('fuel_used', 101));
        self::assertSame(1, $s->player('fuel_recovered', 101));
        self::assertSame(1, $s->player('splashdowns', 101));
        self::assertSame(1, $s->player('landings', 101));
        self::assertSame(1, $s->player('links_count', 101));
        self::assertSame(1, $s->player('special_links', 101));
        self::assertSame(1, $s->player('links_count', 102));
        self::assertSame(2, $s->table['links_total']);
        self::assertSame(1, $s->player('links_abandoned', 101), '自動の見送りと他のアクションの [Pass] は数えない');
        self::assertSame(1, $s->player('effects_used', 101));
        self::assertSame(1, $s->player('effects_skipped', 102));
    }

    public function testPrivateCopiesAreNotCounted(): void
    {
        $this->stats->recordNotices([
            $this->notice('effectUsed', ['player_id' => 101], 101),
            $this->notice('mayaChanged', ['player_id' => 101, 'delta' => 5], 101),
        ]);
        self::assertSame(0, $this->store->player('effects_used', 101));
        self::assertSame(0, $this->store->player('maya_earned', 101));
    }

    public function testTableStats(): void
    {
        $this->stats->recordNotices([
            $this->notice('roundStarted', ['round_no' => 1]),
            $this->notice('roundStarted', ['round_no' => 2]),
            $this->notice('nearMiss', ['cell' => 5, 'sats' => [1, 2, 3], 'players' => [101, 102]]),
            $this->notice('nearMiss', ['cell' => 6, 'sats' => [4, 5], 'players' => [101]]),
            $this->notice('gameEndTriggered', ['players' => [101], 'reasons' => ['satellites', 'links']]),
        ]);
        self::assertSame(2, $this->store->table['rounds_played']);
        self::assertSame(2, $this->store->table['near_misses']);
        self::assertSame(2, $this->store->player('near_miss_count', 101));
        self::assertSame(1, $this->store->player('near_miss_count', 102));
        self::assertSame(StatsRecorder::END_REASON_BOTH, $this->store->table['end_reason']);
    }

    public function testEndReason(): void
    {
        self::assertSame(1, StatsRecorder::endReason(true, false));
        self::assertSame(2, StatsRecorder::endReason(false, true));
        self::assertSame(3, StatsRecorder::endReason(true, true));
        self::assertSame(0, StatsRecorder::endReason(false, false));
    }

    public function testRecordFinal(): void
    {
        $detail = [
            'total' => 10,
            'I' => ['points' => 3, 'budget' => 3],
            'II' => ['points' => 1, 'maya' => 12],
            'III' => ['points' => 2],
            'IV' => ['points' => 0],
            'V' => ['points' => 1],
            'VI' => ['points' => 2, 'sats' => [['sat_id' => 1, 'fame' => 1], ['sat_id' => 2, 'fame' => 1]]],
            'VII' => ['points' => 1],
            'VIII' => ['points' => 0],
            'IX' => ['points' => 0],
        ];
        $this->stats->recordFinal(new FinalScoreOutcome([], [101 => 10], [101 => $detail], [101 => 9]));
        $s = $this->store;
        self::assertSame(3, $s->player('fame_budget', 101));
        self::assertSame(1, $s->player('fame_maya', 101));
        self::assertSame(2, $s->player('fame_satellites', 101));
        self::assertSame(10, $s->player('fame_total', 101));
        self::assertSame(3, $s->player('budget_final', 101));
        self::assertSame(2, $s->player('active_satellites_final', 101));
        self::assertSame(9, $s->player('cubes_left_final', 101));
    }
}
