// check-studio.mjs の自己テスト。実行: node --test scripts/lint/
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkSources } from './check-studio.mjs';

const php = (code) => checkSources([{ path: 'a.php', code: `<?php\n${code}` }]).errors;
const css = (code) => checkSources([{ path: 'a.css', code }]).errors;

test('exec( と $exec( は失敗、->execute( と executor( は成功', () => {
  assert.equal(php('$t = $exec($op);').length, 1);
  assert.equal(php('exec("ls");').length, 1);
  assert.equal(php('$pdo->exec("x");').length, 1);
  assert.deepEqual(php('$s->execute(); $executor(1); function execOp() {}'), []);
});

test('他の禁止関数も失敗する', () => {
  for (const f of ['shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec']) {
    assert.equal(php(`${f}('x');`).length, 1, f);
  }
  assert.deepEqual(php('$this->filesystem(1); $ecosystem(2);'), []);
});

test('_( は単独・->_(・::_( で失敗、clienttranslate( や __( は成功', () => {
  assert.equal(php("_('x');").length, 1);
  assert.equal(php("$this->game->_('x');").length, 1);
  assert.equal(php("Game::_('x');").length, 1);
  assert.deepEqual(php("clienttranslate('x'); my_func(1); $a__(2);"), []);
});

test('player_score はバッククォートなしで失敗、あれば成功', () => {
  assert.equal(php('// player_score に保存').length, 1);
  assert.equal(php('// player_score_aux は使わない').length, 1);
  assert.deepEqual(php('$sql = "UPDATE player SET `player_score` = 1, `player_score_aux` = 2";'), []);
});

test('CSS の @import は、コメントでも失敗する', () => {
  assert.equal(css('/* @import は使わない */').length, 1);
  assert.equal(css('@import url(a.css);').length, 1);
  assert.deepEqual(css('.a { color: red; }'), []);
});

test('エラーにはファイル・行・理由が入る', () => {
  const [e] = php('\n$exec(1);');
  assert.match(e, /^a\.php:3: .*exec\(/);
});

test('対象外の拡張子は見ない', () => {
  assert.deepEqual(checkSources([{ path: 'a.jsonc', code: 'player_score_aux' }]).errors, []);
});
