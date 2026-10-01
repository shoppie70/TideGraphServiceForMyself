<?php

namespace App\Services;

use App\Support\HttpClient;
use App\Support\ResponseCache;

/**
 * 第六管区海上保安本部の公開潮流グラフ CGI から転流・最強流を取得する。
 *
 * 出典: https://www1.kaiho.mlit.go.jp/KAN6/2_kaisyo_new/tidal-current/tidalc_forecast.html
 * CGI:  tidal-current-graph201012_2.cgi?tiiki=&nen=&tuki=&hi=
 *
 * 対応 tiiki はフォーム上 3 地点のみ（bisanseto / kurushima / hayasui）。
 * 航海用ではない推算。潮汐表（書誌781）とは計算が異なる場合あり。
 * 大量一括取得は行わず、表示要求日のみ・ファイルキャッシュ付きで取得する。
 */
class KaihoTidalCurrentService
{
    public const CGI_BASE = 'https://www1.kaiho.mlit.go.jp/KAN6/cgi/tidal-current-graph201012_2.cgi';
    public const FORECAST_PAGE = 'https://www1.kaiho.mlit.go.jp/KAN6/2_kaisyo_new/tidal-current/tidalc_forecast.html';

    /** @var array<string, array{name: string, plus: string, minus: string, txt_prefix: string}> */
    public const STATIONS = [
        'bisanseto' => [
            'name' => '備讃瀬戸',
            'plus' => '西流',
            'minus' => '東流',
            'txt_prefix' => 'bisan',
        ],
        'kurushima' => [
            'name' => '来島海峡',
            'plus' => '南流',
            'minus' => '北流',
            'txt_prefix' => 'kurushima',
        ],
        'hayasui' => [
            'name' => '速吸瀬戸',
            'plus' => '北流',
            'minus' => '南流',
            'txt_prefix' => 'hayasui',
        ],
    ];

    private const CACHE_TTL = 21600; // 6h — 推算は日付固定

    /**
     * @return array{
     *   ok: bool,
     *   tiiki: string,
     *   station_name: string,
     *   date: string,
     *   source_url: string,
     *   plus_label: string,
     *   minus_label: string,
     *   turns: list<array{time: string, direction: string, kind: string}>,
     *   maxima: list<array{time: string, kn: float, sign: string, direction: string}>,
     *   error: ?string
     * }|null  null = ネットワーク等で取得自体に失敗
     */
    public static function fetchDay(string $tiiki, int $year, int $month, int $day): ?array
    {
        if (!isset(self::STATIONS[$tiiki])) {
            return [
                'ok' => false,
                'tiiki' => $tiiki,
                'station_name' => '',
                'date' => self::formatDate($year, $month, $day),
                'source_url' => self::cgiUrl($tiiki, $year, $month, $day),
                'plus_label' => '',
                'minus_label' => '',
                'turns' => [],
                'maxima' => [],
                'error' => 'unknown_tiiki',
            ];
        }

        $date = self::formatDate($year, $month, $day);
        $cacheKey = ResponseCache::key('kaiho-cgi', 'v1', $tiiki, $date);
        $cached = ResponseCache::get($cacheKey);
        if (is_array($cached) && isset($cached['ok'])) {
            return $cached;
        }

        $url = self::cgiUrl($tiiki, $year, $month, $day);
        $raw = HttpClient::get($url, 8, [
            'Accept: text/html,application/xhtml+xml,*/*',
        ]);
        if ($raw === null || $raw === '') {
            return null;
        }

        $html = self::toUtf8($raw);
        $parsed = self::parseCgiHtml($html, $tiiki, $year, $month, $day);
        if ($parsed !== null && $parsed['ok']) {
            ResponseCache::set($cacheKey, $parsed, self::CACHE_TTL);
        }

        return $parsed;
    }

    public static function cgiUrl(string $tiiki, int $year, int $month, int $day): string
    {
        return self::CGI_BASE . '?' . http_build_query([
            'tiiki' => $tiiki,
            'nen' => sprintf('%04d', $year),
            'tuki' => sprintf('%02d', $month),
            'hi' => sprintf('%02d', $day),
        ]);
    }

    public static function formatDate(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function toUtf8(string $raw): string
    {
        if (mb_check_encoding($raw, 'UTF-8') && preg_match('/潮流|転流/', $raw)) {
            return $raw;
        }

        $converted = @mb_convert_encoding($raw, 'UTF-8', 'SJIS-win');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        $converted = @iconv('CP932', 'UTF-8//IGNORE', $raw);
        return is_string($converted) ? $converted : $raw;
    }

    /**
     * @return array{
     *   ok: bool,
     *   tiiki: string,
     *   station_name: string,
     *   date: string,
     *   source_url: string,
     *   plus_label: string,
     *   minus_label: string,
     *   turns: list<array{time: string, direction: string, kind: string}>,
     *   maxima: list<array{time: string, kn: float, sign: string, direction: string}>,
     *   error: ?string
     * }
     */
    public static function parseCgiHtml(string $html, string $tiiki, int $year, int $month, int $day): array
    {
        $station = self::STATIONS[$tiiki];
        $base = [
            'ok' => false,
            'tiiki' => $tiiki,
            'station_name' => $station['name'],
            'date' => self::formatDate($year, $month, $day),
            'source_url' => self::cgiUrl($tiiki, $year, $month, $day),
            'plus_label' => $station['plus'],
            'minus_label' => $station['minus'],
            'turns' => [],
            'maxima' => [],
            'error' => null,
        ];

        if (str_contains($html, 'ご指定日時の観測データはございません')) {
            $base['error'] = 'no_data_for_date';
            return $base;
        }

        if (!str_contains($html, '転流') && !str_contains($html, 'kn')) {
            $base['error'] = 'unrecognized_html';
            return $base;
        }

        // 例: （ 備讃瀬戸　＋:西流　−:東流 ）
        if (preg_match('/（\s*([^＋\+]+?)\s*[＋\+]\s*[:：]\s*([^−\-]+)[−\-]\s*[:：]\s*([^）]+)）/u', $html, $m)) {
            $base['station_name'] = trim(preg_replace('/\s+/u', '', $m[1]) ?? $station['name']);
            $base['plus_label'] = trim($m[2]);
            $base['minus_label'] = trim($m[3]);
        }

        // 「最強または転流の時刻と流速」以降を対象にトークン化
        $section = $html;
        if (preg_match('/最強または転流の時刻と流速[^<]*(.*)$/su', $html, $sec)) {
            $section = $sec[1];
        }

        // 時刻 HH MM （転流） or 時刻 HH MM ± N.N
        if (!preg_match_all(
            '/時刻\s*(\d{1,2})\s+(\d{2})\s*(?:\(\s*転流\s*\)|([＋+\-−－])\s*(\d+(?:\.\d+)?))/u',
            $section,
            $matches,
            PREG_SET_ORDER
        )) {
            $base['error'] = 'no_events_parsed';
            return $base;
        }

        /** @var list<array{time: string, type: string, kn: ?float, sign: ?string}> $timeline */
        $timeline = [];
        foreach ($matches as $match) {
            $time = sprintf('%02d:%02d', (int)$match[1], (int)$match[2]);
            if (isset($match[3]) && $match[3] !== '') {
                $signChar = $match[3];
                $sign = (str_contains($signChar, '-') || str_contains($signChar, '−') || str_contains($signChar, '－'))
                    ? '-'
                    : '+';
                $timeline[] = [
                    'time' => $time,
                    'type' => 'max',
                    'kn' => (float)$match[4],
                    'sign' => $sign,
                ];
            } else {
                $timeline[] = [
                    'time' => $time,
                    'type' => 'slack',
                    'kn' => null,
                    'sign' => null,
                ];
            }
        }

        $plus = $base['plus_label'];
        $minus = $base['minus_label'];
        $turns = [];
        $maxima = [];

        foreach ($timeline as $i => $item) {
            if ($item['type'] === 'max' && $item['sign'] !== null && $item['kn'] !== null) {
                $maxima[] = [
                    'time' => $item['time'],
                    'kn' => $item['kn'],
                    'sign' => $item['sign'],
                    'direction' => $item['sign'] === '+' ? $plus : $minus,
                ];
                continue;
            }

            $prevSign = null;
            for ($j = $i - 1; $j >= 0; $j--) {
                if ($timeline[$j]['sign'] !== null) {
                    $prevSign = $timeline[$j]['sign'];
                    break;
                }
            }
            $nextSign = null;
            for ($j = $i + 1, $n = count($timeline); $j < $n; $j++) {
                if ($timeline[$j]['sign'] !== null) {
                    $nextSign = $timeline[$j]['sign'];
                    break;
                }
            }

            $direction = '転流';
            if ($prevSign !== null && $nextSign !== null && $prevSign !== $nextSign) {
                $from = $prevSign === '+' ? $plus : $minus;
                $to = $nextSign === '+' ? $plus : $minus;
                $direction = $from . '→' . $to;
            } elseif ($prevSign !== null && $nextSign === null) {
                // 翌日側が無い最終転流: 直前最強の逆向きへ
                $from = $prevSign === '+' ? $plus : $minus;
                $to = $prevSign === '+' ? $minus : $plus;
                $direction = $from . '→' . $to;
            } elseif ($prevSign === null && $nextSign !== null) {
                $to = $nextSign === '+' ? $plus : $minus;
                $from = $nextSign === '+' ? $minus : $plus;
                $direction = $from . '→' . $to;
            }

            $turns[] = [
                'time' => $item['time'],
                'direction' => $direction,
                'kind' => 'slack',
            ];
        }

        if ($turns === []) {
            $base['error'] = 'no_turns';
            return $base;
        }

        $base['ok'] = true;
        $base['turns'] = $turns;
        $base['maxima'] = $maxima;
        return $base;
    }
}
