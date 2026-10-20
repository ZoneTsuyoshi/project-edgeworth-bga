<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * ドライバ（50・60・70・80・90・100）のステップ進行（009 §3.6〜§3.8、§6.2）。
 * 処理を並べたリスト ops を、永続カーソル flow_step（再入場しても二重に実行しない）で 1 つずつ進める。
 *
 * - カーソルは処理の「前」に進める。選択状態や窓へ出て戻ってきたとき、次の処理から再開するため。
 * - 'win:XX' は窓 XX を開く位置。WindowGate が必要と答えたときだけ window_id と window_resume を書いて 'window' を返す
 *   （状態 30 へ。閉じると 32 が戻り先のドライバへ返す）。不要なら素通りする。
 * - 'next' は終わり。flow_step を 0 に戻して 'next' を返す。
 * - それ以外は $exec が実行し、遷移名を返したらそこで止める（選択状態へ出る）。null なら続ける。
 */
final class StepProgram
{
    public function __construct(
        private readonly FlowContext $ctx,
        private readonly int $stateId,
    ) {
    }

    /**
     * @param list<string> $ops
     * @param \Closure(string, NoticeList): ?string $exec
     */
    public function run(array $ops, \Closure $exec): DriverResult
    {
        $globals = $this->ctx->globals;
        $notes = new NoticeList();
        while (true) {
            $index = $globals->flowStep();
            if ($index < 0 || $index >= count($ops)) {
                throw new \LogicException("state {$this->stateId}: flow_step $index is out of range");
            }
            $op = $ops[$index];
            $globals->setFlowStep($index + 1);

            if (str_starts_with($op, 'win:')) {
                $windowId = substr($op, 4);
                if ($this->ctx->gate->needsWindow($windowId)) {
                    $globals->setWindowId($windowId);
                    $globals->setWindowResume($this->stateId);
                    return new DriverResult('window', $notes->all());
                }
                continue;
            }
            if ($op === 'next') {
                $globals->setFlowStep(0);
                return new DriverResult('next', $notes->all());
            }
            $transition = $exec($op, $notes);
            if ($transition !== null) {
                return new DriverResult($transition, $notes->all());
            }
        }
    }

    /**
     * 残りの処理を飛ばして 'next' へ進める（ボーナス保持者がいないとき）。
     *
     * @param list<string> $ops
     */
    public function skipToEnd(array $ops): void
    {
        $this->ctx->globals->setFlowStep(count($ops) - 1);
    }
}
