<?php

namespace App\Support;

/**
 * 場所パラメータを解決する。
 * 推奨: prefecture + code
 * 互換: place=28%269 / place=28&9（後者は PHP で place=28 とキー "9" になる場合あり）
 */
class PlaceResolver
{
    /**
     * @param array $params $_GET / $_REQUEST 相当
     * @return array{name: string, prefecture: string, code: string}
     */
    public static function resolve(array $params): array
    {
        $prefecture = isset($params['prefecture']) ? (string)$params['prefecture'] : '';
        $code = isset($params['code']) ? (string)$params['code'] : '';

        if ($prefecture !== '' && $code !== '') {
            return self::findOrFail($prefecture, $code);
        }

        if (!empty($params['place'])) {
            $place = (string)$params['place'];
            if (str_contains($place, '&')) {
                [$prefecture, $code] = array_pad(explode('&', $place, 2), 2, '');
                return self::findOrFail($prefecture, $code);
            }

            // place=28&9 → place=28 とキー "9" が別パラメータになるケース
            $prefecture = $place;
            $code = self::findBareCodeKey($params);
            if ($code !== null) {
                return self::findOrFail($prefecture, $code);
            }
        }

        throw new \InvalidArgumentException('場所パラメータが不正です。prefecture と code、または place を指定してください。');
    }

    /**
     * @return array{name: string, prefecture: string, code: string}|null
     */
    public static function find(string $prefecture, string $code): ?array
    {
        foreach (PLACES as $place) {
            if ((string)$place['prefecture'] === (string)$prefecture
                && (string)$place['code'] === (string)$code) {
                return [
                    'name' => $place['name'],
                    'prefecture' => (string)$place['prefecture'],
                    'code' => (string)$place['code'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{name: string, prefecture: string, code: string}
     */
    public static function findOrFail(string $prefecture, string $code): array
    {
        $place = self::find($prefecture, $code);
        if ($place === null) {
            throw new \InvalidArgumentException('登録されていない場所です。');
        }

        return $place;
    }

    /**
     * @return array{name: string, prefecture: string, code: string}|null
     */
    public static function findByName(string $name): ?array
    {
        foreach (PLACES as $place) {
            if ($place['name'] === $name) {
                return [
                    'name' => $place['name'],
                    'prefecture' => (string)$place['prefecture'],
                    'code' => (string)$place['code'],
                ];
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, prefecture: string, code: string}>
     */
    public static function all(): array
    {
        return array_map(static function (array $place): array {
            return [
                'name' => $place['name'],
                'prefecture' => (string)$place['prefecture'],
                'code' => (string)$place['code'],
            ];
        }, PLACES);
    }

    private static function findBareCodeKey(array $params): ?string
    {
        foreach ($params as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }
            $keyStr = (string)$key;
            if ($keyStr === 'place' || $keyStr === 'prefecture' || $keyStr === 'code'
                || $keyStr === 'date' || $keyStr === 'year' || $keyStr === 'month'
                || $keyStr === 'include_series' || $keyStr === 'include_wind') {
                continue;
            }
            // place=28&9 のときキーが港コード、値が空文字になりがち
            if ($value === '' || $value === null || $value === '1' || $value === true) {
                if (ctype_digit($keyStr)) {
                    return $keyStr;
                }
            }
        }

        return null;
    }
}
