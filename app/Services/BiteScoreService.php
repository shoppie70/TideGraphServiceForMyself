<?php

namespace App\Services;

/**
 * 釣時スコア: 潮汐・潮回り・日出入・風・気圧から算出する透明な時間帯スコア（0〜5）。
 * chowari BI の非公開式は未使用（公開説明の一般要素のみ参考）。
 */
class BiteScoreService
{
    public const NAME = '釣時スコア';
    public const DESCRIPTION = '潮の位相・潮の動き・潮位差・潮回り・風・気圧・日の出入りから算出した目安（0〜5）。釣果保証ではない。';

    /**
     * @param list<array{time?: ?string, cm?: ?float}> $tideSeries 20分間隔潮位
     * @param list<array{time?: ?string, cm?: ?float}> $flood
     * @param list<array{time?: ?string, cm?: ?float}> $edd
     * @param array{title?: ?string, age?: mixed, brightness?: mixed} $moon
     * @param array{rise?: ?string, set?: ?string} $sun
     * @param list<float|int|null>|null $windSpeedMs 0〜23時の風速
     * @param list<float|int|null>|null $pressureHpa 0〜23時の地上気圧 hPa
     * @return array{
     *   name: string,
     *   description: string,
     *   scale: array{min: float, max: float, star_unit: float},
     *   day_peak: array{hour: int, time: string, score: float, stars: int}|null,
     *   peak_hours: list<int>,
     *   hourly: list<array{hour: int, time: string, score: float, stars: int, factors: array<string, float>}>
     * }
     */
    public static function compute(
        array $tideSeries,
        array $flood,
        array $edd,
        array $moon,
        array $sun,
        ?array $windSpeedMs = null,
        ?array $pressureHpa = null
    ): array {
        $hourlyCm = self::hourlyTideCm($tideSeries);
        $flowByHour = self::tideFlowScores($hourlyCm);
        $phaseByHour = self::tidePhaseScores($flood, $edd);
        $rangeFactor = self::tideRangeFactor($flood, $edd);
        $moonFactor = self::moonFactor($moon['title'] ?? null);
        $sunRiseMin = self::parseHmToMinutes($sun['rise'] ?? null);
        $sunSetMin = self::parseHmToMinutes($sun['set'] ?? null);

        $hourly = [];
        $peak = null;

        for ($hour = 0; $hour < 24; $hour++) {
            $midMin = $hour * 60 + 30;
            $tideFlow = $flowByHour[$hour] ?? 0.0;
            $tidePhase = $phaseByHour[$hour] ?? 0.35;
            $wind = self::windFactor($windSpeedMs[$hour] ?? null);
            $pressure = self::pressureFactor($pressureHpa[$hour] ?? null);
            $sunEdge = self::sunEdgeScore($midMin, $sunRiseMin, $sunSetMin);

            $factors = [
                'tide_phase' => round($tidePhase, 2),
                'tide_flow' => round($tideFlow, 2),
                'tide_range' => round($rangeFactor, 2),
                'moon' => round($moonFactor, 2),
                'wind' => round($wind, 2),
                'pressure' => round($pressure, 2),
                'sun_edge' => round($sunEdge, 2),
            ];

            $raw = $tidePhase + $tideFlow + $rangeFactor + $moonFactor + $wind + $pressure + $sunEdge;
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

        $peakHours = [];
        if ($peak !== null) {
            $threshold = max(0.0, (float)$peak['score'] - 0.2);
            foreach ($hourly as $row) {
                if ((float)$row['score'] >= $threshold) {
                    $peakHours[] = (int)$row['hour'];
                }
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
            'peak_hours' => $peakHours,
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
     * @return array<int, float|null>
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
            $offset = $minutes % 60;
            if (!isset($byHour[$hour]) || $offset === 0) {
                $byHour[$hour] = isset($point['cm']) ? (float)$point['cm'] : null;
            }
        }
        return $byHour;
    }

    /**
     * 潮の動き（相対）。上限 1.2。
     *
     * @param array<int, float|null> $hourlyCm
     * @return array<int, float>
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
            $scores[$h] = round(1.2 * ($delta / $maxDelta), 4);
        }
        return $scores;
    }

    /**
     * 潮汐位相スコア（上限 1.5）。
     * 一般的な釣り知見として、上げ潮中盤・下げ潮終盤寄りを高くする（公開の釣り知見を独自重みで表現）。
     * 満干潮ちょうど（転流・潮止まり）は低くする。
     *
     * @param list<array{time?: ?string, cm?: ?float}> $flood
     * @param list<array{time?: ?string, cm?: ?float}> $edd
     * @return array<int, float>
     */
    private static function tidePhaseScores(array $flood, array $edd): array
    {
        $events = [];
        foreach ($flood as $e) {
            $m = self::parseHmToMinutes($e['time'] ?? null);
            if ($m !== null) {
                $events[] = ['min' => $m, 'type' => 'flood'];
            }
        }
        foreach ($edd as $e) {
            $m = self::parseHmToMinutes($e['time'] ?? null);
            if ($m !== null) {
                $events[] = ['min' => $m, 'type' => 'edd'];
            }
        }
        usort($events, static fn($a, $b) => $a['min'] <=> $b['min']);

        $scores = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $mid = $hour * 60 + 30;
            $scores[$hour] = self::phaseScoreAt($mid, $events);
        }
        return $scores;
    }

    /**
     * @param list<array{min: int, type: string}> $events
     */
    private static function phaseScoreAt(int $midMin, array $events): float
    {
        if (count($events) < 2) {
            return 0.35;
        }

        $prev = null;
        $next = null;
        foreach ($events as $event) {
            if ($event['min'] <= $midMin) {
                $prev = $event;
            } elseif ($next === null) {
                $next = $event;
                break;
            }
        }

        // 日境界: 先頭より前 / 末尾より後は端の区間を延長扱い
        if ($prev === null) {
            $prev = $events[count($events) - 1];
            $prev = ['min' => $prev['min'] - 24 * 60, 'type' => $prev['type']];
            $next = $events[0];
        } elseif ($next === null) {
            $next = $events[0];
            $next = ['min' => $next['min'] + 24 * 60, 'type' => $next['type']];
        }

        $span = $next['min'] - $prev['min'];
        if ($span <= 0) {
            return 0.35;
        }
        $progress = ($midMin - $prev['min']) / $span; // 0..1
        $progress = max(0.0, min(1.0, $progress));

        // 上げ（干→満）: 中盤寄り / 下げ（満→干）: 終盤寄り
        if ($prev['type'] === 'edd' && $next['type'] === 'flood') {
            $target = 0.35;
        } elseif ($prev['type'] === 'flood' && $next['type'] === 'edd') {
            $target = 0.70;
        } else {
            $target = 0.50;
        }

        $dist = abs($progress - $target);
        // 近いほど高く、転流付近（progress≈0/1）は低め
        $shape = max(0.0, 1.0 - ($dist / 0.45));
        $slackPenalty = 1.0;
        if ($progress < 0.08 || $progress > 0.92) {
            $slackPenalty = 0.35;
        }

        return round(1.5 * $shape * $slackPenalty, 4);
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
            return 0.35;
        }

        $range = max($highs) - min($lows);
        return round(min(0.8, max(0.0, $range / 160.0)), 4);
    }

    private static function moonFactor(?string $title): float
    {
        if ($title === null || $title === '') {
            return 0.35;
        }
        if (str_contains($title, '大潮')) {
            return 0.7;
        }
        if (str_contains($title, '中潮')) {
            return 0.5;
        }
        if (str_contains($title, '長潮') || str_contains($title, '若潮')) {
            return 0.35;
        }
        if (str_contains($title, '小潮')) {
            return 0.2;
        }
        return 0.35;
    }

    private static function windFactor(float|int|null $ms): float
    {
        if ($ms === null) {
            return 0.3;
        }
        $ms = (float)$ms;
        if ($ms <= 4.0) {
            return 0.55;
        }
        if ($ms <= 8.0) {
            return 0.35;
        }
        if ($ms <= 12.0) {
            return 0.15;
        }
        return 0.05;
    }

    private static function pressureFactor(float|int|null $hpa): float
    {
        if ($hpa === null) {
            return 0.25;
        }
        // 一般知見: 相対的に低めの気圧をやや加点（独自スケール）
        $delta = 1013.25 - (float)$hpa;
        if ($delta >= 0) {
            return round(min(0.55, 0.25 + ($delta / 25.0) * 0.3), 4);
        }
        return round(max(0.05, 0.25 + ($delta / 40.0) * 0.2), 4);
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
                $best = max($best, 0.45 * (1.0 - ($delta / 90.0)));
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
