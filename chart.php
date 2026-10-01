<?php

include_once __DIR__ . "/vendor/autoload.php";

use App\Support\PageEmbed;
use App\Support\PlaceResolver;
use App\Support\ShioyomiPayload;
use App\Support\Site;
use Carbon\Carbon;

Carbon::setLocale('ja');

try {
    // 既存 UI: place=28%269 / 推奨 API 互換: prefecture+code
    if (empty($_REQUEST['place']) && !empty($_REQUEST['prefecture']) && !empty($_REQUEST['code'])) {
        $_REQUEST['place'] = $_REQUEST['prefecture'] . '&' . $_REQUEST['code'];
        $_GET['place'] = $_REQUEST['place'];
    }

    $place = PlaceResolver::resolve($_REQUEST);

    if (isset($_REQUEST['year'], $_REQUEST['month'], $_REQUEST['date'])
        && ctype_digit((string)$_REQUEST['year'])
        && ctype_digit((string)$_REQUEST['month'])
        && ctype_digit((string)$_REQUEST['date'])
        && strlen((string)$_REQUEST['date']) <= 2) {
        $date = Carbon::create(
            (int)$_REQUEST['year'],
            (int)$_REQUEST['month'],
            (int)$_REQUEST['date'],
            0,
            0,
            0,
            ShioyomiPayload::TIMEZONE
        );
    } elseif (!empty($_REQUEST['date'])) {
        $date = ShioyomiPayload::parseDate((string)$_REQUEST['date']);
    } else {
        throw new InvalidArgumentException('日時が送信されていません。');
    }

    $shioyomi = ShioyomiPayload::buildTide($place, $date, true);
    $tide_data_array = [
        'status' => 200,
        'tide' => $shioyomi['tide'],
        'sun' => $shioyomi['sun'],
        'edd' => $shioyomi['edd'],
        'flood' => $shioyomi['flood'],
        'moon' => $shioyomi['moon'],
        'port' => $shioyomi['place']['harbor_name'],
        'lat' => $shioyomi['place']['lat'],
        'lng' => $shioyomi['place']['lng'],
    ];
    $weather_data = [
        'status' => $shioyomi['weather'] ? 200 : 400,
        'label' => $shioyomi['weather']['label'] ?? null,
        'icon' => null,
        'temp_max' => $shioyomi['weather']['temp_max'] ?? null,
        'temp_min' => $shioyomi['weather']['temp_min'] ?? null,
        'wind_speed' => $shioyomi['weather']['wind_speed_ms'] ?? [],
    ];

    // 天気アイコンは WeatherService 側の表示用。ラベルから簡易復元
    if ($weather_data['status'] === 200) {
        $weather_data['icon'] = match (true) {
            ($weather_data['label'] ?? '') === '快晴' => '☀️',
            str_contains((string)$weather_data['label'], '曇') => '⛅',
            str_contains((string)$weather_data['label'], '雨') => '☔',
            str_contains((string)$weather_data['label'], '雪') => '❄️',
            str_contains((string)$weather_data['label'], '雷') => '⛈️',
            str_contains((string)$weather_data['label'], '霧') => '🌫️',
            default => '🌤️',
        };
    }

    $request = [
        'year' => (int)$date->year,
        'month' => (int)$date->month,
        'date' => (int)$date->day,
        'prefecture' => $place['prefecture'],
        'code' => $place['code'],
    ];
} catch (Exception $e) {
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

$current_place_val = $request['prefecture'] . '&' . $request['code'];
$place_param = urlencode($current_place_val);
$prev_date = $date->copy()->subDay()->format('Y-m-d');
$next_date = $date->copy()->addDay()->format('Y-m-d');
$prev_url = "?place={$place_param}&date={$prev_date}";
$next_url = "?place={$place_param}&date={$next_date}";
$calendar_url = "calendar.php?place={$place_param}&year={$request['year']}&month={$request['month']}";
$map_url = $shioyomi['map_url'];
$date_str = $date->format('Y-m-d');

$page_title = sprintf(
    '%sの潮見表（%s）| %s',
    $shioyomi['place']['harbor_name'] ?? $place['name'],
    $date_str,
    Site::APP_NAME
);
$page_description = $shioyomi['summary_text'] ?? Site::APP_DESCRIPTION;
$page_canonical = Site::url('chart.php', [
    'place' => $current_place_val,
    'date' => $date_str,
]);

include_once __DIR__ . '/header.php';

?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+JP:wght@400;500;700&display=swap');
    
    body {
        margin: 0;
        font-family: 'Inter', 'Noto Sans JP', sans-serif;
        background-color: #ffffff;
        color: #333;
        overflow: auto;
    }

    .top-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 1rem;
        background: #fff;
        border-bottom: 1px solid #ddd;
        height: 60px;
        box-sizing: border-box;
    }
    
    .header-left, .header-center, .header-right {
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .brand {
        font-weight: 800;
        font-size: 1.2rem;
        color: #0072ff;
        text-decoration: none;
    }

    .form-control {
        padding: 0.4rem 0.5rem;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 0.9rem;
        color: #333;
        background: #fff;
    }

    .nav-btn {
        background: #f0f0f0;
        color: #333;
        text-decoration: none;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        transition: background 0.2s;
        border: 1px solid #ddd;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    .nav-btn:hover {
        background: #e0e0e0;
    }

    .weather-badge {
        display: inline-flex;
        align-items: center;
        background: #f8f9fa;
        padding: 0.3rem 0.6rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        border: 1px solid #eee;
    }

    .content-wrap {
        display: flex;
        flex-direction: row;
        align-items: stretch;
        width: 100%;
        min-height: calc(100svh - 60px);
        box-sizing: border-box;
    }

    .chart-container {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
        min-height: 420px;
        height: calc(100svh - 60px);
    }
    .chart-container canvas {
        position: relative;
        z-index: 1;
        background: transparent;
    }
    /* canvas の背後にも黄色い縦帯（透明部分から見える保険） */
    .peak-band-layer {
        position: absolute;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        overflow: hidden;
    }
    .peak-band-layer .peak-band {
        position: absolute;
        background: rgba(255, 235, 59, 0.45);
        border-left: 2px solid rgba(255, 193, 7, 0.95);
        border-right: 2px solid rgba(255, 193, 7, 0.95);
        box-sizing: border-box;
    }
    /* 時間帯ごとの活性★（チャート上レイヤ。軸は増やさない） */
    .bite-star-layer {
        position: absolute;
        inset: 0;
        z-index: 2;
        pointer-events: none;
        overflow: hidden;
    }
    .bite-star-layer .bite-star-col {
        position: absolute;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0;
        line-height: 1.05;
        font-size: 11px;
        font-weight: 700;
        color: #d97706;
        text-shadow: 0 0 2px rgba(255, 255, 255, 0.95), 0 1px 0 rgba(255, 255, 255, 0.8);
        transform: translateX(-50%);
        white-space: nowrap;
    }
    .bite-star-layer .bite-star-col.is-peak {
        color: #b45309;
        font-size: 12px;
    }
    @media (max-width: 768px) {
        .bite-star-layer .bite-star-col {
            font-size: 9px;
        }
        .bite-star-layer .bite-star-col.is-peak {
            font-size: 10px;
        }
    }

    .tide-info-panel {
        position: static;
        flex: 0 0 280px;
        width: 280px;
        max-width: 100%;
        background: #fff;
        border-left: 1px solid #e5e7eb;
        border-radius: 0;
        padding: 1rem 1rem 1.15rem;
        box-shadow: none;
        max-height: calc(100svh - 60px);
        overflow: auto;
        box-sizing: border-box;
        color: #111827;
    }

    .info-dl {
        display: flex;
        align-items: baseline;
        gap: 0.75rem;
        margin: 0 0 0.65rem;
        font-size: 0.875rem;
        border-bottom: 1px solid #eef2f7;
        padding-bottom: 0.55rem;
    }
    .info-dl:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .info-dt {
        font-weight: 600;
        width: 4.5rem;
        flex: 0 0 4.5rem;
        color: #374151;
        letter-spacing: 0.02em;
    }
    .info-dd {
        text-align: right;
        flex: 1 1 auto;
        margin: 0;
        font-weight: 700;
        color: #111827;
        min-width: 0;
    }
    .info-dd.is-moon { color: #1d4ed8; }
    .info-dd.is-sunset { color: #c2410c; }

    /* 満潮・干潮・転流: 時刻を主、数値/向きを副（Pro Max: hierarchy + contrast） */
    .tide-event-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        width: 100%;
    }
    .tide-event-item {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: baseline;
        column-gap: 0.5rem;
        line-height: 1.3;
        text-align: left;
    }
    .tide-event-item > .tide-event-time {
        grid-column: 1;
    }
    .tide-event-item > .tide-event-dir {
        grid-column: 2;
        justify-self: start;
    }
    .tide-event-item > .tide-event-meta {
        grid-column: 3;
        justify-self: end;
    }
    /* 満潮・干潮: 時刻（主）の直後に cm（副） */
    .tide-event-list.is-flood .tide-event-item,
    .tide-event-list.is-edd .tide-event-item {
        display: flex;
        justify-content: flex-start;
        align-items: baseline;
        gap: 0.55rem;
    }
    .tide-event-list.is-turn .tide-event-item {
        grid-template-columns: auto auto 1fr;
        column-gap: 0.4rem;
    }
    .tide-event-list.is-turn .tide-event-item > .tide-event-meta {
        justify-self: start;
        color: #6b7280 !important;
    }
    .tide-event-time {
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
        font-size: 1.1rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: #111827;
    }
    .tide-event-meta {
        font-size: 0.78rem;
        font-weight: 600;
        color: #4b5563 !important;
        font-variant-numeric: tabular-nums;
    }
    .tide-event-list.is-flood .tide-event-time { color: #b91c1c; }
    .tide-event-list.is-edd .tide-event-time { color: #1d4ed8; }
    .tide-event-list.is-turn .tide-event-time { color: #0f766e; }
    .tide-event-dir {
        font-size: 0.75rem;
        font-weight: 700;
        color: #0f766e;
    }
    .tide-event-empty {
        font-size: 0.85rem;
        font-weight: 600;
        color: #6b7280;
    }
    .info-note {
        margin: 0.15rem 0 0;
        font-size: 0.7rem;
        font-weight: 500;
        line-height: 1.35;
        color: #6b7280;
        text-align: right;
    }
    .info-dl.is-block {
        flex-direction: column;
        align-items: stretch;
        gap: 0.35rem;
    }
    .info-dl.is-block .info-dt {
        width: auto;
        flex: none;
    }
    .info-dl.is-block .info-dd {
        text-align: left;
        width: 100%;
    }

    @media (max-width: 900px) {
        .top-header {
            flex-direction: column;
            height: auto;
            padding: 0.5rem;
            gap: 0.5rem;
        }
        .header-center {
            flex-wrap: wrap;
            justify-content: center;
        }
        .content-wrap {
            flex-direction: column;
            min-height: 0;
        }
        .chart-container {
            width: 100%;
            height: 52vh;
            min-height: 280px;
        }
        .tide-info-panel {
            flex: 0 0 auto;
            width: 100%;
            max-height: none;
            border-left: none;
            border-top: 1px solid #e5e7eb;
            padding: 0.85rem 1rem 1.35rem;
        }
        .info-dl {
            border-bottom: 1px solid #eef2f7;
            margin-bottom: 0.55rem;
            padding-bottom: 0.5rem;
        }
        .info-dl:not(.is-block) {
            align-items: center;
        }
        .info-dl:not(.is-block) .info-dd {
            text-align: right;
        }
    }
</style>

<header class="top-header">
    <div class="header-left">
        <a href="<?php echo htmlspecialchars(Site::homePath(), ENT_QUOTES, 'UTF-8'); ?>" class="brand"><?php echo htmlspecialchars(Site::APP_NAME, ENT_QUOTES, 'UTF-8'); ?> <span style="font-size: 0.75rem; color: #64748b; margin-left: 0.5rem; font-weight: normal;">自分専用の潮汐・天気・風速確認ツール</span></a>
    </div>
    
    <div class="header-center">
        <form action="chart.php" method="GET" style="display: flex; gap: 0.5rem; margin: 0; align-items: center;">
            <select class="form-control" name="place" onchange="this.form.submit()">
                <?php foreach (PLACES as $p): ?>
                    <?php $val = $p['prefecture'] . '&' . $p['code']; ?>
                    <option value="<?php echo $val; ?>" <?php if ($val === $current_place_val) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="date" value="<?php echo $request['year'].'-'.sprintf('%02d',$request['month']).'-'.sprintf('%02d',$request['date']); ?>" onchange="this.form.submit()">
        </form>
        <a href="<?php echo $map_url; ?>" target="_blank" class="nav-btn" title="Google Mapで位置を確認">🗺️ Map</a>
        <a href="<?php echo $prev_url; ?>" class="nav-btn" title="前日">&larr;</a>
        <a href="<?php echo $next_url; ?>" class="nav-btn" title="翌日">&rarr;</a>
    </div>

    <div class="header-right">
        <?php if ($weather_data['status'] === 200): ?>
        <div class="weather-badge">
            <?php echo $weather_data['icon']; ?> <?php echo htmlspecialchars((string)$weather_data['label']); ?> 
            <span style="color: #e74c3c; margin-left: 0.4rem;">H:<?php echo $weather_data['temp_max']; ?>°</span>
            <span style="color: #3498db; margin-left: 0.3rem;">L:<?php echo $weather_data['temp_min']; ?>°</span>
        </div>
        <?php endif; ?>
        <a href="<?php echo $calendar_url; ?>" class="nav-btn" title="カレンダー">📅</a>
    </div>
</header>

<div class="content-wrap">
    <div class="chart-container">
        <div id="peak-band-layer" class="peak-band-layer" aria-hidden="true"></div>
        <canvas id="chart"></canvas>
        <div id="bite-star-layer" class="bite-star-layer" aria-hidden="true"></div>
    </div>

    <section class="tide-info-panel" aria-label="潮汐情報">
        <?php
        // ページ HTML にスコア名称が一切出ないよう埋め込み用ペイロードをサニタイズ
        $uiSummary = $shioyomi['summary_text'] ?? '';
        $embedPayload = $shioyomi;
        if (isset($embedPayload['bite_score']) && is_array($embedPayload['bite_score'])) {
            unset($embedPayload['bite_score']['name'], $embedPayload['bite_score']['description']);
        }
        echo PageEmbed::renderMachineReadableSummary($uiSummary, '潮汐情報の要約');
        ?>
        <?php
        $currentTurn = $shioyomi['current_turn'] ?? ['available' => false, 'events' => [], 'source_label' => 'データなし', 'note' => ''];
        $formatCm = static function ($cm): string {
            if ($cm === null || $cm === '') {
                return '-';
            }
            return rtrim(rtrim(number_format((float)$cm, 1, '.', ''), '0'), '.') . 'cm';
        };
        ?>
        <dl class="info-dl">
            <dt class="info-dt">潮回り</dt>
            <dd class="info-dd is-moon"><?php echo htmlspecialchars($tide_data_array['moon']['title'] ?? ''); ?></dd>
        </dl>
        <dl class="info-dl">
            <dt class="info-dt">日の出</dt>
            <dd class="info-dd"><?php echo htmlspecialchars($tide_data_array['sun']['rise'] ?? ''); ?></dd>
        </dl>
        <dl class="info-dl">
            <dt class="info-dt">日の入</dt>
            <dd class="info-dd is-sunset"><?php echo htmlspecialchars($tide_data_array['sun']['set'] ?? '-'); ?></dd>
        </dl>
        <dl class="info-dl is-block">
            <dt class="info-dt">満潮</dt>
            <dd class="info-dd">
                <?php if (!empty($tide_data_array['flood'])): ?>
                <ul class="tide-event-list is-flood">
                    <?php foreach ($tide_data_array['flood'] as $flood): ?>
                    <li class="tide-event-item">
                        <span class="tide-event-time"><?php echo htmlspecialchars((string)($flood['time'] ?? '-')); ?></span>
                        <span class="tide-event-meta"><?php echo htmlspecialchars($formatCm($flood['cm'] ?? null)); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <span class="tide-event-empty">データなし</span>
                <?php endif; ?>
            </dd>
        </dl>
        <dl class="info-dl is-block">
            <dt class="info-dt">干潮</dt>
            <dd class="info-dd">
                <?php if (!empty($tide_data_array['edd'])): ?>
                <ul class="tide-event-list is-edd">
                    <?php foreach ($tide_data_array['edd'] as $edd): ?>
                    <li class="tide-event-item">
                        <span class="tide-event-time"><?php echo htmlspecialchars((string)($edd['time'] ?? '-')); ?></span>
                        <span class="tide-event-meta"><?php echo htmlspecialchars($formatCm($edd['cm'] ?? null)); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <span class="tide-event-empty">データなし</span>
                <?php endif; ?>
            </dd>
        </dl>
        <dl class="info-dl is-block">
            <dt class="info-dt">転流</dt>
            <dd class="info-dd">
                <?php if (!empty($currentTurn['available']) && !empty($currentTurn['events'])): ?>
                <ul class="tide-event-list is-turn">
                    <?php foreach ($currentTurn['events'] as $turnEvent): ?>
                    <li class="tide-event-item">
                        <span class="tide-event-time"><?php echo htmlspecialchars((string)($turnEvent['time'] ?? '-')); ?></span>
                        <span class="tide-event-dir"><?php echo htmlspecialchars((string)($turnEvent['direction'] ?? '')); ?></span>
                        <span class="tide-event-meta"><?php echo htmlspecialchars((string)($turnEvent['label'] ?? '')); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="info-note"><?php echo htmlspecialchars((string)($currentTurn['source_label'] ?? '海保')); ?> — 海峡推算の参考（航海用ではない）</p>
                <?php elseif (($currentTurn['source'] ?? '') === 'kaiho_unsupported'): ?>
                <span class="tide-event-empty">海保未対応</span>
                <p class="info-note"><?php echo htmlspecialchars((string)($currentTurn['note'] ?? 'この港は海保公開潮流地点に未対応です。')); ?></p>
                <?php else: ?>
                <span class="tide-event-empty">データなし</span>
                <?php if (!empty($currentTurn['note'])): ?>
                <p class="info-note"><?php echo htmlspecialchars((string)$currentTurn['note']); ?></p>
                <?php endif; ?>
                <?php endif; ?>
            </dd>
        </dl>
    </section>
</div>

<?php echo PageEmbed::renderScriptTag($embedPayload); ?>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/webmcp.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/pwa.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
    const tide_data = <?php echo json_encode($tide_data_array['tide'], JSON_THROW_ON_ERROR); ?>;
    const wind_speed = <?php echo json_encode($weather_data['wind_speed'] ?? [], JSON_THROW_ON_ERROR); ?>;
    const bite_peak_hours = <?php echo json_encode($shioyomi['bite_score']['peak_hours'] ?? [], JSON_THROW_ON_ERROR); ?>;
    const bite_hourly = <?php echo json_encode($shioyomi['bite_score']['hourly'] ?? [], JSON_THROW_ON_ERROR); ?>;
    
    const tide_time = get_dataset(tide_data, 'time');
    const tide_cm = get_dataset(tide_data, 'cm');
    
    // 風速データを20分間隔（73点）にマッピング
    // 1時間は3要素(00:00, 00:20, 00:40)。正時の部分にのみ風速を設定し、間はspanGapsで結ぶ
    const mapped_wind_speed = tide_time.map((time, index) => {
        if (index % 3 === 0) {
            let hour = index / 3;
            if (hour >= 24) hour = 23; // 24:00 (index 72) は23時のデータを使用
            return wind_speed[hour] !== undefined ? wind_speed[hour] : null;
        }
        return null;
    });

    const peakHourSet = {};
    (bite_peak_hours || []).forEach(function (h) { peakHourSet[h] = true; });

    const biteByHour = {};
    (bite_hourly || []).forEach(function (row) {
        biteByHour[row.hour] = row;
    });

    function mergePeakHourRanges(hours) {
        const sorted = hours.slice().sort(function (a, b) { return a - b; });
        const ranges = [];
        sorted.forEach(function (hour) {
            const last = ranges[ranges.length - 1];
            if (last && hour === last.end + 1) {
                last.end = hour;
            } else {
                ranges.push({ start: hour, end: hour });
            }
        });
        return ranges;
    }

    function syncPeakBandOverlay(chart) {
        const layer = document.getElementById('peak-band-layer');
        if (!layer || !chart || !chart.canvas || chart.canvas.id !== 'chart') return;
        const xAxis = chart.scales['x-axis-0'];
        const area = chart.chartArea;
        if (!xAxis || !area) return;
        const labels = chart.data.labels || [];
        const hours = Object.keys(peakHourSet).map(Number);
        const ranges = mergePeakHourRanges(hours);
        layer.innerHTML = '';
        ranges.forEach(function (range) {
            const startIdx = range.start * 3;
            const endIdx = Math.min((range.end + 1) * 3, labels.length - 1);
            if (startIdx >= labels.length || startIdx < 0) return;
            const x1 = xAxis.getPixelForTick(startIdx);
            const x2 = xAxis.getPixelForTick(endIdx);
            if (!isFinite(x1) || !isFinite(x2)) return;
            const left = Math.min(x1, x2);
            const width = Math.max(8, Math.abs(x2 - x1));
            const band = document.createElement('div');
            band.className = 'peak-band';
            band.style.left = left + 'px';
            band.style.width = width + 'px';
            band.style.top = area.top + 'px';
            band.style.height = Math.max(0, area.bottom - area.top) + 'px';
            layer.appendChild(band);
        });
    }

    // 各正時のチャート上端に ★ を縦積み（第3軸なし・数値ボックスなし）
    function syncBiteStarOverlay(chart) {
        const layer = document.getElementById('bite-star-layer');
        if (!layer || !chart || !chart.canvas || chart.canvas.id !== 'chart') return;
        const xAxis = chart.scales['x-axis-0'];
        const area = chart.chartArea;
        if (!xAxis || !area) return;
        const labels = chart.data.labels || [];
        layer.innerHTML = '';
        for (let hour = 0; hour < 24; hour++) {
            const row = biteByHour[hour];
            if (!row) continue;
            const stars = Math.max(0, Math.min(5, Number(row.stars) || 0));
            if (stars <= 0) continue;
            const idx = hour * 3;
            if (idx >= labels.length) continue;
            const x = xAxis.getPixelForTick(idx);
            if (!isFinite(x)) continue;
            const col = document.createElement('div');
            col.className = 'bite-star-col' + (peakHourSet[hour] ? ' is-peak' : '');
            col.style.left = x + 'px';
            col.style.top = (area.top + 2) + 'px';
            col.textContent = '★'.repeat(stars);
            // 縦積み表示（文字を1つずつ改行）
            col.innerHTML = '';
            for (let s = 0; s < stars; s++) {
                const span = document.createElement('span');
                span.textContent = '★';
                col.appendChild(span);
            }
            layer.appendChild(col);
        }
    }

    Chart.defaults.global.defaultFontColor = '#333';
    Chart.defaults.global.defaultFontFamily = "'Inter', 'Noto Sans JP', sans-serif";
    
    const ctx = document.getElementById('chart').getContext('2d');

    // Chart.js 2.7: はっきりした黄色い縦帯（潮位 fill の上でも黄色と分かる濃さ）＋時間帯★
    if (!window.__SHIOYOMI_PEAK_BAND_PLUGIN__) {
        window.__SHIOYOMI_PEAK_BAND_PLUGIN__ = true;
        Chart.pluginService.register({
            id: 'shioyomiPeakBands',
            beforeDatasetsDraw: function (chart) {
                if (!chart.canvas || chart.canvas.id !== 'chart') return;
                const xAxis = chart.scales['x-axis-0'];
                const area = chart.chartArea;
                if (!xAxis || !area) return;
                const labels = chart.data.labels || [];
                const ctx2 = chart.chart.ctx;
                mergePeakHourRanges(Object.keys(peakHourSet).map(Number)).forEach(function (range) {
                    const startIdx = range.start * 3;
                    const endIdx = Math.min((range.end + 1) * 3, labels.length - 1);
                    if (startIdx >= labels.length || startIdx < 0) return;
                    const x1 = xAxis.getPixelForTick(startIdx);
                    const x2 = xAxis.getPixelForTick(endIdx);
                    if (!isFinite(x1) || !isFinite(x2)) return;
                    const left = Math.min(x1, x2);
                    const width = Math.max(8, Math.abs(x2 - x1));
                    ctx2.save();
                    ctx2.fillStyle = 'rgba(255, 220, 0, 0.40)';
                    ctx2.fillRect(left, area.top, width, area.bottom - area.top);
                    ctx2.restore();
                });
            },
            afterDatasetsDraw: function (chart) {
                if (!chart.canvas || chart.canvas.id !== 'chart') return;
                const xAxis = chart.scales['x-axis-0'];
                const area = chart.chartArea;
                if (!xAxis || !area) return;
                const labels = chart.data.labels || [];
                const ctx2 = chart.chart.ctx;
                mergePeakHourRanges(Object.keys(peakHourSet).map(Number)).forEach(function (range) {
                    const startIdx = range.start * 3;
                    const endIdx = Math.min((range.end + 1) * 3, labels.length - 1);
                    if (startIdx >= labels.length || startIdx < 0) return;
                    const x1 = xAxis.getPixelForTick(startIdx);
                    const x2 = xAxis.getPixelForTick(endIdx);
                    if (!isFinite(x1) || !isFinite(x2)) return;
                    const left = Math.min(x1, x2);
                    const width = Math.max(8, Math.abs(x2 - x1));
                    ctx2.save();
                    // fill の上に薄い黄色を重ね、ベージュ化を防ぐ
                    ctx2.fillStyle = 'rgba(255, 235, 59, 0.35)';
                    ctx2.fillRect(left, area.top, width, area.bottom - area.top);
                    ctx2.strokeStyle = 'rgba(255, 193, 7, 0.95)';
                    ctx2.lineWidth = 2;
                    ctx2.beginPath();
                    ctx2.moveTo(left, area.top);
                    ctx2.lineTo(left, area.bottom);
                    ctx2.moveTo(left + width, area.top);
                    ctx2.lineTo(left + width, area.bottom);
                    ctx2.stroke();
                    ctx2.restore();
                });
                syncPeakBandOverlay(chart);
                syncBiteStarOverlay(chart);
            }
        });
    }

    const adjustStyles = function (chart, width) {
        const tide = chart.data.datasets[0];
        const wind = chart.data.datasets[1];
        if (width < 768) {
            tide.borderWidth = 2;
            tide.pointRadius = 3;
            tide.pointHoverRadius = 5;
            tide.pointHoverBorderWidth = 2;
            tide.pointBorderWidth = 2;
            if (wind) {
                wind.borderWidth = 2;
                wind.pointRadius = 2.5;
            }
        } else {
            tide.borderWidth = 7;
            tide.pointRadius = 3;
            tide.pointHoverRadius = 6;
            tide.pointHoverBorderWidth = 2;
            tide.pointBorderWidth = 2;
            if (wind) {
                wind.borderWidth = 3;
                wind.pointRadius = 2;
            }
        }
    };
    
    const myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: tide_time,
            datasets: [
                {
                    label: '潮位 (cm)',
                    yAxisID: 'y-axis-1',
                    data: tide_cm,
                    fill: true,
                    borderColor: '#35b0eb',
                    backgroundColor: 'rgba(169, 227, 255, 0.5)',
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#35b0eb',
                    pointBorderWidth: 2,
                    lineTension: 0.5,
                    borderWidth: 7,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointHoverBorderWidth: 2,
                },
                {
                    label: '風速 (m/s)',
                    yAxisID: 'y-axis-2',
                    data: mapped_wind_speed,
                    fill: false,
                    spanGaps: true,
                    borderColor: '#2ecc71',
                    backgroundColor: '#2ecc71',
                    borderDash: [5, 5],
                    lineTension: 0.3,
                    borderWidth: 3,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                }
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onResize: function (chart, size) {
                adjustStyles(chart, size.width);
                chart.update();
            },
            tooltips: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    afterBody: function (items) {
                        if (!items || !items.length) return;
                        const idx = items[0].index;
                        let hour = Math.floor(idx / 3);
                        if (hour >= 24) hour = 23;
                        const row = biteByHour[hour];
                        if (!row) return;
                        const n = Math.max(0, Math.min(5, Number(row.stars) || 0));
                        return '★'.repeat(n) + '☆'.repeat(5 - n) + ' (' + Number(row.score).toFixed(1) + ')';
                    }
                }
            },
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    generateLabels: function (chart) {
                        const labels = Chart.defaults.global.legend.labels.generateLabels(chart);
                        labels.push({
                            text: '★＝活性目安',
                            fillStyle: '#d97706',
                            strokeStyle: '#d97706',
                            lineWidth: 0,
                            hidden: false,
                            index: -1,
                            datasetIndex: -1
                        });
                        return labels;
                    }
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        autoSkip: false,
                        maxRotation: 0,
                        minRotation: 0,
                        callback: function (value, index) {
                            if (index % 9 === 0 || index === tide_time.length - 1) {
                                return value;
                            }
                            return '';
                        }
                    }
                }],
                yAxes: [
                    {
                        id: 'y-axis-1',
                        type: 'linear',
                        position: 'left',
                        ticks: {
                            beginAtZero: true
                        },
                        scaleLabel: {
                            display: true,
                            labelString: '潮位 (cm)'
                        }
                    },
                    {
                        id: 'y-axis-2',
                        type: 'linear',
                        position: 'right',
                        gridLines: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            beginAtZero: true
                        },
                        scaleLabel: {
                            display: true,
                            labelString: '風速 (m/s)'
                        }
                    }
                ]
            }
        }
    });

    adjustStyles(myChart, window.innerWidth);
    myChart.update();
</script>
</body>
</html>
