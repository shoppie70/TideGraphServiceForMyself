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
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        throw new InvalidArgumentException('year / month が不正です。');
    }

    $payload = ShioyomiPayload::buildCalendar($place, $year, $month);
    JsonResponse::send($payload);
} catch (InvalidArgumentException $e) {
    JsonResponse::error($e->getMessage(), 400);
} catch (Throwable $e) {
    JsonResponse::error($e->getMessage(), 502);
}
