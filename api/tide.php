<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\JsonResponse;
use App\Support\PlaceResolver;
use App\Support\ShioyomiPayload;
use Carbon\Carbon;

Carbon::setLocale('ja');

if (JsonResponse::handleOptions()) {
    exit;
}

try {
    $place = PlaceResolver::resolve($_GET);
    $date = ShioyomiPayload::parseDate($_GET['date'] ?? null);
    $includeSeries = !isset($_GET['include_series']) || $_GET['include_series'] !== '0';

    $payload = ShioyomiPayload::buildTide($place, $date, $includeSeries);
    JsonResponse::send($payload);
} catch (InvalidArgumentException $e) {
    JsonResponse::error($e->getMessage(), 400);
} catch (Throwable $e) {
    JsonResponse::error($e->getMessage(), 502);
}
