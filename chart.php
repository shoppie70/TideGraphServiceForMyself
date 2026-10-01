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
        overflow: hidden;
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
        position: relative;
        height: calc(100svh - 60px);
        width: 100vw;
    }

    .chart-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    .chart-toolbar {
        position: absolute;
        left: 0.75rem;
        top: 0.75rem;
        z-index: 12;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: center;
    }
    .chart-mode-btn {
        border: 1px solid #cbd5e1;
        background: rgba(255,255,255,0.92);
        color: #334155;
        border-radius: 999px;
        padding: 0.35rem 0.75rem;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
    }
    .chart-mode-btn.is-active {
        background: #0f766e;
        border-color: #0f766e;
        color: #fff;
    }
    .chart-legend-hint {
        font-size: 0.72rem;
        color: #64748b;
        background: rgba(255,255,255,0.88);
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 0.3rem 0.65rem;
    }

    .tide-info-panel {
        position: absolute;
        right: 5rem;
        top: 1rem;
        width: 248px;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 1rem;
        z-index: 10;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        max-height: calc(100% - 2rem);
        overflow: auto;
    }

    .info-dl {
        display: flex;
        margin-bottom: 0.4rem;
        font-size: 0.85rem;
        border-bottom: 1px solid #eee;
        padding-bottom: 0.3rem;
    }
    .info-dl:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .info-dt {
        font-weight: 600;
        width: 35%;
        color: #666;
    }
    .info-dd {
        text-align: right;
        width: 65%;
        margin: 0;
        font-weight: 700;
    }

    .bite-score-block {
        margin-top: 0.6rem;
        padding-top: 0.6rem;
        border-top: 1px solid #eee;
        width: 100%;
    }
    .bite-score-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: #0f766e;
        margin: 0 0 0.25rem;
    }
    .bite-score-peak {
        font-size: 0.95rem;
        font-weight: 800;
        color: #134e4a;
        margin: 0 0 0.25rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: baseline;
    }
    .bite-score-stars {
        letter-spacing: 0.08em;
        color: #d97706;
        font-size: 1.05rem;
    }
    .bite-score-note {
        font-size: 0.7rem;
        line-height: 1.35;
        color: #64748b;
        margin: 0;
    }
    .bite-hour-strip {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 3px;
        margin-top: 0.5rem;
    }
    .bite-hour-cell {
        text-align: center;
        font-size: 0.58rem;
        line-height: 1.15;
        color: #334155;
        border-radius: 4px;
        padding: 0.2rem 0.05rem;
        border: 1px solid transparent;
        background: #f1f5f9;
    }
    .bite-hour-cell strong {
        display: block;
        font-size: 0.62rem;
        color: #0f766e;
    }
    .bite-hour-cell .bite-cell-stars {
        display: block;
        color: #d97706;
        font-size: 0.55rem;
        letter-spacing: -0.05em;
        min-height: 0.7rem;
    }
    .bite-hour-cell.is-peak {
        border-color: #f59e0b;
        box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.35);
    }
    .bite-hour-cell.is-peak strong {
        color: #b45309;
    }
    .bite-hour-cell.lvl-0 { background: #f8fafc; }
    .bite-hour-cell.lvl-1 { background: #ecfdf5; }
    .bite-hour-cell.lvl-2 { background: #d1fae5; }
    .bite-hour-cell.lvl-3 { background: #fef3c7; }
    .bite-hour-cell.lvl-4 { background: #fde68a; }
    .bite-hour-cell.lvl-5 { background: #fbbf24; }

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
            height: auto;
            min-height: calc(100svh - 130px);
            display: flex;
            flex-direction: column;
        }
        .chart-container {
            position: relative;
            height: 52vh;
            min-height: 280px;
        }
        .chart-toolbar {
            position: absolute;
        }
        .tide-info-panel {
            position: relative;
            width: auto;
            max-height: none;
            top: auto;
            right: auto;
            left: auto;
            margin: 0.5rem;
            transform: none;
            display: block;
            padding: 0.75rem;
        }
        .info-dl {
            border-bottom: 1px solid #eee;
            margin-bottom: 0.35rem;
            padding-bottom: 0.3rem;
        }
        .bite-hour-strip {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
        body {
            overflow: auto;
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
        <div class="chart-toolbar" role="group" aria-label="チャート表示切替">
            <button type="button" class="chart-mode-btn is-active" data-chart-mode="standard" id="chartModeStandard">標準</button>
            <button type="button" class="chart-mode-btn" data-chart-mode="bite" id="chartModeBite">釣時強調</button>
            <span class="chart-legend-hint">帯 = ピーク帯 / ★ = 釣時スコア</span>
        </div>
        <canvas id="chart"></canvas>
    </div>

    <section class="tide-info-panel" aria-label="潮汐情報">
        <?php echo PageEmbed::renderMachineReadableSummary($shioyomi['summary_text'], '潮汐情報の要約'); ?>
        <dl class="info-dl">
            <dt class="info-dt">潮回り</dt>
            <dd class="info-dd" style="color: #2980b9;"><?php echo htmlspecialchars($tide_data_array['moon']['title'] ?? ''); ?></dd>
        </dl>
        <dl class="info-dl">
            <dt class="info-dt">日の出</dt>
            <dd class="info-dd"><?php echo htmlspecialchars($tide_data_array['sun']['rise'] ?? ''); ?></dd>
        </dl>
        <dl class="info-dl">
            <dt class="info-dt">日の入</dt>
            <dd class="info-dd" style="color: #e67e22;"><?php echo htmlspecialchars($tide_data_array['sun']['set'] ?? '-'); ?></dd>
        </dl>
        <dl class="info-dl">
            <dt class="info-dt">満潮</dt>
            <dd class="info-dd" style="color: #e74c3c;">
                <?php 
                $floods = [];
                foreach ($tide_data_array['flood'] as $flood) {
                    $floods[] = $flood['time'] . ' (' . $flood['cm'] . 'cm)';
                }
                echo implode(', ', $floods) ?: '-';
                ?>
            </dd>
        </dl>
        <dl class="info-dl">
            <dt class="info-dt">干潮</dt>
            <dd class="info-dd" style="color: #3498db;">
                <?php 
                $edds = [];
                foreach ($tide_data_array['edd'] as $edd) {
                    $edds[] = $edd['time'] . ' (' . $edd['cm'] . 'cm)';
                }
                echo implode(', ', $edds) ?: '-';
                ?>
            </dd>
        </dl>
        <?php
        $biteScore = $shioyomi['bite_score'] ?? null;
        $bitePeak = is_array($biteScore) ? ($biteScore['day_peak'] ?? null) : null;
        ?>
        <?php if (is_array($biteScore) && is_array($bitePeak)): ?>
        <?php
            $peakHourSet = [];
            foreach (($biteScore['peak_hours'] ?? []) as $ph) {
                $peakHourSet[(int)$ph] = true;
            }
        ?>
        <div class="bite-score-block">
            <p class="bite-score-title"><?php echo htmlspecialchars((string)$biteScore['name'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="bite-score-peak">
                <span>ピーク <?php echo htmlspecialchars((string)$bitePeak['time'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="bite-score-stars" aria-label="<?php echo (int)$bitePeak['stars']; ?>つ星"><?php
                    $peakStars = (int)($bitePeak['stars'] ?? 0);
                    echo str_repeat('★', $peakStars) . str_repeat('☆', max(0, 5 - $peakStars));
                ?></span>
                <span style="font-weight:700;color:#0f766e;"><?php echo number_format((float)$bitePeak['score'], 1); ?></span>
            </p>
            <p class="bite-score-note"><?php echo htmlspecialchars((string)($biteScore['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> ★1＝1.0</p>
            <div class="bite-hour-strip" aria-label="時間帯ごとの釣時スコア">
                <?php foreach (($biteScore['hourly'] ?? []) as $row): ?>
                    <?php
                    $cellStars = (int)($row['stars'] ?? 0);
                    $isPeakBand = isset($peakHourSet[(int)$row['hour']]);
                    $lvl = max(0, min(5, $cellStars));
                    ?>
                    <div class="bite-hour-cell lvl-<?php echo $lvl; ?><?php echo $isPeakBand ? ' is-peak' : ''; ?>" title="<?php
                        echo htmlspecialchars(
                            sprintf(
                                '%s %.1f %s',
                                $row['time'],
                                (float)$row['score'],
                                str_repeat('★', $cellStars) . str_repeat('☆', max(0, 5 - $cellStars))
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>">
                        <?php echo sprintf('%02d', (int)$row['hour']); ?>
                        <span class="bite-cell-stars"><?php echo $cellStars > 0 ? str_repeat('★', $cellStars) : '·'; ?></span>
                        <strong><?php echo number_format((float)$row['score'], 1); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php echo PageEmbed::renderScriptTag($shioyomi); ?>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/webmcp.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/pwa.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
    const tide_data = <?php echo json_encode($tide_data_array['tide'], JSON_THROW_ON_ERROR); ?>;
    const wind_speed = <?php echo json_encode($weather_data['wind_speed'] ?? [], JSON_THROW_ON_ERROR); ?>;
    const bite_hourly = <?php echo json_encode($shioyomi['bite_score']['hourly'] ?? [], JSON_THROW_ON_ERROR); ?>;
    const bite_peak_hours = <?php echo json_encode($shioyomi['bite_score']['peak_hours'] ?? [], JSON_THROW_ON_ERROR); ?>;
    
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

    const biteByHour = {};
    bite_hourly.forEach(function (row) {
        biteByHour[row.hour] = row;
    });
    const peakHourSet = {};
    (bite_peak_hours || []).forEach(function (h) { peakHourSet[h] = true; });

    const mapped_bite_score = tide_time.map(function (time, index) {
        if (index % 3 !== 0) return null;
        let hour = index / 3;
        if (hour >= 24) hour = 23;
        const row = biteByHour[hour];
        return row ? row.score : null;
    });
    const bitePointRadius = tide_time.map(function (time, index) {
        if (index % 3 !== 0) return 0;
        let hour = index / 3;
        if (hour >= 24) hour = 23;
        const row = biteByHour[hour];
        if (!row) return 0;
        return 3 + (Number(row.stars) || 0) * 1.4;
    });
    const bitePointColors = tide_time.map(function (time, index) {
        if (index % 3 !== 0) return 'rgba(0,0,0,0)';
        let hour = index / 3;
        if (hour >= 24) hour = 23;
        const row = biteByHour[hour];
        if (!row) return '#d97706';
        const s = Number(row.stars) || 0;
        if (s >= 4) return '#b45309';
        if (s >= 3) return '#d97706';
        if (s >= 2) return '#f59e0b';
        return '#fbbf24';
    });

    Chart.defaults.global.defaultFontColor = '#333';
    Chart.defaults.global.defaultFontFamily = "'Inter', 'Noto Sans JP', sans-serif";
    
    const ctx = document.getElementById('chart').getContext('2d');

    const starLabel = function (stars) {
        const n = Math.max(0, Math.min(5, Number(stars) || 0));
        return '★'.repeat(n) + '☆'.repeat(5 - n);
    };

    // Chart.js 2.7: ピーク帯ハイライト
    Chart.pluginService.register({
        beforeDatasetsDraw: function (chart) {
            if (!chart.canvas || chart.canvas.id !== 'chart') return;
            const xAxis = chart.scales['x-axis-0'];
            const yAxis = chart.scales['y-axis-1'];
            if (!xAxis || !yAxis) return;
            const labels = chart.data.labels || [];
            const ctx2 = chart.chart.ctx;
            Object.keys(peakHourSet).forEach(function (hourKey) {
                const hour = Number(hourKey);
                const startIdx = hour * 3;
                const endIdx = Math.min(startIdx + 3, labels.length - 1);
                if (startIdx >= labels.length) return;
                const x1 = xAxis.getPixelForTick(startIdx);
                const x2 = xAxis.getPixelForTick(endIdx);
                ctx2.save();
                ctx2.fillStyle = 'rgba(251, 191, 36, 0.18)';
                ctx2.fillRect(x1, yAxis.top, Math.max(2, x2 - x1), yAxis.bottom - yAxis.top);
                ctx2.restore();
            });
        }
    });

    let chartMode = 'standard';

    const applyChartMode = function (chart, mode, shouldUpdate) {
        chartMode = mode;
        const tide = chart.data.datasets[0];
        const wind = chart.data.datasets[1];
        const bite = chart.data.datasets[2];
        const narrow = window.innerWidth < 768;
        if (mode === 'bite') {
            tide.borderWidth = narrow ? 1.5 : 3;
            tide.backgroundColor = 'rgba(169, 227, 255, 0.18)';
            wind.borderWidth = 1.5;
            wind.borderColor = 'rgba(46, 204, 113, 0.45)';
            bite.borderWidth = narrow ? 3 : 4.5;
            bite.borderDash = [];
            bite.borderColor = '#b45309';
            chart.options.scales.yAxes[1].display = false;
            chart.options.scales.yAxes[2].display = true;
            chart.options.scales.yAxes[2].scaleLabel.display = true;
        } else {
            tide.borderWidth = narrow ? 2 : 7;
            tide.backgroundColor = 'rgba(169, 227, 255, 0.5)';
            wind.borderWidth = narrow ? 2 : 3;
            wind.borderColor = '#2ecc71';
            bite.borderWidth = narrow ? 2.5 : 3.5;
            bite.borderDash = [2, 3];
            bite.borderColor = '#d97706';
            chart.options.scales.yAxes[1].display = true;
            chart.options.scales.yAxes[2].display = false;
            chart.options.scales.yAxes[2].scaleLabel.display = false;
        }
        document.querySelectorAll('.chart-mode-btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-chart-mode') === mode);
        });
        if (shouldUpdate !== false) {
            chart.update();
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
                    lineTension: 0.5,
                    borderWidth: 7,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBorderWidth: 10,
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
                },
                {
                    label: '釣時スコア ★',
                    yAxisID: 'y-axis-3',
                    data: mapped_bite_score,
                    fill: false,
                    spanGaps: true,
                    borderColor: '#d97706',
                    backgroundColor: '#d97706',
                    borderDash: [2, 3],
                    lineTension: 0.25,
                    borderWidth: 3.5,
                    pointRadius: bitePointRadius,
                    pointHoverRadius: 8,
                    pointBackgroundColor: bitePointColors,
                    pointBorderColor: '#92400e',
                    pointBorderWidth: 1,
                }
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onResize: function (chart) {
                applyChartMode(chart, chartMode, false);
            },
            tooltips: {
                mode: 'index',
                intersect: false,
                titleFontSize: 13,
                bodyFontSize: 12,
                callbacks: {
                    title: function (items) {
                        if (!items || !items.length) return '';
                        const idx = items[0].index;
                        let hour = Math.floor(idx / 3);
                        if (hour >= 24) hour = 23;
                        const row = biteByHour[hour];
                        const base = items[0].xLabel || '';
                        if (!row) return base;
                        return base + '  /  釣時 ' + starLabel(row.stars) + ' (' + Number(row.score).toFixed(1) + ')';
                    },
                    label: function (tooltipItem, data) {
                        const ds = data.datasets[tooltipItem.datasetIndex];
                        const val = tooltipItem.yLabel;
                        if (ds.yAxisID === 'y-axis-3') {
                            let hour = Math.floor(tooltipItem.index / 3);
                            if (hour >= 24) hour = 23;
                            const row = biteByHour[hour];
                            return '釣時スコア: ' + Number(val).toFixed(1) + ' ' + starLabel(row ? row.stars : 0);
                        }
                        return ds.label + ': ' + val;
                    }
                }
            },
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    boxWidth: 10,
                    fontSize: 11,
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        autoSkip: false,
                        maxRotation: 0,
                        minRotation: 0,
                        fontSize: 11,
                        fontStyle: 'bold',
                        callback: function (value, index) {
                            // 3時間ごと + 24:00
                            if (index % 9 === 0 || index === tide_time.length - 1) {
                                return value;
                            }
                            return '';
                        }
                    },
                    gridLines: {
                        color: 'rgba(226, 232, 240, 0.7)'
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
                    },
                    {
                        id: 'y-axis-3',
                        type: 'linear',
                        position: 'right',
                        display: false,
                        ticks: {
                            min: 0,
                            max: 5,
                            stepSize: 1,
                            beginAtZero: true,
                            callback: function (v) {
                                return v + '★';
                            }
                        },
                        scaleLabel: {
                            display: false,
                            labelString: '釣時スコア'
                        },
                        gridLines: {
                            drawOnChartArea: false,
                        }
                    }
                ]
            }
        }
    });

    applyChartMode(myChart, 'standard');

    document.querySelectorAll('.chart-mode-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyChartMode(myChart, btn.getAttribute('data-chart-mode') || 'standard', true);
        });
    });
</script>
</body>
</html>
