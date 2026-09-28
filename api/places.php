<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\JsonResponse;
use App\Support\PlaceResolver;

if (JsonResponse::handleOptions()) {
    exit;
}

try {
    JsonResponse::send([
        'ok' => true,
        'timezone' => 'Asia/Tokyo',
        'places' => PlaceResolver::all(),
        'count' => count(PLACES),
    ]);
} catch (Throwable $e) {
    JsonResponse::error($e->getMessage(), 500);
}
