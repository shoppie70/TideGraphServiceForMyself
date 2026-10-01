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
    </section>
</div>

<?php echo PageEmbed::renderScriptTag($shioyomi); ?>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/webmcp.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(Site::path('assets/js/pwa.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
    const tide_data = <?php echo json_encode($tide_data_array['tide'], JSON_THROW_ON_ERROR); ?>;
    const wind_speed = <?php echo json_encode($weather_data['wind_speed'] ?? [], JSON_THROW_ON_ERROR); ?>;
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

    const peakHourSet = {};
    (bite_peak_hours || []).forEach(function (h) { peakHourSet[h] = true; });

    Chart.defaults.global.defaultFontColor = '#333';
    Chart.defaults.global.defaultFontFamily = "'Inter', 'Noto Sans JP', sans-serif";
    
    const ctx = document.getElementById('chart').getContext('2d');

    // Chart.js 2.7: ピーク帯の縦ゾーン（目視できる濃さ。潮位線の下に描画）
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
                const hours = Object.keys(peakHourSet).map(Number).sort(function (a, b) { return a - b; });
                hours.forEach(function (hour) {
                    const startIdx = hour * 3;
                    const endIdx = Math.min(startIdx + 3, labels.length - 1);
                    if (startIdx >= labels.length || startIdx < 0) return;
                    const x1 = xAxis.getPixelForTick(startIdx);
                    const x2 = xAxis.getPixelForTick(endIdx);
                    if (!isFinite(x1) || !isFinite(x2)) return;
                    const left = Math.min(x1, x2);
                    const width = Math.max(4, Math.abs(x2 - x1));
                    ctx2.save();
                    ctx2.fillStyle = 'rgba(245, 180, 70, 0.28)';
                    ctx2.fillRect(left, area.top, width, area.bottom - area.top);
                    ctx2.strokeStyle = 'rgba(217, 140, 40, 0.45)';
                    ctx2.lineWidth = 1;
                    ctx2.beginPath();
                    ctx2.moveTo(left, area.top);
                    ctx2.lineTo(left, area.bottom);
                    ctx2.moveTo(left + width, area.top);
                    ctx2.lineTo(left + width, area.bottom);
                    ctx2.stroke();
                    ctx2.restore();
                });
            }
        });
    }

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
