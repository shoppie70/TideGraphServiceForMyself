<?php

namespace App\Support;

/**
 * サイト名・本番ベース URL・サブパス解決。
 * 本番: https://static.sho-tsukamoto.jp/tidegraph
 */
class Site
{
    public const APP_NAME = 'シオヨミ';
    public const APP_TAGLINE = '潮見表・天気・風速';
    public const APP_DESCRIPTION = '選定したスポットの潮汐・天気・風速を素早く確認するための、自分専用の潮見表ツール「シオヨミ」。';
    public const AUTHOR = 'Sho Tsukamoto';
    public const TWITTER_SITE = '@shopico3';

    /** 本番オリジン（末尾スラッシュなし） */
    public const PRODUCTION_ORIGIN = 'https://static.sho-tsukamoto.jp';

    /** 本番サブパス（先頭スラッシュあり・末尾なし） */
    public const PRODUCTION_BASE_PATH = '/tidegraph';

    /**
     * アプリのベースパス（例: '' または '/tidegraph'）。末尾スラッシュなし。
     */
    public static function basePath(): string
    {
        $env = getenv('SHIOYOMI_BASE_PATH');
        if ($env === false || $env === '') {
            $env = getenv('SHIOUMI_BASE_PATH'); // 旧誤記エイリアス
        }
        if ($env !== false && $env !== '') {
            $path = '/' . trim(str_replace('\\', '/', (string)$env), '/');
            return $path === '/' ? '' : $path;
        }

        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

        foreach ([$script, $uriPath] as $candidate) {
            if (preg_match('#^(/(?:tidegraph))(?:/|$)#', $candidate, $m)) {
                return $m[1];
            }
        }

        // DOCUMENT_ROOT 配下にアプリがある場合のフォールバック
        $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $appRoot = realpath(dirname(__DIR__, 2)) ?: '';
        if ($docRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $docRoot) && $appRoot !== $docRoot) {
            $rel = substr($appRoot, strlen($docRoot));
            $rel = '/' . trim(str_replace('\\', '/', $rel), '/');
            return $rel === '/' ? '' : $rel;
        }

        return '';
    }

    /** `<base href>` 用（末尾スラッシュあり） */
    public static function baseHref(): string
    {
        $path = self::basePath();
        return ($path === '' ? '/' : $path . '/');
    }

    /** 本番絶対ベース（末尾スラッシュなし） */
    public static function publicBaseUrl(): string
    {
        return self::PRODUCTION_ORIGIN . self::PRODUCTION_BASE_PATH;
    }

    /**
     * 現在リクエスト向けの絶対オリジン+ベース（ローカルでは検知、不明時は本番）。
     * 末尾スラッシュなし。
     */
    public static function absoluteBaseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $host = $_SERVER['HTTP_HOST'] ?? null;
        if ($host) {
            $scheme = $https ? 'https' : 'http';
            return rtrim($scheme . '://' . $host . self::basePath(), '/');
        }

        return self::publicBaseUrl();
    }

    /**
     * アプリ内パスを絶対 URL に（先頭スラッシュ任意）。
     * 例: url('api/tide.php') → https://.../tidegraph/api/tide.php
     */
    public static function url(string $path = '', ?array $query = null): string
    {
        $path = ltrim($path, '/');
        $base = self::absoluteBaseUrl();
        $url = $path === '' ? $base . '/' : $base . '/' . $path;
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $url;
    }

    /**
     * HTML の href/src 用ルート相対パス（サブパス込み）。
     * 例: path('assets/js/webmcp.js') → /tidegraph/assets/js/webmcp.js
     */
    public static function path(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $base = self::basePath();
        if ($path === '') {
            return $base === '' ? '/' : $base . '/';
        }
        return ($base === '' ? '' : $base) . '/' . $path;
    }

    public static function homePath(): string
    {
        return self::path('');
    }

    public static function defaultTitle(): string
    {
        return self::APP_NAME . ' - ' . self::APP_TAGLINE;
    }

    public static function themeColor(): string
    {
        return '#0072ff';
    }
}
