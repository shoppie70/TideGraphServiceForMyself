<?php

namespace App\Services;

/**
 * 転流（上げ⇔下げ／流向切り替わり）表示。
 *
 * 瀬戸内・備讃瀬戸周辺港は第六管区海上保安本部の公開潮流グラフ CGI
 *（tiiki=bisanseto 等）から転流時刻を取得する。
 * 海保地点にマッピングできない港、取得失敗・対象外日は近似にフォールバックせず
 * 「海保未対応」または「データなし」とする（嘘の精密値を出さない）。
 */
class CurrentTurnService
{
    public const SOURCE_KAIHO = 'kaiho_kan6';
    public const SOURCE_UNSUPPORTED = 'kaiho_unsupported';
    public const SOURCE_NONE = 'unavailable';

    /**
     * シオヨミ港 → 海保 tiiki。拠点は備讃瀬戸。
     * キーは "都道府県コード:港コード"。
     *
     * @var array<string, string>
     */
    public const PLACE_TIIKI = [
        '33:2' => 'bisanseto', // 大多府島
        '33:3' => 'bisanseto', // 牛窓
        '33:5' => 'bisanseto', // 小串
        '33:6' => 'bisanseto', // 宇野
        '33:7' => 'bisanseto', // 下津井
        '37:12' => 'bisanseto', // 与島
        '37:7' => 'bisanseto', // 男木島
    ];

    /**
     * @param array{name?: string, prefecture: string, code: string} $place
     * @return array{
     *   available: bool,
     *   source: string,
     *   source_label: string,
     *   note: string,
     *   station: ?string,
     *   tiiki: ?string,
     *   source_url: ?string,
     *   events: list<array{
     *     time: string,
     *     kind: string,
     *     direction: string,
     *     label: string,
     *     approx: bool
     *   }>,
     *   maxima: list<array{time: string, kn: float, direction: string}>
     * }
     */
    public static function compute(array $place, string $date): array
    {
        $key = ($place['prefecture'] ?? '') . ':' . ($place['code'] ?? '');
        $tiiki = self::PLACE_TIIKI[$key] ?? null;

        if ($tiiki === null) {
            return [
                'available' => false,
                'source' => self::SOURCE_UNSUPPORTED,
                'source_label' => '海保未対応',
                'note' => 'この港は海上保安庁第6管区の公開潮流地点（備讃瀬戸・来島海峡・速吸瀬戸）に対応していません。満干潮からの近似は行いません。',
                'station' => null,
                'tiiki' => null,
                'source_url' => KaihoTidalCurrentService::FORECAST_PAGE,
                'events' => [],
                'maxima' => [],
            ];
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return self::emptyResult(
                self::SOURCE_NONE,
                'データなし',
                '日付が不正なため海保潮流を取得できません。',
                $tiiki
            );
        }

        $year = (int)$m[1];
        $month = (int)$m[2];
        $day = (int)$m[3];

        $fetched = KaihoTidalCurrentService::fetchDay($tiiki, $year, $month, $day);
        if ($fetched === null) {
            return self::emptyResult(
                self::SOURCE_NONE,
                'データなし',
                '海上保安庁の潮流グラフを取得できませんでした。しばらくしてから再度お試しください。',
                $tiiki,
                KaihoTidalCurrentService::cgiUrl($tiiki, $year, $month, $day)
            );
        }

        if (empty($fetched['ok']) || empty($fetched['turns'])) {
            $reason = $fetched['error'] ?? 'unknown';
            $note = $reason === 'no_data_for_date'
                ? 'この日付は海上保安庁の公開潮流データがありません。'
                : '海上保安庁の潮流グラフから転流を読み取れませんでした。';

            return self::emptyResult(
                self::SOURCE_NONE,
                'データなし',
                $note,
                $tiiki,
                $fetched['source_url'] ?? null,
                $fetched['station_name'] ?? KaihoTidalCurrentService::STATIONS[$tiiki]['name']
            );
        }

        $events = [];
        foreach ($fetched['turns'] as $turn) {
            $events[] = [
                'time' => $turn['time'],
                'kind' => 'slack',
                'direction' => $turn['direction'],
                'label' => '転流',
                'approx' => false,
            ];
        }

        $maxima = [];
        foreach ($fetched['maxima'] ?? [] as $max) {
            $maxima[] = [
                'time' => $max['time'],
                'kn' => $max['kn'],
                'direction' => $max['direction'],
            ];
        }

        $stationName = $fetched['station_name'] ?: KaihoTidalCurrentService::STATIONS[$tiiki]['name'];

        return [
            'available' => true,
            'source' => self::SOURCE_KAIHO,
            'source_label' => '海保 ' . $stationName,
            'note' => '第六管区海上保安本部の公開潮流推算（' . $stationName . '）です。港そのものの実測ではなく海峡代表地点の参考値で、航海の用には供しません。潮汐表（書誌781）とは計算が異なる場合があります。',
            'station' => $stationName,
            'tiiki' => $tiiki,
            'source_url' => $fetched['source_url'] ?? KaihoTidalCurrentService::cgiUrl($tiiki, $year, $month, $day),
            'events' => $events,
            'maxima' => $maxima,
        ];
    }

    /**
     * @return array{
     *   available: bool,
     *   source: string,
     *   source_label: string,
     *   note: string,
     *   station: ?string,
     *   tiiki: ?string,
     *   source_url: ?string,
     *   events: list,
     *   maxima: list
     * }
     */
    private static function emptyResult(
        string $source,
        string $label,
        string $note,
        ?string $tiiki = null,
        ?string $sourceUrl = null,
        ?string $station = null
    ): array {
        if ($station === null && $tiiki !== null && isset(KaihoTidalCurrentService::STATIONS[$tiiki])) {
            $station = KaihoTidalCurrentService::STATIONS[$tiiki]['name'];
        }

        return [
            'available' => false,
            'source' => $source,
            'source_label' => $label,
            'note' => $note,
            'station' => $station,
            'tiiki' => $tiiki,
            'source_url' => $sourceUrl ?? ($tiiki ? KaihoTidalCurrentService::FORECAST_PAGE : null),
            'events' => [],
            'maxima' => [],
        ];
    }
}
