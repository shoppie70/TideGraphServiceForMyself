<?php

namespace App\Services;

/**
 * 転流（上げ⇔下げの切り替わり）目安。
 *
 * tide736 に転流フィールドはなく、海上保安庁の海峡潮流推算も
 * 港コードと1対1で再配布可能な JSON API ではないため、
 * 満潮・干潮時刻に基づく近似を明示ラベル付きで提供する。
 * 海峡の精密な転流時刻（潮汐表・JHOD）そのものは載せない。
 */
class CurrentTurnService
{
    public const SOURCE_APPROX = 'approx_from_tide';
    public const SOURCE_NONE = 'unavailable';

    /**
     * @param list<array{time?: ?string, cm?: ?float}> $flood
     * @param list<array{time?: ?string, cm?: ?float}> $edd
     * @return array{
     *   available: bool,
     *   source: string,
     *   source_label: string,
     *   note: string,
     *   events: list<array{
     *     time: string,
     *     kind: string,
     *     direction: string,
     *     label: string,
     *     approx: bool
     *   }>
     * }
     */
    public static function compute(array $flood, array $edd): array
    {
        $events = [];

        foreach ($flood as $event) {
            $time = self::normalizeTime($event['time'] ?? null);
            if ($time === null) {
                continue;
            }
            $events[] = [
                'time' => $time,
                'kind' => 'flood_slack',
                'direction' => '上げ→下げ',
                'label' => '満潮付近',
                'approx' => true,
            ];
        }

        foreach ($edd as $event) {
            $time = self::normalizeTime($event['time'] ?? null);
            if ($time === null) {
                continue;
            }
            $events[] = [
                'time' => $time,
                'kind' => 'edd_slack',
                'direction' => '下げ→上げ',
                'label' => '干潮付近',
                'approx' => true,
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp($a['time'], $b['time']);
        });

        if ($events === []) {
            return [
                'available' => false,
                'source' => self::SOURCE_NONE,
                'source_label' => 'データなし',
                'note' => 'この港・日付では転流の目安を出せる満潮・干潮時刻がありません。海峡の精密な転流は海上保安庁潮汐表を参照してください。',
                'events' => [],
            ];
        }

        return [
            'available' => true,
            'source' => self::SOURCE_APPROX,
            'source_label' => '満干潮基準の近似',
            'note' => '上げ潮⇔下げ潮の転流は満潮・干潮付近で起きやすい、という目安です。瀬戸などの精密転流時刻（海上保安庁潮汐表／JHOD推算）とは異なります。',
            'events' => $events,
        ];
    }

    private static function normalizeTime(?string $time): ?string
    {
        if ($time === null || $time === '' || $time === '-') {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return null;
        }

        $hour = (int)$m[1];
        $minute = (int)$m[2];
        if ($hour < 0 || $hour > 24 || $minute < 0 || $minute > 59) {
            return null;
        }
        if ($hour === 24 && $minute !== 0) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
