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

    .tide-info-panel {
        position: static;
        flex: 0 0 260px;
        width: 260px;
        max-width: 100%;
        background: #fff;
        border-left: 1px solid #e5e7eb;
        border-radius: 0;
        padding: 1rem;
        box-shadow: none;
        max-height: calc(100svh - 60px);
        overflow: auto;
        box-sizing: border-box;
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
        margin-top: 0.55rem;
        padding-top: 0.55rem;
        border-top: 1px solid #eee;
        width: 100%;
    }
    .bite-score-title {
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        margin: 0 0 0.2rem;
    }
    .bite-score-peak {
        font-size: 0.78rem;
        font-weight: 600;
        color: #475569;
        margin: 0 0 0.2rem;
    }
    .bite-score-note {
        font-size: 0.65rem;
        line-height: 1.3;
        color: #94a3b8;
        margin: 0;
    }
    .bite-hour-strip {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 2px;
        margin-top: 0.4rem;
    }
    .bite-hour-cell {
        text-align: center;
        font-size: 0.55rem;
        line-height: 1.15;
        color: #64748b;
        border-radius: 3px;
        padding: 0.15rem 0.02rem;
        border: 1px solid transparent;
        background: #f8fafc;
    }
    .bite-hour-cell strong {
        display: block;
        font-size: 0.58rem;
        font-weight: 600;
        color: #64748b;
    }
    .bite-hour-cell .bite-cell-stars {
        display: block;
        color: #a8a29e;
        font-size: 0.5rem;
        letter-spacing: -0.06em;
        min-height: 0.65rem;
    }
    .bite-hour-cell.is-peak {
        border-color: rgba(214, 181, 110, 0.55);
        background: #faf6ef;
    }
    .bite-hour-cell.is-peak strong {
        color: #78716c;
    }
    .bite-hour-cell.lvl-0,
    .bite-hour-cell.lvl-1 { background: #f8fafc; }
    .bite-hour-cell.lvl-2 { background: #f1f5f9; }
    .bite-hour-cell.lvl-3 { background: #f5f0e8; }
    .bite-hour-cell.lvl-4,
    .bite-hour-cell.lvl-5 { background: #f3ebe0; }

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
            padding: 0.75rem 1rem 1.25rem;
        }
        .info-dl {
            border-bottom: 1px solid #eee;
            margin-bottom: 0.35rem;
            padding-bottom: 0.3rem;
        }
        .bite-hour-strip {
            grid-template-columns: repeat(6, minmax(0, 1fr));
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
                ピーク <?php echo htmlspecialchars((string)$bitePeak['time'], ENT_QUOTES, 'UTF-8'); ?>
                · <?php echo number_format((float)$bitePeak['score'], 1); ?>
                （★<?php echo (int)($bitePeak['stars'] ?? 0); ?>）
            </p>
            <p class="bite-score-note">潮位グラフの薄帯がピーク帯。詳細は下の時間帯一覧。★1＝1.0</p>
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
                        <span class="bite-cell-stars"><?php echo $cellStars > 0 ? str_repeat('★', min(3, $cellStars)) : '·'; ?></span>
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

    Chart.defaults.global.defaultFontColor = '#333';
    Chart.defaults.global.defaultFontFamily = "'Inter', 'Noto Sans JP', sans-serif";
    
    const ctx = document.getElementById('chart').getContext('2d');

    const starLabel = function (stars) {
        const n = Math.max(0, Math.min(5, Number(stars) || 0));
        return '★'.repeat(n) + '☆'.repeat(5 - n);
    };

    // Chart.js 2.7: ピーク帯のみ（薄い縦ゾーン）。スコア線は描かず潮位を主役に保つ
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
                ctx2.fillStyle = 'rgba(214, 181, 110, 0.12)';
                ctx2.fillRect(x1, yAxis.top, Math.max(2, x2 - x1), yAxis.bottom - yAxis.top);
                ctx2.restore();
            });
        }
    });

    const adjustStyles = function (chart, width) {
        if (width < 768) {
            chart.data.datasets[0].borderWidth = 2;
            chart.data.datasets[0].pointHoverBorderWidth = 3;
            if (chart.data.datasets[1]) chart.data.datasets[1].borderWidth = 2;
        } else {
            chart.data.datasets[0].borderWidth = 7;
            chart.data.datasets[0].pointHoverBorderWidth = 10;
            if (chart.data.datasets[1]) chart.data.datasets[1].borderWidth = 3;
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
                        if (!items || !items.length) return '';
                        const idx = items[0].index;
                        let hour = Math.floor(idx / 3);
                        if (hour >= 24) hour = 23;
                        const row = biteByHour[hour];
                        if (!row) return '';
                        return '釣時 ' + Number(row.score).toFixed(1) + ' ' + starLabel(row.stars);
                    }
                }
            },
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    usePointStyle: true,
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
