<?php

namespace App\Services;

/**
 * 釣時スコア: 潮汐・潮回り・日出入・風から算出する透明な時間帯スコア（0〜5）。
 * chowari BI とは無関係の独自指標。
 */
class BiteScoreService
{
    public const NAME = '釣時スコア';
    public const DESCRIPTION = '潮の動き・満干潮前後・潮位差・潮回り・風・日の出入りから算出した目安（0〜5）。釣果保証ではない。';

    /**
     * @param list<array{time?: ?string, cm?: ?float}> $tideSeries 20分間隔潮位
     * @param list<array{time?: ?string, cm?: ?float}> $flood
     * @param list<array{time?: ?string, cm?: ?float}> $edd
     * @param array{title?: ?string, age?: mixed, brightness?: mixed} $moon
     * @param array{rise?: ?string, set?: ?string} $sun
     * @param list<float|int|null>|null $windSpeedMs 0〜23時の風速
     * @return array{
     *   name: string,
     *   description: string,
     *   scale: array{min: float, max: float, star_unit: float},
     *   day_peak: array{hour: int, time: string, score: float, stars: int}|null,
     *   hourly: list<array{hour: int, time: string, score: float, stars: int, factors: array<string, float>}>
     * }
     */
    public static function compute(
        array $tideSeries,
        array $flood,
        array $edd,
        array $moon,
        array $sun,
        ?array $windSpeedMs = null
    ): array {
        $hourlyCm = self::hourlyTideCm($tideSeries);
        $flowByHour = self::tideFlowScores($hourlyCm);
        $turnMinutes = self::eventMinutes(array_merge($flood, $edd));
        $rangeFactor = self::tideRangeFactor($flood, $edd);
        $moonFactor = self::moonFactor($moon['title'] ?? null);
        $sunRiseMin = self::parseHmToMinutes($sun['rise'] ?? null);
        $sunSetMin = self::parseHmToMinutes($sun['set'] ?? null);

        $hourly = [];
        $peak = null;

        for ($hour = 0; $hour < 24; $hour++) {
            $midMin = $hour * 60 + 30;
            $tideFlow = $flowByHour[$hour] ?? 0.0;
            $tideTurn = self::turnProximityScore($midMin, $turnMinutes);
            $wind = self::windFactor($windSpeedMs[$hour] ?? null);
            $sunEdge = self::sunEdgeScore($midMin, $sunRiseMin, $sunSetMin);

            $factors = [
                'tide_flow' => round($tideFlow, 2),
                'tide_turn' => round($tideTurn, 2),
                'tide_range' => round($rangeFactor, 2),
                'moon' => round($moonFactor, 2),
                'wind' => round($wind, 2),
                'sun_edge' => round($sunEdge, 2),
            ];

            $raw = $tideFlow + $tideTurn + $rangeFactor + $moonFactor + $wind + $sunEdge;
            $score = round(max(0.0, min(5.0, $raw)), 1);
            $stars = self::scoreToStars($score);

            $row = [
                'hour' => $hour,
                'time' => sprintf('%02d:00', $hour),
                'score' => $score,
                'stars' => $stars,
                'factors' => $factors,
            ];
            $hourly[] = $row;

            if ($peak === null
                || $score > $peak['score']
                || ($score === $peak['score'] && $hour < $peak['hour'])) {
                $peak = [
                    'hour' => $hour,
                    'time' => $row['time'],
                    'score' => $score,
                    'stars' => $stars,
                ];
            }
        }

        return [
            'name' => self::NAME,
            'description' => self::DESCRIPTION,
            'scale' => [
                'min' => 0.0,
                'max' => 5.0,
                'star_unit' => 1.0,
            ],
            'day_peak' => $peak,
            'hourly' => $hourly,
        ];
    }

    public static function scoreToStars(float $score): int
    {
        return (int)max(0, min(5, (int)round($score)));
    }

    public static function starsLabel(int $stars): string
    {
        $stars = max(0, min(5, $stars));
        return str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
    }

    /**
     * @param list<array{time?: ?string, cm?: ?float}> $tideSeries
     * @return array<int, float|null> hour => cm
     */
    private static function hourlyTideCm(array $tideSeries): array
    {
        $byHour = [];
        foreach ($tideSeries as $point) {
            $minutes = self::parseHmToMinutes($point['time'] ?? null);
            if ($minutes === null) {
                continue;
            }
            $hour = intdiv($minutes, 60);
            if ($hour >= 24) {
                $hour = 23;
            }
            // 正時に最も近い点を優先（00/20/40 のうち :00）
            $offset = $minutes % 60;
            if (!isset($byHour[$hour]) || $offset === 0) {
                $byHour[$hour] = isset($point['cm']) ? (float)$point['cm'] : null;
            }
        }
        return $byHour;
    }

    /**
     * @param array<int, float|null> $hourlyCm
     * @return array<int, float> hour => 0..2.0
     */
    private static function tideFlowScores(array $hourlyCm): array
    {
        $deltas = [];
        for ($h = 0; $h < 24; $h++) {
            $cur = $hourlyCm[$h] ?? null;
            $next = $hourlyCm[$h === 23 ? 23 : $h + 1] ?? null;
            if ($h === 23 && isset($hourlyCm[22], $hourlyCm[23])) {
                $deltas[$h] = abs((float)$hourlyCm[23] - (float)$hourlyCm[22]);
                continue;
            }
            if ($cur === null || $next === null) {
                $deltas[$h] = 0.0;
                continue;
            }
            $deltas[$h] = abs((float)$next - (float)$cur);
        }

        $maxDelta = max($deltas ?: [0.0]);
        if ($maxDelta < 1.0) {
            $maxDelta = 1.0;
        }

        $scores = [];
        foreach ($deltas as $h => $delta) {
            $scores[$h] = round(2.0 * ($delta / $maxDelta), 4);
        }
        return $scores;
    }

    /**
     * @param list<array{time?: ?string, cm?: ?float}> $flood
     * @param list<array{time?: ?string, cm?: ?float}> $edd
     */
    private static function tideRangeFactor(array $flood, array $edd): float
    {
        $highs = [];
        foreach ($flood as $e) {
            if (isset($e['cm'])) {
                $highs[] = (float)$e['cm'];
            }
        }
        $lows = [];
        foreach ($edd as $e) {
            if (isset($e['cm'])) {
                $lows[] = (float)$e['cm'];
            }
        }
        if ($highs === [] || $lows === []) {
            return 0.4;
        }

        $range = max($highs) - min($lows);
        return round(min(1.0, max(0.0, $range / 150.0)), 4);
    }

    private static function moonFactor(?string $title): float
    {
        if ($title === null || $title === '') {
            return 0.4;
        }
        if (str_contains($title, '大潮')) {
            return 0.8;
        }
        if (str_contains($title, '中潮')) {
            return 0.55;
        }
        if (str_contains($title, '長潮') || str_contains($title, '若潮')) {
            return 0.4;
        }
        if (str_contains($title, '小潮')) {
            return 0.25;
        }
        return 0.4;
    }

    /**
     * @param list<array{time?: ?string, cm?: ?float}> $events
     * @return list<int>
     */
    private static function eventMinutes(array $events): array
    {
        $mins = [];
        foreach ($events as $event) {
            $m = self::parseHmToMinutes($event['time'] ?? null);
            if ($m !== null) {
                $mins[] = $m;
            }
        }
        return $mins;
    }

    /**
     * @param list<int> $turnMinutes
     */
    private static function turnProximityScore(int $midMin, array $turnMinutes): float
    {
        if ($turnMinutes === []) {
            return 0.3;
        }

        $best = 0.0;
        foreach ($turnMinutes as $turn) {
            $delta = abs($midMin - $turn);
            // 前後 90 分で山型（満干潮ちょうどで 1.0）
            if ($delta <= 90) {
                $best = max($best, 1.0 * (1.0 - ($delta / 90.0)));
            }
        }
        return $best;
    }

    private static function windFactor(float|int|null $ms): float
    {
        if ($ms === null) {
            return 0.35;
        }
        $ms = (float)$ms;
        if ($ms <= 4.0) {
            return 0.7;
        }
        if ($ms <= 8.0) {
            return 0.45;
        }
        if ($ms <= 12.0) {
            return 0.2;
        }
        return 0.05;
    }

    private static function sunEdgeScore(int $midMin, ?int $riseMin, ?int $setMin): float
    {
        $best = 0.0;
        foreach ([$riseMin, $setMin] as $anchor) {
            if ($anchor === null) {
                continue;
            }
            $delta = abs($midMin - $anchor);
            if ($delta <= 90) {
                $best = max($best, 0.5 * (1.0 - ($delta / 90.0)));
            }
        }
        return $best;
    }

    private static function parseHmToMinutes(?string $hm): ?int
    {
        if ($hm === null || $hm === '') {
            return null;
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hm, $m)) {
            return null;
        }
        $h = (int)$m[1];
        $min = (int)$m[2];
        if ($h === 24 && $min === 0) {
            return 24 * 60;
        }
        if ($h < 0 || $h > 23 || $min < 0 || $min > 59) {
            return null;
        }
        return $h * 60 + $min;
    }
}
