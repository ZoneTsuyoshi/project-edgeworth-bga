<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support;

use Bga\Games\projectedgeworth\Flow\StateId;

/**
 * src/modules/php/States/ の状態クラスのソースから、状態 ID・種類・遷移を読み出す。
 * 状態クラスは BGA のフレームワークがないと読み込めないため、ソースの字句を見る（書式は ChooseState / GameState の定型）。
 */
final class StateGraph
{
    /**
     * @return array<int, array{class: string, type: string, transitions: array<string, int>}> 状態 ID => 情報
     */
    public static function load(): array
    {
        $graph = [];
        $files = glob(dirname(__DIR__, 2) . '/src/modules/php/States/*.php');
        foreach ($files === false ? [] : $files as $file) {
            $source = (string) file_get_contents($file);
            $class = basename($file, '.php');
            preg_match_all('/StateId::([A-Z_]+)/', $source, $all);
            $names = $all[1];
            if (str_contains($source, 'extends ChooseState')) {
                $id = self::value($names[0]);
                $transitions = ['allConfirmed' => self::value($names[1])];
                $type = 'MULTIPLE_ACTIVE_PLAYER';
            } else {
                preg_match('/id: StateId::([A-Z_]+)/', $source, $idMatch);
                $id = self::value($idMatch[1]);
                preg_match('/type: StateType::([A-Z_]+)/', $source, $typeMatch);
                $type = $typeMatch[1];
                $transitions = [];
                preg_match_all("/'([A-Za-z]+)' => StateId::([A-Z_]+)/", $source, $pairs, PREG_SET_ORDER);
                foreach ($pairs as $pair) {
                    $transitions[$pair[1]] = self::value($pair[2]);
                }
            }
            $graph[$id] = ['class' => $class, 'type' => $type, 'transitions' => $transitions];
        }
        ksort($graph);
        return $graph;
    }

    private static function value(string $constant): int
    {
        return (int) constant(StateId::class . '::' . $constant);
    }
}
