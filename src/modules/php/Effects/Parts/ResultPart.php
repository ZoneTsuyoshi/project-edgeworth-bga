<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts;

use Bga\Games\projectedgeworth\Effects\Outcome;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\Field;
use Bga\Games\projectedgeworth\Effects\Question\ValidationResult;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * 結果部品 R01〜R15（011 §5-1）。`isAvailable`・`buildFields`・`validate`・`preview` は読み取りだけで、DB を書かない
 * （009 の `getArgs` では DB を書かない）。副作用は `apply` だけ。
 */
interface ResultPart
{
    public function stages(EffectSpec $spec): int;

    /** 対象なし・払えない等で使えないか（使えなければ候補から外す。使用済みにもならない） */
    public function isAvailable(PartCall $call): bool;

    /**
     * 選択が要るなら問いのフィールド。なければ空（強制ならば問いを出さずに自動で解決する）。
     *
     * @return list<Field>
     */
    public function buildFields(PartCall $call): array;

    /**
     * [Use] の答えに売却するカードを同梱できるか。支払い C（`EffectSpec::$cost`）があれば既定で同梱できる。支払いのない効果が
     * 解決の中でマヤを払う場合（R09 の配備コスト）は真にして、支払えるかを `validate()` で確認する（008 §2-6）。
     */
    public function acceptsSale(PartCall $call): bool;

    /** フィールドの共通の検証（`AnswerValidator`）の後に呼ぶ、部品固有の検証（組み合わせの整合・支払いなど） */
    public function validate(PartCall $call, Answer $answer): ValidationResult;

    /** 副作用はここだけ。通信確立はステージに積む（`Outcome::$stagedLinks`）。自動の効果は `$answer` が null */
    public function apply(PartCall $call, ?Answer $answer): Outcome;

    /** zombie の既定の答え（null = 辞退。強制の問いで null のときは効果を捨てる） */
    public function defaultAnswer(PartCall $call): ?Answer;

    /**
     * 結果の見込み（サーバーが計算する。008 §2-6）。
     *
     * @return array<string, mixed>
     */
    public function preview(PartCall $call): array;
}
