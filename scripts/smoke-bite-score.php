<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\BiteScoreService;
use App\Support\PlaceResolver;
use App\Support\ShioyomiPayload;
use Carbon\Carbon;

Carbon::setLocale('ja');

$place = PlaceResolver::resolve(['place' => '28&9']);
$date = Carbon::now('Asia/Tokyo')->startOfDay();
$payload = ShioyomiPayload::buildTide($place, $date, [
    'include_series' => true,
    'include_weather' => true,
    'include_places' => false,
]);

if (!isset($payload['bite_score']['hourly']) || count($payload['bite_score']['hourly']) !== 24) {
    fwrite(STDERR, "bite_score.hourly missing or not 24\n");
    exit(1);
}

$peak = $payload['bite_score']['day_peak'];
echo "place=" . ($payload['place']['harbor_name'] ?? '') . "\n";
echo "date=" . $payload['date'] . "\n";
echo "moon=" . ($payload['moon']['title'] ?? '') . "\n";
echo "peak=" . $peak['time'] . " score=" . $peak['score'] . " stars=" . BiteScoreService::starsLabel((int)$peak['stars']) . "\n";
echo "sample06=" . json_encode($payload['bite_score']['hourly'][6], JSON_UNESCAPED_UNICODE) . "\n";
echo "ok\n";
