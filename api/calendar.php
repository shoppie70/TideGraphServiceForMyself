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
    $req = ApiRequest::parseCalendar($_GET);
    $place = PlaceResolver::resolve(ApiRequest::placeParams($req));
    $year = $req['year'] ?? (int)date('Y');
    $month = $req['month'] ?? (int)date('n');

    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        throw new InvalidArgumentException('year / month が不正です。');
    }

    $cacheKey = ResponseCache::key(
        'calendar',
        $place['prefecture'],
        $place['code'],
        (string)$year,
        (string)$month,
        $req['include_weather'] ? '1' : '0',
        $req['include_places'] ? '1' : '0'
    );

    $payload = ResponseCache::get($cacheKey);
    if ($payload === null) {
        $payload = ShioyomiPayload::buildCalendar($place, $year, $month, [
            'include_weather' => $req['include_weather'],
            'include_places' => $req['include_places'],
        ]);
        ResponseCache::set($cacheKey, $payload, 3600);
    }

    $cacheHeader = ['Cache-Control' => 'public, max-age=600'];
    if (($req['format'] ?? 'json') === 'txt') {
        JsonResponse::sendText(JsonResponse::calendarToText($payload), 200, $cacheHeader);
        exit;
    }

    JsonResponse::send($payload, 200, $cacheHeader);
} catch (InvalidArgumentException $e) {
    JsonResponse::error($e->getMessage(), 400);
} catch (Throwable $e) {
    JsonResponse::error($e->getMessage(), 502);
}
