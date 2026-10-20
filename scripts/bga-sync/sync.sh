#!/usr/bin/env bash
# BGA Studio への SFTP 同期（仕様書 006 第 3・6 節（段階 1: test と dry-run のみ。sync は段階 2 で追加））。
# 使い方: scripts/bga-sync/sync.sh <test|dry-run>
# 必要な環境変数（ログに出さない）:
#   BGA_KEY_FILE          秘密鍵ファイル（権限 600）
#   BGA_KNOWN_HOSTS_FILE  known_hosts ファイル
#   BGA_SFTP_USER / BGA_SFTP_HOST / BGA_SFTP_PORT / BGA_REMOTE_DIR
# 注意: set -x を使わない。lftp の debug を有効にしない。
set -euo pipefail

mode="${1:-}"
case "$mode" in test | dry-run) ;; *)
  echo "使い方: $0 <test|dry-run>" >&2
  exit 2
  ;;
esac

for v in BGA_KEY_FILE BGA_KNOWN_HOSTS_FILE BGA_SFTP_USER BGA_SFTP_HOST BGA_SFTP_PORT BGA_REMOTE_DIR; do
  if [ -z "${!v:-}" ]; then
    echo "環境変数 $v が空です" >&2
    exit 2
  fi
done

# lftp -c に文字列として渡すため、許可する文字を絞る（コマンド注入の防止）
[[ "$BGA_SFTP_USER" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "BGA_SFTP_USER に使えない文字があります" >&2; exit 2; }
[[ "$BGA_SFTP_HOST" =~ ^[A-Za-z0-9.-]+$ ]] || { echo "BGA_SFTP_HOST に使えない文字があります" >&2; exit 2; }
[[ "$BGA_SFTP_PORT" =~ ^[0-9]+$ ]] || { echo "BGA_SFTP_PORT は数字のみです" >&2; exit 2; }
[[ "$BGA_REMOTE_DIR" =~ ^/[A-Za-z0-9._/-]*$ ]] || { echo "BGA_REMOTE_DIR に使えない文字があります" >&2; exit 2; }
[[ "$BGA_KEY_FILE$BGA_KNOWN_HOSTS_FILE" =~ ^[A-Za-z0-9._/-]+$ ]] || { echo "鍵ファイルのパスに使えない文字があります" >&2; exit 2; }

remote="${BGA_REMOTE_DIR%/}"
summary="${GITHUB_STEP_SUMMARY:-/dev/null}"

# lftp の共通設定。ダミーのパスワード（dummy）は入力待ちを避けるため。鍵認証が使われる
lftp_run() {
  lftp -c "
    set cmd:fail-exit yes
    set net:max-retries 3
    set net:timeout 30
    set sftp:auto-confirm no
    set sftp:connect-program 'ssh -a -x -i $BGA_KEY_FILE -o IdentitiesOnly=yes -o UserKnownHostsFile=$BGA_KNOWN_HOSTS_FILE -o StrictHostKeyChecking=yes -o BatchMode=yes'
    open -p $BGA_SFTP_PORT -u $BGA_SFTP_USER,dummy sftp://$BGA_SFTP_HOST
    $1
  "
}

# --- test: 接続して一覧を表示するだけ（書き込みしない）---
run_test() {
  local out rc=0 failed=0
  out="$(mktemp)"
  {
    echo "## BGA Studio 接続テスト"
    echo
    echo "- lftp: $(lftp --version | head -n1)"
    echo "- ssh: $(ssh -V 2>&1)"
    echo
  } >>"$summary"

  # 1) ホームの一覧（必須）。2) リモートパス（必須）。3) 末尾スラッシュ付き（参考。仕様書 C6）
  local label cmd required
  while IFS='|' read -r label cmd required; do
    echo "== $label =="
    rc=0
    lftp_run "$cmd" </dev/null >"$out" 2>&1 || rc=$?
    cat "$out"
    {
      echo "### $label: $([ "$rc" -eq 0 ] && echo 成功 || echo "失敗 (終了コード $rc)")"
      echo
      echo '```text'
      cat "$out"
      echo '```'
      echo
    } >>"$summary"
    if [ "$rc" -ne 0 ] && [ "$required" = required ]; then failed=1; fi
  done <<LIST
ホームの一覧 (ls)|ls|required
リモートパスの一覧 (ls $remote)|ls $remote|required
末尾スラッシュ付き (ls $remote/)|ls $remote/|optional
LIST
  rm -f "$out"
  return "$failed"
}

# --- dry-run: 転送予定の表示（段階 2 で sync も同じ関数を使う）---
# 3 段階（img → modules → ルートのファイル）。削除なし（--delete を付けない）。
# 段階 1 では呼び出し側が必ず --dry-run を渡す。
transfer() {
  local flag="$1" # 段階 1 は "--dry-run" のみ
  [ "$flag" = "--dry-run" ] || { echo "段階 1 では --dry-run 以外を実行しません" >&2; return 1; }
  [ -d src ] || { echo "src/ がありません（初期ファイルの取り込み前です）" >&2; return 1; }
  local cmds=""
  if [ -d src/img ]; then
    cmds+="mirror --reverse --no-perms --parallel=2 --verbose $flag src/img $remote/img; "
  fi
  if [ -d src/modules ]; then
    cmds+="mirror --reverse --no-perms --parallel=2 --verbose $flag src/modules $remote/modules; "
  fi
  # ルート直下のファイルのみ（CSS・設定・スキーマ）。サブディレクトリは上で処理済み
  cmds+="mirror --reverse --no-perms --no-recursion --verbose $flag src $remote"
  lftp_run "$cmds"
}

case "$mode" in
  test)
    run_test
    ;;
  dry-run)
    {
      echo "## BGA Studio dry-run（転送せず予定を表示）"
      echo
    } >>"$summary"
    log="$(mktemp)"
    rc=0
    transfer "--dry-run" </dev/null >"$log" 2>&1 || rc=$?
    cat "$log"
    { echo '```text'; cat "$log"; echo '```'; } >>"$summary"
    rm -f "$log"
    exit "$rc"
    ;;
esac
