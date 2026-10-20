/**
 * 通知（010 §10 の N1〜N33）の受け取り。通知名 → ハンドラの表。ハンドラは (view, args) を受けてモデルを書き換え、部分的に描き直す。
 * Game.js が `notif_<名前>` として登録する（BGA）。tools/preview でも同じ表を使って再生できる。
 * 保留通知（010 §10.5）: args に `ev_id` が付く。先送りで適用済みの ev_id はバリアの再送では画面に反映しない（ログだけ）。
 * 公開通知にカードの ID が載るのは、公開されているものだけ（手札は枚数のみ。本人宛ての handCardsAdded に ID）。
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */
import { chipInfo } from './cards/card-text.js';
import { projectKey } from './cards/sprite-layout.js';
import { mergePublicFame, scoreFromNotification } from './states/logic/score.js';
import { int } from './ui/dom.js';

/**
 * @typedef {import('./ui/GameView.js').GameView} GameView
 * @typedef {(view: GameView, args: Record<string, any>) => void | Promise<void>} NotifHandler
 */

/**
 * 衛星をモデルから探す。
 * @param {GameView} view
 * @param {unknown} satId
 */
function findSat(view, satId) {
  return view.findSatellite(int(satId));
}

/**
 * 手札から ID を取り除く。本人は ID の並び、他人は枚数だけ。
 * @param {GameView} view
 * @param {number} playerId
 * @param {number[]} ids
 */
function removeFromHand(view, playerId, ids) {
  const m = view.m;
  if (playerId === m.me) {
    m.myHand = m.myHand.filter((id) => !ids.includes(id));
    m.players[playerId].handCount = m.myHand.length;
  } else if (m.players[playerId] !== undefined) {
    m.players[playerId].handCount = Math.max(0, m.players[playerId].handCount - ids.length);
  }
}

/** 山札・捨て札の表示をモデルに合わせる */
function refreshPiles(/** @type {GameView} */ view) {
  view.field.setDeck(view.m.deckCount);
  view.field.setDiscard(view.m.discard);
}

/**
 * @param {GameView} view
 * @param {number} playerId
 */
function refreshOne(view, playerId) {
  view.refreshPlayer(playerId);
}

/** phaseStarted の phase（1〜5 / 'orbit'）→ flow_phase（010 §7.2） */
const PHASE_BY_NUMBER = { 1: 'BUDGET', 2: 'DEPLOY', 3: 'LAUNCH_TURN', 4: 'LINK', 5: 'PROPOSAL', orbit: 'ORBIT' };

/** @type {Record<string, NotifHandler>} */
export const HANDLERS = {
  // N1: 発射拠点のコマを区域に置く
  launchBasePlaced(view, a) {
    const p = view.m.players[int(a.player_id)];
    if (p !== undefined) {
      p.padCell = int(a.cell);
      view.pieces.setBase(p.id, p.padCell, p.colorIndex);
      view.panels.get(p.id)?.update({});
    }
  },

  // N2: 同時選択の確定（内容なし）。チェックマーク
  choiceMarked(view, a) {
    const p = view.m.players[int(a.player_id)];
    if (p !== undefined) {
      p.confirmed = true;
      view.refreshMarks();
    }
  },

  // N3（本人）: 確定内容の要約。別タブの同期。計画フェイズなら選んだアクションが分かる
  choiceRecorded(view, a) {
    const m = view.m;
    if (m.me === null) {
      return;
    }
    m.players[m.me].confirmed = true;
    view.refreshMarks();
    const no = a.summary?.action;
    if (no !== undefined) {
      view.areas.get(m.me)?.actions.setPlot(true, int(no));
    }
  },

  // N4: ラウンド開始。起動効果の使用済みマークを全員分外す
  roundStarted(view, a) {
    const m = view.m;
    m.round = int(a.round_no);
    m.bonusHolders = {};
    for (const id of m.playerOrder) {
      const p = m.players[id];
      p.confirmed = false;
      for (const s of p.satellites) {
        s.activation = 0;
        for (const c of s.cards) {
          c.activation = 0;
        }
      }
    }
    view.refreshBand();
    view.refreshAllPlayers();
    view.refreshMarks();
  },

  // N5: アクションカードの回収
  actionCardsCollected(view, a) {
    const m = view.m;
    for (const [pid, move] of Object.entries(a.moves ?? {})) {
      const p = m.players[int(pid)];
      if (p === undefined) {
        continue;
      }
      const mv = /** @type {{ toHand?: number[], toSideways?: number[] }} */ (move);
      for (const no of mv.toHand ?? []) {
        p.actionCards[String(no)] = 0;
      }
      for (const no of mv.toSideways ?? []) {
        p.actionCards[String(no)] = 2;
      }
      p.confirmed = false;
    }
    view.refreshAllPlayers();
    view.refreshMarks();
  },

  // N6: プロットを一斉に表向きにする。ボーナス保持者を更新
  actionRevealed(view, a) {
    const m = view.m;
    const pid = int(a.player_id);
    const no = int(a.action_no);
    if (m.players[pid] !== undefined) {
      m.players[pid].actionCards[String(no)] = 1;
      m.players[pid].confirmed = false;
    }
    const holders = m.bonusHolders[String(no)] ?? [];
    if (!holders.includes(pid)) {
      holders.push(pid);
    }
    m.bonusHolders[String(no)] = holders;
    view.refreshBand();
    view.refreshPlayer(pid);
    view.refreshMarks();
  },

  // N7: フェイズ開始。フェイズ帯の点灯。ボーナス保持者は公開
  phaseStarted(view, a) {
    const m = view.m;
    // I-07 は phase を 1〜5（アクション番号）または 'orbit' で送る。flow_phase の綴りにそろえる
    m.phase = PHASE_BY_NUMBER[/** @type {keyof typeof PHASE_BY_NUMBER} */ (String(a.phase).toLowerCase())] ?? String(a.phase).toUpperCase();
    view.refreshBand();
  },

  // N8: 引いた枚数と山の枚数だけ（手札の ID は N9 で本人に）
  cardsDrawn(view, a) {
    const m = view.m;
    const p = m.players[int(a.player_id)];
    if (p !== undefined) {
      if (a.zone === 'drawn') {
        p.drawnCount += int(a.count);
      } else if (p.id !== m.me) {
        p.handCount += int(a.count);
      }
    }
    m.deckCount = int(a.deck_count, m.deckCount);
    refreshPiles(view);
    if (p !== undefined) {
      refreshOne(view, p.id);
    }
  },

  // N9（本人）: ID を手札（または引いた直後の置き場）に加える
  handCardsAdded(view, a) {
    const m = view.m;
    const ids = (a.cards ?? []).map((/** @type {unknown} */ v) => int(v));
    if (a.zone === 'drawn') {
      m.myDrawn = [...m.myDrawn, ...ids];
    } else {
      m.myHand = [...m.myHand, ...ids];
      if (m.me !== null) {
        m.players[m.me].handCount = m.myHand.length;
      }
    }
    if (m.me !== null) {
      refreshOne(view, m.me);
    }
  },

  // N10: 売却・企画・マリガン・効果で捨てた。ID を公開（捨て札は表向き）
  cardsDiscarded(view, a) {
    const m = view.m;
    const pid = int(a.player_id);
    const ids = (a.cards ?? []).map((/** @type {unknown} */ v) => int(v));
    if (a.reason !== 'deckRefill') {
      removeFromHand(view, pid, ids);
    }
    m.discard = [...m.discard, ...ids];
    refreshPiles(view);
    refreshOne(view, pid);
  },

  // N11: 山札が尽きて捨て札をシャッフルした。順序は載らない
  deckReshuffled(view, a) {
    const m = view.m;
    m.deckCount = int(a.deck_count);
    m.discard = [];
    refreshPiles(view);
  },

  // N12: マヤの増減
  mayaChanged(view, a) {
    const p = view.m.players[int(a.player_id)];
    if (p !== undefined) {
      p.maya = int(a.maya);
      view.panels.get(p.id)?.update({});
    }
  },

  // N13: 先駆コスト
  mayaTransferred(view, a) {
    const m = view.m;
    const from = m.players[int(a.from_player_id)];
    const to = m.players[int(a.to_player_id)];
    if (from !== undefined) {
      from.maya = int(a.maya_from);
      view.panels.get(from.id)?.update({});
    }
    if (to !== undefined) {
      to.maya = int(a.maya_to);
      view.panels.get(to.id)?.update({});
    }
  },

  // N14: 予算
  budgetChanged(view, a) {
    const p = view.m.players[int(a.player_id)];
    if (p !== undefined) {
      p.budget = int(a.budget);
      view.panels.get(p.id)?.update({});
    }
  },

  // N15: 配備。カードが衛星に重なる。配備するカードの ID は公開
  cardDeployed(view, a) {
    const m = view.m;
    const pid = int(a.player_id);
    const found = findSat(view, a.sat_id);
    const card = int(a.card);
    if (a.from_hand !== false) {
      removeFromHand(view, pid, [card]);
    }
    if (found !== null) {
      const level = Math.max(1, int(a.level, found.sat.cards.length + 1));
      const cards = found.sat.cards.filter((c) => c.id !== card);
      cards.splice(level - 1, 0, { id: card, activation: 0 });
      found.sat.cards = cards;
      found.sat.fuelCapacity = int(a.fuel_capacity, found.sat.fuelCapacity);
    }
    m.discard = m.discard.filter((id) => id !== card);
    refreshPiles(view);
    refreshOne(view, pid);
  },

  // N16: 打ち上げ。衛星コマが発射拠点の区域に向きつきで現れる
  satelliteLaunched(view, a) {
    const found = findSat(view, a.sat_id);
    if (found === null) {
      return;
    }
    found.sat.cell = int(a.cell);
    found.sat.dir = String(a.dir);
    found.sat.fuelUsed = 0;
    view.syncPieces();
    refreshOne(view, found.player.id);
  },

  // N17: 転回
  satelliteTurned(view, a) {
    const found = findSat(view, a.sat_id);
    if (found === null) {
      return;
    }
    found.sat.dir = String(a.dir);
    view.pieces.turnSatellite(found.sat.satId, found.sat.dir);
    refreshOne(view, found.player.id);
  },

  // N18: 循環フェイズの移動（全員分を 1 件に。並列に動かす）
  async satellitesMoved(view, a) {
    const moves = /** @type {{ sat_id: number, cell: number, dir: string, reflected?: boolean }[]} */ (a.moves ?? []);
    await Promise.all(moves.map((mv) => view.pieces.moveSatellite(int(mv.sat_id), int(mv.cell), String(mv.dir), mv.reflected === true)));
    for (const mv of moves) {
      const found = findSat(view, mv.sat_id);
      if (found !== null) {
        found.sat.cell = int(mv.cell);
        found.sat.dir = String(mv.dir);
      }
    }
    view.syncPieces();
    view.refreshAllPlayers();
  },

  // N19: 燃料コマを動かす。燃料切れは fuel_used > fuel_capacity
  fuelChanged(view, a) {
    const found = findSat(view, a.sat_id);
    if (found === null) {
      return;
    }
    found.sat.fuelUsed = int(a.fuel_used);
    found.sat.fuelCapacity = int(a.fuel_capacity, found.sat.fuelCapacity);
    view.syncPieces();
    refreshOne(view, found.player.id);
  },

  // N20: ニアミス。その区域を一瞬強調する（続けて N19）
  nearMiss(view, a) {
    const cell = int(a.cell);
    view.highlights.set([{ cell, kind: 'selected' }]);
    window.setTimeout(() => view.stateFrame.restoreHighlights(), view.env.animationsActive() ? 800 : 0);
  },

  // N21: 着陸 / 着水。衛星コマを衛星カードに戻し、燃料コマを初期燃料庫へ
  satelliteLanded(view, a) {
    const found = findSat(view, a.sat_id);
    if (found === null) {
      return;
    }
    found.sat.cell = null;
    found.sat.dir = null;
    found.sat.fuelUsed = 0;
    found.sat.grace = false;
    view.pieces.removeSatellite(found.sat.satId);
    refreshOne(view, found.player.id);
  },

  // N22（本人）: ステージに積んだ通信確立。予約額と残りキューブ
  linkStaged(view, a) {
    const m = view.m;
    if (m.me !== null && a.cubes_left !== undefined) {
      m.players[m.me].cubesLeft = int(a.cubes_left);
      view.panels.get(m.me)?.update({});
    }
  },

  // N23: 通信確立のコミット。キューブが Z 順の場所に現れる
  linkEstablished(view, a) {
    const p = view.m.players[int(a.player_id)];
    if (p === undefined) {
      return;
    }
    const link = { cell: int(a.cell), slot: int(a.slot), level: int(a.level) };
    p.links = [...p.links.filter((l) => l.cell !== link.cell), link];
    p.cubesLeft = int(a.cubes_left, p.cubesLeft);
    view.pieces.setCube(p.id, link, p.colorIndex);
    view.pieces.refreshStackBadges();
    view.panels.get(p.id)?.update({});
  },

  // N24: パス（ログのみ）
  actionPassed() {},

  // N25（本人）: 引いた直後の置き場から手札へ
  drawnCardsKept(view, a) {
    const m = view.m;
    const kept = (a.kept ?? []).map((/** @type {unknown} */ v) => int(v));
    m.myDrawn = [];
    m.myHand = [...m.myHand, ...kept];
    if (m.me !== null) {
      m.players[m.me].handCount = m.myHand.length;
      m.players[m.me].drawnCount = 0;
      refreshOne(view, m.me);
    }
  },

  // N26: 窓の終わり。適用済みの ev_id の記録を整理し、窓の問いの枠と待機表示を片付ける。
  // 窓の途中の変化（EUROPA の宣言など）には専用の通知が無いので、ここで全員の表示をモデルに合わせ直す
  windowClosed(view) {
    view.m.window = null;
    view.appliedEvents.clear();
    view.effectUi?.close();
    view.refreshAllPlayers();
    view.refreshMarks();
  },

  // 窓の問いの更新（本人宛て。状態 31 の args は入場時のものなので、答えるたびに残りの問いをここで受け取る。N34）
  effectQuestions(view, a) {
    view.effectUi?.update(a);
  },

  // 窓の待機表示の更新（全員宛て。誰が・どのカードの選択中か。カードは公開情報。N35）
  effectWaiting(view, a) {
    view.effectUi?.setWaiting(a.waiting);
  },

  // N27（保留）: [Use]・自動の強制効果。使用済みマークを付ける。
  // 010 の args にはカード名（source_name）しか無いので、card（P の番号）があればそれを、無ければ英語名で配備カードを探す（要 I-07 と突き合わせ）
  effectUsed(view, a) {
    if (a.mark_used === false) {
      return;
    }
    const found = findSat(view, a.sat_id);
    if (found === null) {
      return;
    }
    const target = a.card !== undefined
      ? found.sat.cards.find((c) => c.id === int(a.card))
      : found.sat.cards.find((c) => c.activation === 0 && chipInfo(projectKey(c.id))?.name === a.source_name && chipInfo(projectKey(c.id))?.activated);
    if (target !== undefined) {
      target.activation = 1;
    } else {
      found.sat.activation = 1;
    }
    refreshOne(view, found.player.id);
  },

  // N28（保留）: [Skip]（使用済みにしない）
  effectSkipped() {},

  // N29（保留）: カードの付け替え・衛星の入れ替えなど、衛星の変化をまとめて反映
  satelliteChanged(view, a) {
    const touched = new Set();
    for (const s of a.sats ?? []) {
      const found = findSat(view, s.sat_id);
      if (found === null) {
        continue;
      }
      found.sat.cell = s.cell === null || s.cell === undefined ? null : int(s.cell);
      found.sat.dir = s.dir === null || s.dir === undefined ? null : String(s.dir);
      found.sat.fuelUsed = int(s.fuel_used);
      if (Array.isArray(s.cards)) {
        found.sat.cards = s.cards.map((/** @type {unknown} */ id) => ({ id: int(id), activation: found.sat.cards.find((c) => c.id === int(id))?.activation ?? 0 }));
      }
      if (s.fuel_capacity !== undefined) {
        found.sat.fuelCapacity = int(s.fuel_capacity);
      }
      touched.add(found.player.id);
    }
    view.syncPieces();
    for (const id of touched) {
      refreshOne(view, /** @type {number} */ (id));
    }
  },

  // N30: ゲーム終了時の手札の換金。カードを公開し、捨て札へ
  handCashedOut(view, a) {
    const m = view.m;
    const pid = int(a.player_id);
    const ids = (a.cards ?? []).map((/** @type {unknown} */ v) => int(v));
    removeFromHand(view, pid, ids);
    m.discard = [...m.discard, ...ids];
    if (m.players[pid] !== undefined) {
      m.players[pid].maya = int(a.maya, m.players[pid].maya);
      m.players[pid].handCount = 0;
    }
    refreshPiles(view);
    refreshOne(view, pid);
  },

  // N31: 終了条件を満たした
  gameEndTriggered(view) {
    view.band.set({ endNote: true });
  },

  // N32: 名声の内訳。名声は終了時にだけ出す（001 §12）
  finalScore(view, a) {
    const pid = int(a.player_id);
    view.areas.get(pid)?.setMarks({ isBase: view.m.basePlayer === pid, finalFame: int(a.fame) });
    view.band.set({ phase: 'END' });
    view.score.add(scoreFromNotification(a));
  },

  // N36: 途中の名声（ゲームオプション 105 が Visible のときだけ届く。ミッションを除く）。パネルと自分の領域に出す
  publicFameUpdated(view, a) {
    view.m.publicFame = mergePublicFame(view.m.publicFame, a.fame);
    view.refreshFame();
  },

  // N33: 山と手札の枚数カウンターを再同期（変更があった者だけ）
  countersUpdate(view, a) {
    const m = view.m;
    if (a.deck_count !== undefined) {
      m.deckCount = int(a.deck_count);
    }
    if (a.discard_count !== undefined && int(a.discard_count) === 0) {
      m.discard = [];
    }
    for (const [pid, c] of Object.entries(a.players ?? {})) {
      const p = m.players[int(pid)];
      if (p === undefined) {
        continue;
      }
      const counters = /** @type {{ handCount?: number, drawnCount?: number, cubesLeft?: number }} */ (c);
      if (counters.handCount !== undefined && p.id !== m.me) {
        p.handCount = int(counters.handCount);
      }
      if (counters.drawnCount !== undefined) {
        p.drawnCount = int(counters.drawnCount);
      }
      if (counters.cubesLeft !== undefined) {
        p.cubesLeft = int(counters.cubesLeft);
      }
    }
    refreshPiles(view);
    view.refreshAllPlayers();
  },
};

/**
 * 通知 1 件を処理する。保留通知（ev_id 付き）は、先送りで適用済みなら画面に反映しない。
 * @param {GameView} view
 * @param {string} name 通知名（notif_ の後ろ）
 * @param {Record<string, any>} args
 * @returns {Promise<void>}
 */
export async function dispatchNotification(view, name, args) {
  const handler = HANDLERS[name];
  if (handler === undefined) {
    return;
  }
  if (args.ev_id !== undefined) {
    const ev = String(args.ev_id);
    if (view.appliedEvents.has(ev)) {
      return;
    }
    view.appliedEvents.add(ev);
  }
  await handler(view, args);
  view.emitModelChanged(name);
}
