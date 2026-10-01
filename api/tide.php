<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\ApiRequest;
use App\Support\JsonResponse;
use App\Support\PlaceResolver;
use App\Support\ResponseCache;
use App\Support\ShioyomiPayload;
use Carbon\Carbon;

Carbon::setLocale('ja');

if (JsonResponse::handleOptions()) {
    exit;
}

try {
    $req = ApiRequest::parseTide($_GET);
    $place = PlaceResolver::resolve(ApiRequest::placeParams($req));
    $date = ShioyomiPayload::parseDate($req['date'] ?? null);

    $cacheKey = ResponseCache::key(
        'tide',
        'v3-current-turn', // current_turn 追加時にバンプ
        $place['prefecture'],
        $place['code'],
        $date->format('Y-m-d'),
        $req['include_series'] ? '1' : '0',
        $req['include_weather'] ? '1' : '0',
        $req['include_places'] ? '1' : '0'
    );

    $payload = ResponseCache::get($cacheKey);
    if ($payload === null) {
        $payload = ShioyomiPayload::buildTide($place, $date, [
            'include_series' => $req['include_series'],
            'include_weather' => $req['include_weather'],
            'include_places' => $req['include_places'],
        ]);
        // 日付固定データは長めにキャッシュ（上流 tide736 / Open-Meteo 遅延対策）
        ResponseCache::set($cacheKey, $payload, 3600);
    }

    $cacheHeader = ['Cache-Control' => 'public, max-age=600'];
    if (($req['format'] ?? 'json') === 'txt') {
        JsonResponse::sendText(JsonResponse::tideToText($payload), 200, $cacheHeader);
        exit;
    }

    JsonResponse::send($payload, 200, $cacheHeader);
} catch (InvalidArgumentException $e) {
    JsonResponse::error($e->getMessage(), 400);
} catch (Throwable $e) {
    JsonResponse::error($e->getMessage(), 502);
}
