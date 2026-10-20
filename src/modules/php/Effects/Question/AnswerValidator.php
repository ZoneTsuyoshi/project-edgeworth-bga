<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Question;

/**
 * 答えが問いのフィールドの候補に含まれるかの共通の検証（011 §6-2。zombie の既定の答えも同じ検証を通す）。
 * 部品固有の検証（`Assignment`、組み合わせの整合、支払い）は `ResultPart::validate()` が行う。
 * クライアントは検証しない（表示の絞り込みのみ）ので、サーバーは答えのたびに必ずここを通す。
 */
final class AnswerValidator
{
    /**
     * @param list<Field> $fields
     */
    public function validate(array $fields, Answer $answer): ValidationResult
    {
        $known = [];
        foreach ($fields as $field) {
            $known[$field->key] = true;
            $result = $this->validateField($field, $answer->fields[$field->key] ?? null, array_key_exists($field->key, $answer->fields));
            if (!$result->ok) {
                return $result;
            }
        }
        foreach (array_keys($answer->fields) as $key) {
            if (!isset($known[$key])) {
                return ValidationResult::error("unknown field: $key");
            }
        }

        return ValidationResult::ok();
    }

    private function validateField(Field $field, mixed $value, bool $given): ValidationResult
    {
        $key = $field->key;
        switch ($field->type) {
            case FieldType::Satellite:
            case FieldType::Area:
            case FieldType::Direction:
                if (!$given || !is_scalar($value) || !$this->contains($field->candidates, $value)) {
                    return ValidationResult::error("invalid choice: $key");
                }
                return ValidationResult::ok();

            case FieldType::Amount:
                if (!$given || (!is_int($value) && !(is_string($value) && ctype_digit($value)))) {
                    return ValidationResult::error("invalid amount: $key");
                }
                $amount = (int) $value;
                if ($field->candidates !== []) {
                    return $this->contains($field->candidates, $amount)
                        ? ValidationResult::ok()
                        : ValidationResult::error("amount not allowed: $key");
                }
                return $amount >= $field->min && $amount <= $field->max
                    ? ValidationResult::ok()
                    : ValidationResult::error("amount out of range: $key");

            case FieldType::Cards:
            case FieldType::UseMods:
                $list = $given ? $value : [];
                if (!is_array($list) || !array_is_list($list)) {
                    return ValidationResult::error("invalid list: $key");
                }
                $seen = [];
                foreach ($list as $item) {
                    if (!is_scalar($item) || !$this->contains($field->candidates, $item) || isset($seen[(string) $item])) {
                        return ValidationResult::error("invalid item in $key");
                    }
                    $seen[(string) $item] = true;
                }
                return $this->countWithin($field, count($list));

            case FieldType::Pairs:
                $list = $given ? $value : [];
                if (!is_array($list) || !array_is_list($list)) {
                    return ValidationResult::error("invalid list: $key");
                }
                $seen = [];
                foreach ($list as $pair) {
                    $encoded = json_encode($pair);
                    if (!$this->contains($field->candidates, $pair) || isset($seen[$encoded])) {
                        return ValidationResult::error("invalid pair in $key");
                    }
                    $seen[$encoded] = true;
                }
                return $this->countWithin($field, count($list));

            case FieldType::Assignment:
                return $given && is_array($value) ? ValidationResult::ok() : ValidationResult::error("invalid assignment: $key");
        }
    }

    private function countWithin(Field $field, int $count): ValidationResult
    {
        return $count >= $field->min && $count <= $field->max
            ? ValidationResult::ok()
            : ValidationResult::error("wrong number of items: {$field->key}");
    }

    /**
     * @param list<mixed> $candidates
     */
    private function contains(array $candidates, mixed $value): bool
    {
        foreach ($candidates as $candidate) {
            if (is_array($candidate) || is_array($value)) {
                if (is_array($candidate) && is_array($value) && json_encode($candidate) === json_encode($value)) {
                    return true;
                }
                continue;
            }
            if ((string) $candidate === (string) $value) {
                return true;
            }
        }

        return false;
    }
}
