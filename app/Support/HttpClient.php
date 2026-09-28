<?php

namespace App\Support;

/**
 * 上流 HTTP 取得（タイムアウト付き）。エージェント向け API の応答遅延対策。
 */
class HttpClient
{
    public static function get(string $url, int $timeoutSeconds = 4): ?string
    {
        $timeoutSeconds = max(1, $timeoutSeconds);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => "Accept: application/json, text/plain, */*\r\nUser-Agent: Shioyomi/1.0 (+https://static.sho-tsukamoto.jp/tidegraph)\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false || $body === '') {
            return null;
        }

        return $body;
    }
}
