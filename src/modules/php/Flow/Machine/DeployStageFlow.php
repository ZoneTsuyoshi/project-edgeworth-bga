<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\DeployStage;
use Bga\Games\projectedgeworth\Data\FlowPhase;
use Bga\Games\projectedgeworth\Data\DeployStageSnapshot;
use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\ProjectCards;

/**
 * II 配備（状態 61）で、ボーナス保持者が [Deploy another card] を押した時点で 1 枚目を先に確定（ステージ）する処理と、
 * それを取り消す [Reset]（009 追補 2026-11-12、追補 3〜5）。ほかのプレイヤーの確定を待たず、そのプレイヤー 1 人分だけを処理する。
 *
 * ステージ: 1 枚目の検証 → 支払い（売却の同梱を含む）→ 配備 → `DEPLOYED` を起こして、そのプレイヤーのプールだけを窓の外で解決
 * （選択のない効果だけ。選択が要る効果と順番の問いは、バリアのあとの窓 DE に残す）→ ステージ記録（`deploy_stage`）の保存。
 * 通知は本人にすぐ送り、他人向けの公開通知は保留（`held_notif`）に積んでバリアで流す（Q-20 案 A: バリアまで他人に見せない）。
 * [Reset]: ステージ前のスナップショットを復元する。山札から引いた（再シャッフルを含む）結果が出ていれば拒否（Q-22）。
 */
final class DeployStageFlow
{
    public const REASON_IRREVERSIBLE = 'You have drawn cards and cannot undo this deployment';

    public function __construct(private readonly FlowContext $ctx)
    {
    }

    /**
     * @param array<mixed> $data {op: deploy, card, sat, sell?, useMods?}
     * @throws RuleViolation 失敗は状態を変えない
     */
    public function stage(int $playerId, array $data): StageResult
    {
        $services = $this->ctx->services;
        if ($services->globals->flowPhase() !== FlowPhase::Deploy || !in_array($playerId, $services->globals->stepParticipants(), true)) {
            throw new RuleViolation('You are not part of this step');
        }
        $effects = $this->ctx->effects;
        $deploy = $this->ctx->phases->deploy();
        $keys = $effects?->stepMods->validate(2, $playerId, $data) ?? [];
        $snapshot = DeployStageSnapshot::capture($this->ctx->db, $playerId);
        $publicBefore = (new PublicView($this->ctx))->snapshotData();
        $tracker = new MayaTracker($this->ctx->notices, [$playerId => $services->players->maya($playerId)]);

        $outcome = $deploy->stageFirst($playerId, $data);
        $d = $outcome->details;
        $card = (int) $d['card'];
        $satId = (int) $d['sat'];
        $n = $this->ctx->notices;
        $sat = $services->satellites->get($satId);

        $notices = [];
        $sold = array_values(array_map('intval', (array) $d['sold']));
        if ($sold !== []) {
            $notices[] = $n->cardsDiscarded($playerId, $sold, 'sell', $this->ctx->discardCount());
            $notices[] = $tracker->change($playerId, $services->selling->price($playerId) * count($sold), 'sell');
        }
        $notices[] = $tracker->change($playerId, -(int) $d['cost'], 'cost');
        $notices[] = $n->cardDeployed($playerId, $card, $sat, (int) $d['tier'], $services->fuelCapacity($satId), (int) $d['cost'], true);

        $event = new GameEvent(Hook::Deployed, $playerId, $satId, $card, ProjectCards::get(sprintf('P%03d', $card))->type);
        $events = [$event];
        $irreversible = false;
        if ($effects !== null) {
            // 1 枚目に使った起動の修正は、コストを払った今、使用済みにする（2 枚目の候補には出ない）
            $effects->useMods->commit($keys);
            $effectNotices = new EffectNotices($this->ctx);
            foreach ($keys as $key) {
                $notices[] = $effectNotices->used($playerId, $key);
            }
            $mark = $effects->game->journalMark();
            $immediate = $effects->engine()->resolveImmediate($playerId, [$event]);
            $journal = $effects->game->journalSince($mark);
            $built = $effectNotices->build($playerId, $immediate->logs, $journal);
            array_push($notices, ...$built['held'], ...$built['private']);
            $events = $immediate->events;
            $irreversible = self::revealsHiddenInfo($journal);
        }

        $services->deployStages->save(new DeployStage(
            $playerId,
            $card,
            $satId,
            (int) $d['tier'],
            (int) $d['cost'],
            $sold,
            $irreversible,
            $snapshot,
            $publicBefore,
            array_map(static fn(GameEvent $e): array => $e->toArray(), $events),
        ));
        $wasConfirmed = $services->choices->remove($playerId, StateId::DEPLOY_MAIN_CHOOSE);

        // 公開の通知（配備・マヤ・売却・ドローの枚数・山札の枚数）は保留に積み、バリアで流す。本人には先送りの形（メッセージ空・ev_id 付き）
        $out = $this->holdPublic($playerId, $notices);
        $out[] = $n->deployStaged($playerId, $this->privateArgs($playerId));
        if ($wasConfirmed) {
            $out[] = $n->choiceUnmarked($playerId, StateId::DEPLOY_MAIN_CHOOSE);
        }

        return new StageResult($out, $wasConfirmed);
    }

    /**
     * [Reset]（`actResetStage`）: 1 枚目の配備を取り消して、ステージ前の状態に戻す。
     *
     * @throws RuleViolation 後戻りできない結果が出ているとき、ステージしていないとき
     */
    public function reset(int $playerId): StageResult
    {
        $services = $this->ctx->services;
        $stage = $services->deployStages->find($playerId);
        if ($stage === null) {
            throw new RuleViolation('There is no deployment to undo');
        }
        if ($stage->irreversible) {
            throw new RuleViolation(self::REASON_IRREVERSIBLE);
        }
        DeployStageSnapshot::restore($this->ctx->db, $playerId, $stage->snapshot);
        $services->deployStages->remove($playerId);
        (new EffectNotices($this->ctx))->discardStaged($playerId);
        $wasConfirmed = $services->choices->remove($playerId, StateId::DEPLOY_MAIN_CHOOSE);

        $notice = $this->ctx->notices->deployStageReset(
            $playerId,
            $this->privateArgs($playerId),
            (new PublicView($this->ctx))->playerState($playerId),
            $services->cards->idsIn(CardLocation::Hand, $playerId),
        );

        $out = [$notice];
        if ($wasConfirmed) {
            $out[] = $this->ctx->notices->choiceUnmarked($playerId, StateId::DEPLOY_MAIN_CHOOSE);
        }

        return new StageResult($out, $wasConfirmed);
    }

    /**
     * 本人分の `_private`（状態 61 の getArgs と同じ形）。
     *
     * @return array<string, mixed>
     */
    private function privateArgs(int $playerId): array
    {
        $flow = ChoiceFlow::forState($this->ctx, StateId::DEPLOY_MAIN_CHOOSE);

        return (new ChoiceArgs($this->ctx))->privateFor($flow, $playerId);
    }

    /**
     * 全員宛て（`to === null`）の通知は保留に積んで先送りに替え、本人宛て（手札の中身など）はそのまま本人に送る。順序は発生順を保つ。
     *
     * @param list<Notice|null> $notices
     * @return list<Notice>
     */
    private function holdPublic(int $playerId, array $notices): array
    {
        $effectNotices = new EffectNotices($this->ctx);
        $out = [];
        foreach ($notices as $notice) {
            if ($notice === null) {
                continue;
            }
            if ($notice->to === null) {
                array_push($out, ...$effectNotices->holdStaged($playerId, [$notice]));
            } else {
                $out[] = $notice;
            }
        }

        return $out;
    }

    /**
     * 後戻りできない結果か: 山札から引いた（捨て札の再シャッフルを含む）、山札の一番上を配備した（本人に中身が開示される）。
     *
     * @param list<array<string, mixed>> $journal
     */
    private static function revealsHiddenInfo(array $journal): bool
    {
        foreach ($journal as $entry) {
            if ($entry['t'] === 'draw' && ((array) ($entry['cards'] ?? []) !== [] || ($entry['reshuffled'] ?? false) === true)) {
                return true;
            }
            if ($entry['t'] === 'deploy' && ($entry['hand'] ?? true) === false) {
                return true;
            }
        }

        return false;
    }
}
