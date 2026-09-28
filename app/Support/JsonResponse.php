<?php

namespace App\Support;

class JsonResponse
{
    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Accept, Content-Type');
        header('Cache-Control: public, max-age=300');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::send(array_merge([
            'ok' => false,
            'error' => $message,
        ], $extra), $status);
    }

    public static function handleOptions(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            self::send(['ok' => true], 204);
            return true;
        }

        return false;
    }
}
