/**
 * 画面全体（仕様書 012 §2）。getAllDatas（010 §8.2）の値から DOM を組み立て、通知（notifications.js）がモデルを書き換えて部分的に描き直す。
 * BGA のグローバルに依存しない（依存は Env で注入する）ので、tools/preview/index.html でも動く。
 * レイアウト: フェイズ帯 → 場の情報 → 盤面 → 自分の領域 → 他プレイヤー。BGA のプレイヤーパネルとログはゲーム領域の外。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { BoardView } from '../board/BoardView.js';
import { Highlights } from '../board/Highlights.js';
import { PieceLayer } from '../board/PieceLayer.js';
import { FieldView } from '../cards/FieldView.js';
import { Tooltip } from '../cards/Tooltip.js';
import { BottomSheet } from './BottomSheet.js';
import { ChoicePanel } from './ChoicePanel.js';
import { el } from './dom.js';
import { Layout } from './Layout.js';
import { normalizeGamedatas } from './normalize.js';
import { PhaseBand } from './PhaseBand.js';
import { PlayerArea } from './PlayerArea.js';
import { PlayerPanel } from './PlayerPanel.js';
import { ScoreSheet } from './ScoreSheet.js';
import { StateFrame } from './StateFrame.js';
import { scoresFromGamedatas } from '../states/logic/score.js';

const DETAIL_PREF_KEY = 'pe-detail-view';

/** 詳細表示の選択を localStorage から読む（使えない環境では簡略表示） */
function readDetailPref() {
    try {
        return localStorage.getItem(DETAIL_PREF_KEY) === '1';
    } catch {
        return false;
    }
}

/**
 * @param {boolean} detail
 */
function writeDetailPref(detail) {
    try {
        localStorage.setItem(DETAIL_PREF_KEY, detail ? '1' : '0');
    } catch {
        // 保存できなくても表示は切り替わる
    }
}

export class GameView {
    /**
     * @param {import('../cards/CardView.js').Env} env
     * @param {HTMLElement} host ゲーム領域（BGA では `bga.gameArea.getElement()`）
     */
    constructor(env, host) {
        this.env = env;
        this.host = host;
        /** @type {import('./normalize.js').GameModel | null} */
        this.model = null;
        /** @type {Map<number, PlayerArea>} */
        this.areas = new Map();
        /** @type {Map<number, PlayerPanel>} */
        this.panels = new Map();
        this.detailAll = readDetailPref();
        /** 先送り済みの保留通知の ev_id（010 §10.5。windowClosed で空にする） */
        this.appliedEvents = new Set();
        this.tooltip = new Tooltip(env);
        this.root = el('div', { id: 'pe-root', class: 'pe-root' });
        host.append(this.root);

        this.bandBox = el('div', { id: 'pe-phase-band' });
        this.fieldBox = el('div', { id: 'pe-field' });
        this.panelBox = el('section', { id: 'pe-choice-panel', 'aria-live': 'polite' });
        this.scoreBox = el('section', { id: 'pe-score' });
        this.boardWrap = el('section', { id: 'pe-board-wrap', class: 'pe-board-wrap' });
        this.sheetBox = el('div', { id: 'pe-sheet' });
        this.meBox = el('div', { id: 'pe-me' });
        this.othersBox = el('div', { id: 'pe-others' });
        this.detailButton = el('button', {
            type: 'button',
            class: 'pe-btn pe-btn-small',
            'aria-pressed': this.detailAll ? 'true' : 'false',
            title: _('Show all satellites with deployed cards stacked as on the paper cards'),
            onClick: () => this.setDetailAll(!this.detailAll),
        }, [this.detailAll ? _('Simple view') : _('Detail view')]);
        const boardHead = el('div', { class: 'pe-board-head' }, [el('span', { class: 'pe-board-title' }, [_('Board')])]);
        this.root.append(this.bandBox, this.fieldBox, this.panelBox, this.scoreBox, this.boardWrap, this.meBox, this.othersBox);

        this.band = new PhaseBand(this.bandBox);
        this.field = new FieldView(env, this.tooltip, this.fieldBox);
        this.fieldBox.append(this.detailButton);
        this.board = new BoardView(env, this.boardWrap);
        this.boardWrap.prepend(boardHead);
        this.boardWrap.append(this.sheetBox);
        this.highlights = new Highlights(this.board);
        /** @type {((satId: number) => boolean) | null} 盤面の衛星コマのタップを、操作中の状態が受ける（受けたら true。転回の衛星の選択） */
        this.satelliteHandler = null;
        this.pieces = new PieceLayer(this.board, {
            onSatelliteClick: (satId) => {
                if (this.satelliteHandler?.(satId) !== true) {
                    this.focusSatellite(satId);
                }
            },
        });
        this.sheet = new BottomSheet(this.sheetBox);
        this.panel = new ChoicePanel(this.panelBox);
        this.score = new ScoreSheet(this.scoreBox, () => this.m, (satId) => this.satName(satId));
        /** @type {Set<(name: string) => void>} 手札・マヤ・衛星などのモデルが通知で変わったときに呼ぶ（操作中の状態が表示を直す） */
        this.modelListeners = new Set();
        /** @type {{ update: (a: Record<string, any>) => void, setWaiting: (w: unknown) => void, close: () => void } | null} 効果の窓（状態 31）の UI。窓の通知を渡す */
        this.effectUi = null;
        this.layout = new Layout(this.root, () => this.model?.board?.cols ?? 12);
        boardHead.append(this.layout.renderTools());
        this.stateFrame = new StateFrame(this);
        this.board.onPick = (cell) => this.stateFrame.handleCellPick(cell);
    }

    /**
     * getAllDatas の値から全体を作る（setup。リロードの復元も同じ）。
     * @param {unknown} raw
     */
    setup(raw) {
        const model = normalizeGamedatas(raw, this.env.currentPlayerId());
        this.model = model;
        const cols = model.board?.cols ?? 12;
        this.root.classList.toggle('pe-spectator', model.me === null);
        if (model.board !== null) {
            this.board.render(model.board);
        }
        this.layout.update();
        this.field.setRewards(model.rewards);
        this.field.setDeck(model.deckCount);
        this.field.setDiscard(model.discard);
        this.field.setRound(model.round);
        this.refreshBand();

        // プレイヤーの領域: 自分を先頭に、他は席順（観戦者は全員を席順で同じ大きさに）
        this.areas.clear();
        this.meBox.replaceChildren();
        this.othersBox.replaceChildren();
        const order = model.playerOrder;
        const start = model.me === null ? 0 : order.indexOf(model.me);
        const rotated = [...order.slice(start), ...order.slice(0, start)];
        for (const id of rotated) {
            const player = model.players[id];
            const mine = id === model.me;
            const area = new PlayerArea(this.env, this.tooltip, player, { mine, rewards: model.rewards });
            area.setFame(model.publicFame?.[id] ?? null);
            if (mine) {
                area.hand.setCards(model.myHand);
                area.refreshHand({ hand: model.myHand, missions: model.myMissions });
            }
            area.setDetail(this.detailAll);
            this.areas.set(id, area);
            (mine ? this.meBox : this.othersBox).append(area.root);
        }
        this.refreshPanels();
        this.refreshMarks();
        this.syncPieces();
        this.score.setAll(scoresFromGamedatas(model.finalScores, model.playerOrder));
        // スマホの横スクロールの初期位置は自分の発射拠点の区域が中央に来る位置（012 §3.8）
        const pad = model.me === null ? null : model.players[model.me]?.padCell;
        if (pad !== null && pad !== undefined && cols > 0) {
            requestAnimationFrame(() => this.board.scrollToCell(pad));
        }
    }

    /**
     * モデルが通知で変わったことを、操作中の状態に知らせる。
     * @param {string} name 通知名
     */
    emitModelChanged(name) {
        for (const listener of [...this.modelListeners]) {
            listener(name);
        }
    }

    /**
     * 衛星の表示名（形状。例: HAMMER）。
     * @param {number} satId
     * @returns {string}
     */
    satName(satId) {
        const found = this.findSatellite(satId);
        const shape = found?.sat.shape ?? `#${satId}`;
        return shape.charAt(0) + shape.slice(1).toLowerCase();
    }

    /** @returns {import('./normalize.js').GameModel} */
    get m() {
        if (this.model === null) {
            throw new Error('GameView.setup が呼ばれていません');
        }
        return this.model;
    }

    // ---- 部分的な描き直し（通知から呼ぶ） ----

    /** 選ばれたアクション（公開後）でフェイズ帯の点灯を決める */
    refreshBand() {
        const m = this.m;
        const selected = Object.keys(m.bonusHolders).map(Number).filter((n) => n >= 1 && n <= 5);
        this.band.set({
            round: m.round,
            phase: m.phase,
            selected: m.phase === 'PLANNING' || selected.length === 0 ? null : selected,
        });
        this.field.setRound(m.round);
    }

    /** コマの層をモデルに合わせる */
    syncPieces() {
        const m = this.m;
        if (m.board === null) {
            return;
        }
        this.pieces.sync({
            players: m.playerOrder.map((id) => {
                const p = m.players[id];
                return { id, colorIndex: p.colorIndex, padCell: p.padCell, satellites: p.satellites, links: p.links };
            }),
        });
    }

    /** プレイヤーパネルのカウンターをモデルに合わせる（無ければ作る） */
    refreshPanels() {
        const m = this.m;
        for (const id of m.playerOrder) {
            const p = m.players[id];
            const existing = this.panels.get(id);
            if (existing !== undefined) {
                existing.update({});
                continue;
            }
            const host = this.env.playerPanel(id);
            if (host !== null) {
                const panel = new PlayerPanel(this.env, host, p, m.rewards);
                panel.setBase(m.basePlayer === id);
                panel.setFame(m.publicFame?.[id] ?? null);
                panel.listeners.add(() => this.areas.get(id)?.renderStats());
                this.panels.set(id, panel);
            }
        }
    }

    /** 途中の名声（ゲームオプション 105 が Visible のとき。通知 N36）をパネルと自分の領域に反映する */
    refreshFame() {
        const m = this.m;
        for (const id of m.playerOrder) {
            const fame = m.publicFame?.[id] ?? null;
            this.panels.get(id)?.setFame(fame);
            this.areas.get(id)?.setFame(fame);
        }
    }

    /** 確定の印・基準プレイヤー・（終了後の）名声 */
    refreshMarks() {
        const m = this.m;
        for (const id of m.playerOrder) {
            const p = m.players[id];
            this.areas.get(id)?.setMarks({ confirmed: p.confirmed, isBase: m.basePlayer === id });
            this.panels.get(id)?.update({});
            this.areas.get(id)?.actions.setPlot(p.confirmed, null);
        }
    }

    /**
     * 1 人の領域（衛星・アクション・手札の枚数）とパネルを描き直す。
     * @param {number} playerId
     */
    refreshPlayer(playerId) {
        const area = this.areas.get(playerId);
        area?.refresh();
        if (playerId === this.m.me) {
            area?.refreshHand({ hand: this.m.myHand, missions: this.m.myMissions });
        }
        this.panels.get(playerId)?.update({});
    }

    /** 全員の領域を描き直す */
    refreshAllPlayers() {
        for (const id of this.m.playerOrder) {
            this.refreshPlayer(id);
        }
    }

    /**
     * @param {boolean} detail
     */
    setDetailAll(detail) {
        this.detailAll = detail;
        this.detailButton.setAttribute('aria-pressed', detail ? 'true' : 'false');
        this.detailButton.textContent = detail ? _('Simple view') : _('Detail view');
        writeDetailPref(detail);
        for (const area of this.areas.values()) {
            area.setDetail(detail);
        }
    }

    /**
     * 衛星のコマ・カードのどちらをタップしても対応する区域を強調する（012 §3.9）。
     * @param {number} satId
     */
    focusSatellite(satId) {
        const cell = this.pieces.satelliteCell(satId);
        if (cell === null) {
            return;
        }
        this.highlights.set([{ cell, kind: 'selected' }]);
        this.board.scrollToCell(cell);
        window.setTimeout(() => this.stateFrame.restoreHighlights(), 1500);
    }

    /**
     * 持ち主の衛星を探す。
     * @param {number} satId
     * @returns {{ player: import('./normalize.js').PlayerModel, sat: import('../cards/SatelliteView.js').SatelliteData } | null}
     */
    findSatellite(satId) {
        for (const id of this.m.playerOrder) {
            const player = this.m.players[id];
            const sat = player.satellites.find((s) => s.satId === satId);
            if (sat !== undefined) {
                return { player, sat };
            }
        }
        return null;
    }
}
