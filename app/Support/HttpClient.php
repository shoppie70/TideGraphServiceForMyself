<?php

namespace App\Support;

/**
 * 上流 HTTP 取得（タイムアウト付き）。エージェント向け API の応答遅延対策。
 */
class HttpClient
{
    /**
     * @param list<string> $extraHeaders 追加ヘッダ（例: 'Accept: text/html'）
     */
    public static function get(string $url, int $timeoutSeconds = 4, array $extraHeaders = []): ?string
    {
        $timeoutSeconds = max(1, $timeoutSeconds);
        $headers = [
            'Accept: application/json, text/plain, */*',
            'User-Agent: Shioyomi/1.0 (+https://static.sho-tsukamoto.jp/tidegraph; personal tide chart reference)',
        ];
        foreach ($extraHeaders as $header) {
            if (!is_string($header) || $header === '') {
                continue;
            }
            $name = strtolower((string)strstr($header, ':', true));
            $headers = array_values(array_filter(
                $headers,
                static function (string $h) use ($name): bool {
                    return strtolower((string)strstr($h, ':', true)) !== $name;
                }
            ));
            $headers[] = $header;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers) . "\r\n",
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
