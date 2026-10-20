/**
 * PROJECT EDGEWORTH のクライアント（仕様書 004 §4、012 §12）。
 * Game.js は setup、状態クラスの登録、通知の購読だけを持つ。画面の組み立ては ui/GameView.js、盤面は board/、カードは cards/、
 * 通知の処理は notifications.js。BGA のグローバルへの依存はこのファイルの Env の組み立てに閉じ込める。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { dispatchNotification, HANDLERS } from './notifications.js';
import { ChoiceState, PlanningPlot, SetupLaunchPadPlace } from './states/ChoiceState.js';
import { GameView } from './ui/GameView.js';

/**
 * 仮の待機状態（サーバー側 States/PlaceholderWait）。I-07 で状態ごとのクラスに置き換える。
 */
class PlaceholderWait {
    /**
     * @param {Game} game
     * @param {Bga} bga
     */
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    /**
     * @param {unknown} _args
     * @param {boolean} isCurrentPlayerActive
     */
    onEnteringState(_args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.addActionButton(_('End the game (temporary)'), () => this.bga.actions.performAction('actEndTemporarily'));
        }
    }
}

export class Game {
    /**
     * @param {Bga} bga
     */
    constructor(bga) {
        this.bga = bga;
        /** @type {GameView | null} */
        this.view = null;
        this.bga.states.register('PlaceholderWait', new PlaceholderWait(this, bga));
        // 状態クラスはサーバー側の States/ のクラス名で登録する。操作の仕上げがある状態は個別クラス、他の同時選択は枠だけの ChoiceState
        this.bga.states.register('SetupLaunchPadPlace', new SetupLaunchPadPlace(this, bga));
        this.bga.states.register('PlanningPlot', new PlanningPlot(this, bga));
        for (const name of ['SetupMulligan', 'DeployMainChoose', 'DeployBonusChoose', 'LaunchMainChoose', 'LaunchBonusChoose', 'LinkMainChoose', 'LinkBonusChoose', 'ProposalKeepChoose']) {
            this.bga.states.register(name, new ChoiceState(this, bga));
        }
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
        };
        this.view = new GameView(env, this.bga.gameArea.getElement());
        this.view.setup(gamedatas);
        this.bga.notifications.setupPromiseNotifications({});
    }
}

// 通知 N1〜N33（010 §10.2）を `notif_<名前>` として登録する。処理は notifications.js の表に集約
for (const name of Object.keys(HANDLERS)) {
    /** @type {Record<string, unknown>} */ (/** @type {unknown} */ (Game.prototype))[`notif_${name}`] = function notify(/** @type {Record<string, any>} */ args) {
        const game = /** @type {Game} */ (/** @type {unknown} */ (this));
        return game.view === null ? Promise.resolve() : dispatchNotification(game.view, name, args);
    };
}
