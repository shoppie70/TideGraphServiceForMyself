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

    .tide-info-panel {
        position: absolute;
        right: 5rem;
        top: 1rem;
        width: 220px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 1rem;
        z-index: 10;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
    }
    .bite-score-stars {
        letter-spacing: 0.05em;
        color: #d97706;
    }
    .bite-score-note {
        font-size: 0.7rem;
        line-height: 1.35;
        color: #64748b;
        margin: 0;
    }
    .bite-hour-strip {
        display: flex;
        gap: 2px;
        margin-top: 0.45rem;
        overflow-x: auto;
        padding-bottom: 0.15rem;
    }
    .bite-hour-cell {
        flex: 0 0 auto;
        min-width: 1.55rem;
        text-align: center;
        font-size: 0.62rem;
        line-height: 1.2;
        color: #475569;
    }
    .bite-hour-cell strong {
        display: block;
        font-size: 0.68rem;
        color: #0f766e;
    }
    .bite-hour-cell.is-peak strong {
        color: #b45309;
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
            height: calc(100svh - 130px);
        }
        .tide-info-panel {
            width: calc(100% - 2rem);
            top: auto;
            bottom: 0.5rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.5rem;
        }
        .info-dl {
            border: none;
            flex: 1 1 45%;
            margin: 0;
            padding: 0;
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
        <div class="bite-score-block">
            <p class="bite-score-title"><?php echo htmlspecialchars((string)$biteScore['name'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="bite-score-peak">
                ピーク <?php echo htmlspecialchars((string)$bitePeak['time'], ENT_QUOTES, 'UTF-8'); ?>
                <span class="bite-score-stars"><?php
                    $peakStars = (int)($bitePeak['stars'] ?? 0);
                    echo str_repeat('★', $peakStars) . str_repeat('☆', max(0, 5 - $peakStars));
                ?></span>
                <span style="font-weight:700;color:#0f766e;"><?php echo number_format((float)$bitePeak['score'], 1); ?></span>
            </p>
            <p class="bite-score-note"><?php echo htmlspecialchars((string)($biteScore['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> ★1＝1.0</p>
            <div class="bite-hour-strip" aria-label="時間帯ごとの釣時スコア">
                <?php foreach (($biteScore['hourly'] ?? []) as $row): ?>
                    <?php
                    $isPeak = ((int)$row['hour'] === (int)$bitePeak['hour']);
                    $cellStars = (int)($row['stars'] ?? 0);
                    ?>
                    <div class="bite-hour-cell<?php echo $isPeak ? ' is-peak' : ''; ?>" title="<?php
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
                        <?php echo (int)$row['hour']; ?>
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
    const mapped_bite_score = tide_time.map(function (time, index) {
        if (index % 3 !== 0) return null;
        let hour = index / 3;
        if (hour >= 24) hour = 23;
        const row = biteByHour[hour];
        return row ? row.score : null;
    });

    Chart.defaults.global.defaultFontColor = '#333';
    Chart.defaults.global.defaultFontFamily = "'Inter', 'Noto Sans JP', sans-serif";
    
    const ctx = document.getElementById('chart').getContext('2d');

    const adjustStyles = (chart, width) => {
        if (width < 768) {
            chart.data.datasets[0].borderWidth = 2;
            chart.data.datasets[0].pointHoverBorderWidth = 3;
            if(chart.data.datasets[1]) chart.data.datasets[1].borderWidth = 2;
            if(chart.data.datasets[2]) chart.data.datasets[2].borderWidth = 2;
        } else {
            chart.data.datasets[0].borderWidth = 7;
            chart.data.datasets[0].pointHoverBorderWidth = 10;
            if(chart.data.datasets[1]) chart.data.datasets[1].borderWidth = 3;
            if(chart.data.datasets[2]) chart.data.datasets[2].borderWidth = 3;
        }
    };

    const starLabel = function (stars) {
        const n = Math.max(0, Math.min(5, Number(stars) || 0));
        return '★'.repeat(n) + '☆'.repeat(5 - n);
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
                    pointHoverBorderWidth: 10,
                },
                {
                    label: '風速 (m/s)',
                    yAxisID: 'y-axis-2',
                    data: mapped_wind_speed,
                    fill: false,
                    spanGaps: true, // nullの部分を線で結ぶ
                    borderColor: '#2ecc71',
                    backgroundColor: '#2ecc71',
                    borderDash: [5, 5],
                    lineTension: 0.3,
                    borderWidth: 3,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                },
                {
                    label: '釣時スコア (0-5)',
                    yAxisID: 'y-axis-3',
                    data: mapped_bite_score,
                    fill: false,
                    spanGaps: true,
                    borderColor: '#d97706',
                    backgroundColor: '#d97706',
                    borderDash: [2, 3],
                    lineTension: 0.25,
                    borderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                }
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onResize: (chart, size) => {
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
                        return '釣時 ' + starLabel(row.stars) + ' (' + Number(row.score).toFixed(1) + ')';
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
                            drawOnChartArea: false, // 2つ目の軸のグリッド線は消す
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
                            beginAtZero: true
                        },
                        gridLines: {
                            drawOnChartArea: false,
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
