<?php

namespace App\Support;

/**
 * 上流 tide736 / Open-Meteo 依存の遅延を抑える簡易ファイルキャッシュ。
 */
class ResponseCache
{
    private const PREFIX = 'shioyomi_api_';

    public static function get(string $key): ?array
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            @unlink($path);
            return null;
        }

        if (!is_array($decoded) || !isset($decoded['expires_at'], $decoded['payload'])) {
            @unlink($path);
            return null;
        }

        if ((int)$decoded['expires_at'] < time()) {
            @unlink($path);
            return null;
        }

        return is_array($decoded['payload']) ? $decoded['payload'] : null;
    }

    public static function set(string $key, array $payload, int $ttlSeconds): void
    {
        $ttlSeconds = max(60, $ttlSeconds);
        $path = self::path($key);
        $data = json_encode([
            'expires_at' => time() + $ttlSeconds,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($data === false) {
            return;
        }

        @file_put_contents($path, $data, LOCK_EX);
    }

    public static function key(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    private static function path(string $key): string
    {
        $safe = preg_replace('/[^a-f0-9]/i', '', $key) ?: 'empty';
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::PREFIX . $safe . '.json';
    }
}
