<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 効果の窓の状態 30〜32 の処理（009 §3.5、§6）。今回は効果なしで素通りする最小の形。
 *
 * 効果エンジンの本番結線（申し送り N-4・N-7）で差し込む場所:
 * - open(): EffectEngine::openWindow($windowId, $events) を呼び、WindowReport::needsInput() なら 'choose'、でなければ 'barrier'。
 *   窓を開いた時点の公開情報を window_snapshot に保存する（010 §9.4）。
 * - playersNeedingInput(): EffectEngine::playersNeedingInput()。31 の入場で setPlayersMultiactive に渡す。
 * - barrier(): EffectEngine::barrier()。ステージされた通信確立があれば全員分を一斉にコミットして（世代 +1）、StagedLinkConverter で
 *   LINKED のイベントを collect し、もう一度 barrier()。needsInput() なら 'again'（31 へ戻る）。
 *   canClose() になったら closeWindow() のログから保留通知（010 §10.5）を組み立てて送り、window_snapshot を消す。
 */
final class WindowFlow
{
    public function __construct(private readonly FlowContext $ctx)
    {
    }

    /**
     * 30: 窓を開く。トリガーされた効果がなければ（今回は常に）バリアへ。
     */
    public function open(): DriverResult
    {
        return new DriverResult('barrier');
    }

    /**
     * 31 の参加者: 選択が要る効果のあるプレイヤー。今回は誰もいない（入場の直後に 'allDone' で抜ける）。
     *
     * @return list<int>
     */
    public function playersNeedingInput(): array
    {
        return [];
    }

    /**
     * 31 の args（010 §13）。公開部分は窓の種類と待っている人。
     *
     * @return array<string, mixed>
     */
    public function resolveArgs(): array
    {
        return [
            'windowId' => $this->ctx->globals->windowId(),
            'waiting' => [],
            'round' => $this->ctx->globals->roundNo(),
        ];
    }

    /**
     * 32: 窓を閉じて、戻り先（window_resume）へ。選択が要る効果が新しく積まれたときの 'again' は、結線後に追加する。
     */
    public function close(): WindowExit
    {
        $globals = $this->ctx->globals;
        $resume = $globals->windowResume();
        $windowId = $globals->windowId();
        if ($resume === null || $windowId === null) {
            throw new \LogicException('the effect window has no return state');
        }
        $globals->setWindowId(null);
        $globals->setWindowResume(null);
        $globals->setWindowGen(0);
        $globals->setWindowEffects(0);
        $globals->setWindowSnapshot(null);
        return new WindowExit($resume, [$this->ctx->notices->windowClosed($windowId, [])]);
    }
}
