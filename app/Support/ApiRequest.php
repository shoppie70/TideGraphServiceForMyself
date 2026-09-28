<?php

namespace App\Support;

/**
 * エージェント向け API のクエリ / PATH_INFO 両対応パーサ。
 *
 * ChatGPT 等の Web fetch はクエリ付き URL を弾く事例があるため、
 * /api/tide.php/{prefecture}/{code}/{date}[.json|.txt] 形式を推奨する。
 */
class ApiRequest
{
    /**
     * @return array{
     *   prefecture: ?string,
     *   code: ?string,
     *   date: ?string,
     *   year: ?int,
     *   month: ?int,
     *   include_series: bool,
     *   include_weather: bool,
     *   include_places: bool,
     *   format: string,
     *   path_params: list<string>
     * }
     */
    public static function parseTide(array $query, ?string $scriptName = null): array
    {
        $path = self::pathSegments($scriptName);
        $format = self::extractFormat($path);
        $includeSeries = self::boolFromQuery($query, 'include_series', true);
        $includeWeather = self::boolFromQuery($query, 'include_weather', true);
        $includePlaces = self::boolFromQuery($query, 'include_places', false);

        $prefecture = isset($query['prefecture']) ? (string)$query['prefecture'] : null;
        $code = isset($query['code']) ? (string)$query['code'] : null;
        $date = isset($query['date']) ? (string)$query['date'] : null;

        // /{prefecture}/{code}/{YYYY-MM-DD}[/summary]
        if (isset($path[0], $path[1]) && ctype_digit((string)$path[0]) && ctype_digit((string)$path[1])) {
            $prefecture = $prefecture ?: (string)$path[0];
            $code = $code ?: (string)$path[1];
            if (isset($path[2]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$path[2])) {
                $date = $date ?: (string)$path[2];
            }
            if (isset($path[3]) && strtolower((string)$path[3]) === 'summary') {
                $includeSeries = false;
            }
        }

        if (isset($query['place']) || self::hasBarePlaceKeys($query)) {
            // PlaceResolver 側で解決。ここではフラグだけ渡す。
        }

        return [
            'prefecture' => $prefecture,
            'code' => $code,
            'date' => $date,
            'year' => null,
            'month' => null,
            'include_series' => $includeSeries,
            'include_weather' => $includeWeather,
            'include_places' => $includePlaces,
            'format' => $format,
            'path_params' => $path,
            'query' => $query,
        ];
    }

    /**
     * @return array{
     *   prefecture: ?string,
     *   code: ?string,
     *   date: ?string,
     *   year: ?int,
     *   month: ?int,
     *   include_series: bool,
     *   include_weather: bool,
     *   include_places: bool,
     *   format: string,
     *   path_params: list<string>,
     *   query: array
     * }
     */
    public static function parseCalendar(array $query, ?string $scriptName = null): array
    {
        $path = self::pathSegments($scriptName);
        $format = self::extractFormat($path);
        $includePlaces = self::boolFromQuery($query, 'include_places', false);
        $includeWeather = self::boolFromQuery($query, 'include_weather', true);

        $prefecture = isset($query['prefecture']) ? (string)$query['prefecture'] : null;
        $code = isset($query['code']) ? (string)$query['code'] : null;
        $year = isset($query['year']) ? (int)$query['year'] : null;
        $month = isset($query['month']) ? (int)$query['month'] : null;

        // /{prefecture}/{code}/{year}/{month}
        if (isset($path[0], $path[1]) && ctype_digit((string)$path[0]) && ctype_digit((string)$path[1])) {
            $prefecture = $prefecture ?: (string)$path[0];
            $code = $code ?: (string)$path[1];
            if (isset($path[2]) && ctype_digit((string)$path[2])) {
                $year = $year ?: (int)$path[2];
            }
            if (isset($path[3]) && ctype_digit((string)$path[3])) {
                $month = $month ?: (int)$path[3];
            }
        }

        return [
            'prefecture' => $prefecture,
            'code' => $code,
            'date' => null,
            'year' => $year,
            'month' => $month,
            'include_series' => false,
            'include_weather' => $includeWeather,
            'include_places' => $includePlaces,
            'format' => $format,
            'path_params' => $path,
            'query' => $query,
        ];
    }

    /**
     * PlaceResolver::resolve 用にクエリ相当配列を合成する。
     *
     * @param array<string, mixed> $parsed
     * @return array<string, mixed>
     */
    public static function placeParams(array $parsed): array
    {
        $params = $parsed['query'] ?? [];
        if (!empty($parsed['prefecture'])) {
            $params['prefecture'] = $parsed['prefecture'];
        }
        if (!empty($parsed['code'])) {
            $params['code'] = $parsed['code'];
        }
        return $params;
    }

    /**
     * @return list<string>
     */
    public static function pathSegments(?string $scriptName = null): array
    {
        $scriptName = $scriptName ?: (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        $pathInfo = (string)($_SERVER['PATH_INFO'] ?? '');

        $extra = '';
        if ($pathInfo !== '') {
            $extra = $pathInfo;
        } elseif ($scriptName !== '' && str_starts_with($requestPath, $scriptName)) {
            $extra = substr($requestPath, strlen($scriptName));
        } else {
            // SCRIPT_NAME が /tidegraph/api/tide.php のとき REQUEST_URI から切り出す
            $base = basename($scriptName);
            if ($base !== '' && preg_match('#' . preg_quote($base, '#') . '(/.*)$#', $requestPath, $m)) {
                $extra = $m[1];
            }
        }

        $extra = trim(str_replace('\\', '/', $extra), '/');
        if ($extra === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $extra), static fn($p) => $p !== ''));
    }

    /**
     * @param list<string> $path
     */
    private static function extractFormat(array &$path): string
    {
        if ($path === []) {
            return 'json';
        }

        $last = $path[count($path) - 1];
        if (preg_match('/^(.+)\.(json|txt)$/i', $last, $m)) {
            $path[count($path) - 1] = $m[1];
            return strtolower($m[2]);
        }

        return 'json';
    }

    private static function boolFromQuery(array $query, string $key, bool $default): bool
    {
        if (!isset($query[$key])) {
            return $default;
        }
        $value = strtolower(trim((string)$query[$key]));
        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        return $default;
    }

    private static function hasBarePlaceKeys(array $query): bool
    {
        return isset($query['place']);
    }
}
