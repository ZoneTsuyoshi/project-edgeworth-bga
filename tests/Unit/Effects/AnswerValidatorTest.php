<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects;

use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\AnswerValidator;
use Bga\Games\ProjectEdgeworth\Effects\Question\Field;
use Bga\Games\ProjectEdgeworth\Effects\Question\FieldType;
use PHPUnit\Framework\TestCase;

/**
 * 答えの共通の検証（011 §6-2。クライアントは検証しないので、サーバーが必ず弾く）。
 */
final class AnswerValidatorTest extends TestCase
{
    /**
     * @param list<Field> $fields
     * @param array<string, mixed> $values
     */
    private function ok(array $fields, array $values): bool
    {
        return (new AnswerValidator())->validate($fields, new Answer($values))->ok;
    }

    public function testSingleChoicesMustBeCandidates(): void
    {
        foreach ([FieldType::Satellite, FieldType::Area, FieldType::Direction] as $type) {
            $fields = [new Field('x', $type, [3, 'NE'])];
            $this->assertTrue($this->ok($fields, ['x' => 3]), $type->value);
            $this->assertTrue($this->ok($fields, ['x' => '3']), $type->value);
            $this->assertFalse($this->ok($fields, ['x' => 4]), $type->value);
            $this->assertFalse($this->ok($fields, []), $type->value);
            $this->assertFalse($this->ok($fields, ['x' => [3]]), $type->value);
        }
    }

    public function testAmountUsesCandidatesOrRange(): void
    {
        $this->assertTrue($this->ok([new Field('n', FieldType::Amount, [1, 3])], ['n' => 3]));
        $this->assertFalse($this->ok([new Field('n', FieldType::Amount, [1, 3])], ['n' => 2]));
        $range = [new Field('n', FieldType::Amount, [], 0, 3)];
        $this->assertTrue($this->ok($range, ['n' => 0]));
        $this->assertFalse($this->ok($range, ['n' => 4]));
        $this->assertFalse($this->ok($range, ['n' => -1]));
        $this->assertFalse($this->ok($range, ['n' => 'abc']));
    }

    public function testCardsNeedDistinctCandidatesWithinTheCount(): void
    {
        $fields = [new Field('c', FieldType::Cards, [10, 11, 12], 1, 2)];

        $this->assertTrue($this->ok($fields, ['c' => [10]]));
        $this->assertTrue($this->ok($fields, ['c' => [10, 12]]));
        $this->assertFalse($this->ok($fields, ['c' => []]));
        $this->assertFalse($this->ok($fields, ['c' => [10, 11, 12]]));
        $this->assertFalse($this->ok($fields, ['c' => [10, 10]]));
        $this->assertFalse($this->ok($fields, ['c' => [99]]));
        $this->assertFalse($this->ok($fields, ['c' => 10]));
    }

    public function testOptionalListMayBeOmittedWhenMinIsZero(): void
    {
        $fields = [new Field('mods', FieldType::UseMods, ['P:19'], 0, 1)];

        $this->assertTrue($this->ok($fields, []));
        $this->assertTrue($this->ok($fields, ['mods' => ['P:19']]));
        $this->assertFalse($this->ok($fields, ['mods' => ['P:20']]));
    }

    public function testPairsMustBeListedCandidates(): void
    {
        $fields = [new Field('pair', FieldType::Pairs, [[5, 12], [6, 13]], 1, 1)];

        $this->assertTrue($this->ok($fields, ['pair' => [[5, 12]]]));
        $this->assertFalse($this->ok($fields, ['pair' => [[5, 13]]]));
        $this->assertFalse($this->ok($fields, ['pair' => [[5, 12], [6, 13]]]));
    }

    public function testAssignmentOnlyChecksShapeAndUnknownFieldsAreRejected(): void
    {
        $fields = [new Field('assign', FieldType::Assignment)];

        $this->assertTrue($this->ok($fields, ['assign' => [1 => 2]]));
        $this->assertFalse($this->ok($fields, ['assign' => 'x']));
        $this->assertFalse($this->ok($fields, ['assign' => [], 'extra' => 1]));
    }

    public function testNoFieldsAcceptsAnEmptyAnswerOnly(): void
    {
        $this->assertTrue($this->ok([], []));
        $this->assertFalse($this->ok([], ['x' => 1]));
    }
}
