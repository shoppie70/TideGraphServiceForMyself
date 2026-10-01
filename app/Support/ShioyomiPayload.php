<?php

namespace App\Support;

use App\Services\BiteScoreService;
use App\Services\CalendarService;
use App\Services\TideGraphService;
use App\Services\WeatherService;
use Carbon\Carbon;

/**
 * ページ埋め込み / JSON API / WebMCP で共有するペイロード組み立て。
 */
class ShioyomiPayload
{
    public const TIMEZONE = 'Asia/Tokyo';

    /**
     * @param array{name: string, prefecture: string, code: string} $place
     * @param array{include_series?: bool, include_weather?: bool, include_places?: bool} $options
     */
    public static function buildTide(array $place, Carbon $date, bool|array $includeSeries = true, array $options = []): array
    {
        // 後方互換: 第3引数が bool の場合は include_series
        if (is_array($includeSeries)) {
            $options = $includeSeries;
            $includeSeries = $options['include_series'] ?? true;
        }

        $includeWeather = $options['include_weather'] ?? true;
        $includePlaces = $options['include_places'] ?? true;

        $tideService = new TideGraphService(
            (int)$date->year,
            (int)$date->month,
            (int)$date->day,
            $place['prefecture'],
            $place['code']
        );
        $tide = $tideService->get_tide_data_array();

        if (($tide['status'] ?? 400) !== 200) {
            throw new \RuntimeException($tide['message'] ?? '潮汐データの取得に失敗しました。');
        }

        $weather = ['status' => 400];
        if ($includeWeather) {
            $weatherService = new WeatherService($tide['lat'], $tide['lng'], $date->format('Y-m-d'));
            $weather = $weatherService->get_weather_data();
        }

        $payload = [
            'ok' => true,
            'page' => 'chart',
            'timezone' => self::TIMEZONE,
            'date' => $date->format('Y-m-d'),
            'place' => [
                'name' => $place['name'],
                'prefecture' => $place['prefecture'],
                'code' => $place['code'],
                'harbor_name' => $tide['port'] ?? $place['name'],
                'lat' => $tide['lat'],
                'lng' => $tide['lng'],
            ],
            'moon' => [
                'title' => $tide['moon']['title'] ?? null,
                'age' => $tide['moon']['age'] ?? null,
                'brightness' => $tide['moon']['brightness'] ?? null,
            ],
            'sun' => [
                'rise' => $tide['sun']['rise'] ?? null,
                'set' => $tide['sun']['set'] ?? null,
            ],
            'flood' => self::normalizeTideEvents($tide['flood'] ?? []),
            'edd' => self::normalizeTideEvents($tide['edd'] ?? []),
            'weather' => self::normalizeWeather($weather),
            'map_url' => sprintf(
                'https://www.google.com/maps/search/?api=1&query=%s,%s',
                $tide['lat'],
                $tide['lng']
            ),
            'summary_text' => '',
        ];

        if ($includePlaces) {
            $payload['places'] = PlaceResolver::all();
        }

        $tideSeries = array_map(static function ($point): array {
            return [
                'time' => $point['time'] ?? null,
                'cm' => isset($point['cm']) ? (float)$point['cm'] : null,
            ];
        }, $tide['tide'] ?? []);

        if ($includeSeries) {
            $payload['tide'] = $tideSeries;
        }

        $windSpeedMs = null;
        $pressureHpa = null;
        if (is_array($payload['weather'] ?? null)) {
            $windSpeedMs = $payload['weather']['wind_speed_ms'] ?? null;
            $pressureHpa = $payload['weather']['pressure_hpa'] ?? null;
        }

        $payload['bite_score'] = BiteScoreService::compute(
            $tideSeries,
            $payload['flood'],
            $payload['edd'],
            $payload['moon'],
            $payload['sun'],
            is_array($windSpeedMs) ? $windSpeedMs : null,
            is_array($pressureHpa) ? $pressureHpa : null
        );

        $payload['summary_text'] = self::buildTideSummaryText($payload);

        return $payload;
    }

    /**
     * @param array{name: string, prefecture: string, code: string} $place
     * @param array{include_weather?: bool, include_places?: bool} $options
     */
    public static function buildCalendar(array $place, int $year, int $month, array $options = []): array
    {
        $includeWeather = $options['include_weather'] ?? true;
        $includePlaces = $options['include_places'] ?? true;

        $calendarService = new CalendarService($year, $month, $place['prefecture'], $place['code']);
        $data = $calendarService->get_monthly_tide_data();

        if (($data['status'] ?? 400) !== 200) {
            throw new \RuntimeException('カレンダーデータの取得に失敗しました。');
        }

        $monthlyWeather = [];
        if ($includeWeather) {
            $weatherService = new WeatherService($data['lat'], $data['lng'], '');
            $weatherResponse = $weatherService->get_monthly_weather_data($year, $month);
            $monthlyWeather = ($weatherResponse['status'] ?? 400) === 200 ? ($weatherResponse['data'] ?? []) : [];
        }

        $days = [];
        foreach ($data['chart'] as $dateStr => $dayData) {
            $weather = $monthlyWeather[$dateStr] ?? null;
            $days[] = [
                'date' => $dateStr,
                'moon' => [
                    'title' => $dayData['moon']['title'] ?? null,
                ],
                'sun' => [
                    'rise' => $dayData['sun']['rise'] ?? null,
                    'set' => $dayData['sun']['set'] ?? null,
                ],
                'flood' => self::normalizeTideEvents($dayData['flood'] ?? []),
                'edd' => self::normalizeTideEvents($dayData['edd'] ?? []),
                'weather' => $weather ? [
                    'label' => $weather['label'] ?? null,
                    'temp_max' => $weather['temp_max'] ?? null,
                    'temp_min' => $weather['temp_min'] ?? null,
                ] : null,
            ];
        }

        usort($days, static fn($a, $b) => strcmp($a['date'], $b['date']));

        $payload = [
            'ok' => true,
            'page' => 'calendar',
            'timezone' => self::TIMEZONE,
            'year' => $year,
            'month' => $month,
            'place' => [
                'name' => $place['name'],
                'prefecture' => $place['prefecture'],
                'code' => $place['code'],
                'harbor_name' => $data['port'] ?? $place['name'],
                'lat' => $data['lat'],
                'lng' => $data['lng'],
            ],
            'days' => $days,
            'map_url' => sprintf(
                'https://www.google.com/maps/search/?api=1&query=%s,%s',
                $data['lat'],
                $data['lng']
            ),
        ];

        if ($includePlaces) {
            $payload['places'] = PlaceResolver::all();
        }

        $payload['summary_text'] = sprintf(
            '%s（都道府県コード %s / 港コード %s）の %d年%d月の潮回りカレンダー。日数 %d。',
            $payload['place']['harbor_name'],
            $place['prefecture'],
            $place['code'],
            $year,
            $month,
            count($days)
        );

        return $payload;
    }

    public static function buildIndex(): array
    {
        return [
            'ok' => true,
            'page' => 'index',
            'timezone' => self::TIMEZONE,
            'places' => PlaceResolver::all(),
            'summary_text' => 'シオヨミの場所選択入口。登録港 ' . count(PLACES) . ' 箇所。',
        ];
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return list<array{time: ?string, cm: ?float}>
     */
    private static function normalizeTideEvents(array $events): array
    {
        return array_values(array_map(static function ($event): array {
            return [
                'time' => $event['time'] ?? null,
                'cm' => isset($event['cm']) ? (float)$event['cm'] : null,
            ];
        }, $events));
    }

    private static function normalizeWeather(array $weather): ?array
    {
        if (($weather['status'] ?? 400) !== 200) {
            return null;
        }

        return [
            'label' => $weather['label'] ?? null,
            'temp_max' => $weather['temp_max'] ?? null,
            'temp_min' => $weather['temp_min'] ?? null,
            'wind_speed_ms' => $weather['wind_speed'] ?? [],
            'pressure_hpa' => $weather['surface_pressure'] ?? [],
        ];
    }

    private static function buildTideSummaryText(array $payload): string
    {
        $flood = self::formatEvents($payload['flood'] ?? []);
        $edd = self::formatEvents($payload['edd'] ?? []);
        $weather = $payload['weather'] ?? null;
        $weatherText = $weather
            ? sprintf(
                '天気は%s、気温 最高%s° / 最低%s°。',
                $weather['label'] ?? '不明',
                $weather['temp_max'] ?? '-',
                $weather['temp_min'] ?? '-'
            )
            : '天気データなし。';

        $peak = $payload['bite_score']['day_peak'] ?? null;
        $biteText = $peak
            ? sprintf(
                '%sのピークは %s 頃 %s（%.1f）。',
                BiteScoreService::NAME,
                $peak['time'] ?? '-',
                BiteScoreService::starsLabel((int)($peak['stars'] ?? 0)),
                (float)($peak['score'] ?? 0)
            )
            : '';

        return sprintf(
            '%s（%s）の %s。潮回りは%s。日の出 %s、日の入 %s。満潮 %s。干潮 %s。%s%s',
            $payload['place']['harbor_name'] ?? $payload['place']['name'],
            '都道府県' . $payload['place']['prefecture'] . '/港' . $payload['place']['code'],
            $payload['date'],
            $payload['moon']['title'] ?? '不明',
            $payload['sun']['rise'] ?? '-',
            $payload['sun']['set'] ?? '-',
            $flood,
            $edd,
            $weatherText,
            $biteText
        );
    }

    /**
     * @param list<array{time: ?string, cm: ?float}> $events
     */
    private static function formatEvents(array $events): string
    {
        if ($events === []) {
            return 'なし';
        }

        $parts = [];
        foreach ($events as $event) {
            $parts[] = sprintf('%s（%scm）', $event['time'] ?? '-', $event['cm'] ?? '-');
        }

        return implode('、', $parts);
    }

    public static function parseDate(?string $date): Carbon
    {
        if ($date === null || $date === '') {
            return Carbon::now(self::TIMEZONE)->startOfDay();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date, self::TIMEZONE)->startOfDay();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('日付は YYYY-MM-DD 形式で指定してください。');
        }
    }
}
