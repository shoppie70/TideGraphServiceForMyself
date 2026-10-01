<?php

namespace App\Support;

class JsonResponse
{
    public static function send(array $payload, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        self::sendCommonHeaders($headers);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function sendText(string $body, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        self::sendCommonHeaders($headers);
        echo $body;
    }

    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::send(array_merge([
            'ok' => false,
            'error' => $message,
        ], $extra), $status, [
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function handleOptions(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            self::sendCommonHeaders([
                'Cache-Control' => 'public, max-age=86400',
            ]);
            header('Content-Type: application/json; charset=utf-8');
            return true;
        }

        return false;
    }

    /**
     * エージェント向けのテキスト表現（llms.txt と同様に text/plain で通しやすい）。
     */
    public static function tideToText(array $payload): string
    {
        $lines = [];
        $lines[] = '# シオヨミ tide';
        $lines[] = $payload['summary_text'] ?? '';
        $lines[] = '';
        $lines[] = 'date: ' . ($payload['date'] ?? '');
        $lines[] = 'place: ' . ($payload['place']['harbor_name'] ?? $payload['place']['name'] ?? '');
        $lines[] = 'prefecture: ' . ($payload['place']['prefecture'] ?? '');
        $lines[] = 'code: ' . ($payload['place']['code'] ?? '');
        $lines[] = 'moon: ' . ($payload['moon']['title'] ?? '');
        $lines[] = 'sun_rise: ' . ($payload['sun']['rise'] ?? '');
        $lines[] = 'sun_set: ' . ($payload['sun']['set'] ?? '');

        $bite = $payload['bite_score'] ?? null;
        if (is_array($bite)) {
            $peak = $bite['day_peak'] ?? null;
            $lines[] = 'bite_score_name: ' . ($bite['name'] ?? '釣時スコア');
            if (is_array($peak)) {
                $lines[] = sprintf(
                    'bite_score_peak: %s score=%.1f stars=%d',
                    $peak['time'] ?? '-',
                    (float)($peak['score'] ?? 0),
                    (int)($peak['stars'] ?? 0)
                );
            }
        }

        $lines[] = '';
        $lines[] = '## flood';
        foreach ($payload['flood'] ?? [] as $event) {
            $lines[] = ($event['time'] ?? '-') . "\t" . ($event['cm'] ?? '-') . 'cm';
        }
        $lines[] = '';
        $lines[] = '## edd';
        foreach ($payload['edd'] ?? [] as $event) {
            $lines[] = ($event['time'] ?? '-') . "\t" . ($event['cm'] ?? '-') . 'cm';
        }

        $turn = $payload['current_turn'] ?? null;
        $lines[] = '';
        $lines[] = '## current_turn';
        if (is_array($turn)) {
            $lines[] = 'available: ' . (!empty($turn['available']) ? 'true' : 'false');
            $lines[] = 'source: ' . ($turn['source'] ?? '');
            $lines[] = 'source_label: ' . ($turn['source_label'] ?? '');
            foreach ($turn['events'] ?? [] as $event) {
                $lines[] = sprintf(
                    "%s\t%s\t%s\t%s",
                    $event['time'] ?? '-',
                    $event['direction'] ?? '-',
                    $event['label'] ?? '-',
                    !empty($event['approx']) ? 'approx' : 'exact'
                );
            }
        } else {
            $lines[] = 'available: false';
        }

        if (!empty($payload['tide']) && is_array($payload['tide'])) {
            $lines[] = '';
            $lines[] = '## tide_series_20min';
            $lines[] = "time\tcm";
            foreach ($payload['tide'] as $point) {
                $lines[] = ($point['time'] ?? '-') . "\t" . ($point['cm'] ?? '-');
            }
        }

        if (!empty($payload['bite_score']['hourly']) && is_array($payload['bite_score']['hourly'])) {
            $lines[] = '';
            $lines[] = '## bite_score_hourly';
            $lines[] = "time\tscore\tstars";
            foreach ($payload['bite_score']['hourly'] as $row) {
                $lines[] = sprintf(
                    "%s\t%s\t%s",
                    $row['time'] ?? '-',
                    $row['score'] ?? '-',
                    $row['stars'] ?? '-'
                );
            }
        }

        return implode("\n", $lines) . "\n";
    }

    public static function calendarToText(array $payload): string
    {
        $lines = [];
        $lines[] = '# シオヨミ calendar';
        $lines[] = $payload['summary_text'] ?? '';
        $lines[] = '';
        $lines[] = 'place: ' . ($payload['place']['harbor_name'] ?? $payload['place']['name'] ?? '');
        $lines[] = 'prefecture: ' . ($payload['place']['prefecture'] ?? '');
        $lines[] = 'code: ' . ($payload['place']['code'] ?? '');
        $lines[] = sprintf('year_month: %s-%02d', $payload['year'] ?? '', $payload['month'] ?? 0);
        $lines[] = '';
        $lines[] = 'date\tmoon\tflood\tedd';
        foreach ($payload['days'] ?? [] as $day) {
            $flood = self::compactEvents($day['flood'] ?? []);
            $edd = self::compactEvents($day['edd'] ?? []);
            $lines[] = sprintf(
                "%s\t%s\t%s\t%s",
                $day['date'] ?? '',
                $day['moon']['title'] ?? '',
                $flood,
                $edd
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<array{time?: ?string, cm?: ?float}> $events
     */
    private static function compactEvents(array $events): string
    {
        $parts = [];
        foreach ($events as $event) {
            $parts[] = ($event['time'] ?? '-') . '(' . ($event['cm'] ?? '-') . 'cm)';
        }
        return $parts === [] ? '-' : implode(',', $parts);
    }

    private static function sendCommonHeaders(array $headers = []): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Accept, Content-Type');
        header('X-Content-Type-Options: nosniff');
        header('Vary: Accept');

        $cacheControl = $headers['Cache-Control'] ?? 'public, max-age=300';
        header('Cache-Control: ' . $cacheControl);

        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) === 'cache-control') {
                continue;
            }
            header($name . ': ' . $value);
        }
    }
}
