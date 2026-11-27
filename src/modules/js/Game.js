/**
 * PROJECT EDGEWORTH のクライアント（仕様書 004 §4、012 §12）。
 * Game.js は setup、状態クラスの登録、通知の購読、ゲームの設定（101）の反映だけを持つ。画面の組み立ては ui/GameView.js、盤面は board/、カードは cards/、
 * 通知の処理は notifications.js。BGA のグローバルへの依存はこのファイルの Env の組み立てに閉じ込める。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { dispatchNotification, HANDLERS } from './notifications.js';
import { DeployState } from './states/DeployState.js';
import { DraftState } from './states/DraftState.js';
import { EffectWindowState } from './states/EffectWindowState.js';
import { LaunchState } from './states/LaunchState.js';
import { LinkState } from './states/LinkState.js';
import { MulliganState } from './states/MulliganState.js';
import { PlanningPlot, SetupLaunchPadPlace } from './states/PlacementStates.js';
import { ProposalState } from './states/ProposalState.js';
import { GameView } from './ui/GameView.js';

/** ゲームの設定 101「Satellite cards」（gamepreferences.jsonc）。0 = Compact、1 = As printed（詳細表示。012 追補 U-8） */
const PREF_SATELLITE_CARDS = 101;

export class Game {
    /**
     * @param {Bga} bga
     */
    constructor(bga) {
        this.bga = bga;
        /** @type {GameView | null} */
        this.view = null;
        // 状態クラスはサーバー側の States/ のクラス名で登録する（状態 11・13・22・31・61・71・72・81・82・91 の操作 UI）
        this.bga.states.register('SetupLaunchPadPlace', new SetupLaunchPadPlace(this, bga));
        this.bga.states.register('SetupMulligan', new MulliganState(this, bga));
        this.bga.states.register('SetupDraftPick', new DraftState(this, bga));
        this.bga.states.register('PlanningPlot', new PlanningPlot(this, bga));
        this.bga.states.register('EffectWindowResolve', new EffectWindowState(this, bga));
        this.bga.states.register('DeployMainChoose', new DeployState(this, bga));
        this.bga.states.register('LaunchMainChoose', new LaunchState(this, bga));
        this.bga.states.register('LaunchBonusChoose', new LaunchState(this, bga));
        this.bga.states.register('LinkMainChoose', new LinkState(this, bga));
        this.bga.states.register('LinkBonusChoose', new LinkState(this, bga, true));
        this.bga.states.register('ProposalKeepChoose', new ProposalState(this, bga));
    }

    /**
     * @param {Record<string, any>} gamedatas getAllDatas の戻り値（010 §8.2）
     */
    setup(gamedatas) {
        const spectator = this.bga.players.isCurrentPlayerSpectator();
        /** @type {import('./cards/CardView.js').Env} */
        const env = {
            imgUrl: (file) => this.bga.images.getImgUrl(file),
            playerPanel: (playerId) => this.bga.playerPanels.getElement(playerId) ?? null,
            animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
            currentPlayerId: () => (spectator ? null : Number(this.bga.players.getCurrentPlayerId())),
            selectionMode: () => this.view?.stateFrame.selecting === true,
            addButton: (label, callback, opts = {}) => this.bga.statusBar.addActionButton(label, callback, opts),
        };
        this.view = new GameView(env, this.bga.gameArea.getElement());
        this.view.setup(gamedatas);
        this.setupPreferences(this.view);
        this.bga.notifications.setupPromiseNotifications({});
    }

    /**
     * ゲームの設定を読んで画面に反映し、変更も受ける（`userPreferences` が無い環境でも落ちない）。
     * @param {GameView} view
     */
    setupPreferences(view) {
        const prefs = this.bga.userPreferences;
        if (prefs === undefined || prefs === null) {
            return;
        }
        view.setDetailAll(Number(prefs.get?.(PREF_SATELLITE_CARDS)) === 1);
        prefs.onChange = (/** @type {number | string} */ prefId, /** @type {number | string} */ value) => {
            if (Number(prefId) === PREF_SATELLITE_CARDS) {
                view.setDetailAll(Number(value) === 1);
            }
        };
    }
}

// 通知 N1〜N33（010 §10.2）を `notif_<名前>` として登録する。処理は notifications.js の表に集約
for (const name of Object.keys(HANDLERS)) {
    /** @type {Record<string, unknown>} */ (/** @type {unknown} */ (Game.prototype))[`notif_${name}`] = function notify(/** @type {Record<string, any>} */ args) {
        const game = /** @type {Game} */ (/** @type {unknown} */ (this));
        return game.view === null ? Promise.resolve() : dispatchNotification(game.view, name, args);
    };
}
