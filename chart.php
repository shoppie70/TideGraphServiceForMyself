<?php

include_once __DIR__ . "/vendor/autoload.php";

use App\UseCases\Date\GetRequestDateAction;
use App\Requests\TideGraphRequest;
use Carbon\Carbon;
use App\Services\TideGraphService;
use App\Services\WeatherService;

Carbon::setLocale('ja');

try {
    $request         = (new TideGraphRequest($_REQUEST))();
    $date            = (new GetRequestDateAction())($request);
    $tide_data       = (new TideGraphService($request['year'], $request['month'], $request['date'], $request['prefecture'], $request['code']));
    $tide_data_array = $tide_data->get_tide_data_array();

    if ($tide_data_array['status'] !== 200) {
        throw new \RuntimeException('データの取得に失敗しました。');
    }

    $weather_service = new WeatherService($tide_data_array['lat'], $tide_data_array['lng'], $date->format('Y-m-d'));
    $weather_data = $weather_service->get_weather_data();

} catch (Exception $e) {
    echo $e->getMessage();
    exit;
}

include_once __DIR__ . '/header.php';

$current_place_val = $request['prefecture'] . '&' . $request['code'];
$place_param = urlencode($current_place_val);
$prev_date = $date->copy()->subDay()->format('Y-m-d');
$next_date = $date->copy()->addDay()->format('Y-m-d');
$prev_url = "?place={$place_param}&date={$prev_date}";
$next_url = "?place={$place_param}&date={$next_date}";
$calendar_url = "calendar.php?place={$place_param}&year={$request['year']}&month={$request['month']}";
$map_url = "https://www.google.com/maps/search/?api=1&query={$tide_data_array['lat']},{$tide_data_array['lng']}";

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
        <a href="/" class="brand">シオヨミ <span style="font-size: 0.75rem; color: #64748b; margin-left: 0.5rem; font-weight: normal;">自分専用の潮汐・天気・風速確認ツール</span></a>
    </div>
    
    <div class="header-center">
        <form action="chart.php" method="GET" style="display: flex; gap: 0.5rem; margin: 0; align-items: center;">
            <select class="form-control" name="place" onchange="this.form.submit()">
                <?php foreach (PLACES as $place): ?>
                    <?php $val = $place['prefecture'] . '&' . $place['code']; ?>
                    <option value="<?php echo $val; ?>" <?php if ($val === $current_place_val) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($place['name']); ?>
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
            <?php echo $weather_data['icon']; ?> <?php echo $weather_data['label']; ?> 
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

    <div class="tide-info-panel">
        <dl class="info-dl">
            <dt class="info-dt">潮回り</dt>
            <dd class="info-dd" style="color: #2980b9;"><?php echo htmlspecialchars($tide_data_array['moon']['title']); ?></dd>
        </dl>
        <dl class="info-dl">
            <dt class="info-dt">日の出</dt>
            <dd class="info-dd"><?php echo htmlspecialchars($tide_data_array['sun']['rise']); ?></dd>
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
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
    const tide_data = <?php echo json_encode($tide_data_array['tide'], JSON_THROW_ON_ERROR); ?>;
    const wind_speed = <?php echo json_encode($weather_data['wind_speed'] ?? [], JSON_THROW_ON_ERROR); ?>;
    
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

    Chart.defaults.global.defaultFontColor = '#333';
    Chart.defaults.global.defaultFontFamily = "'Inter', 'Noto Sans JP', sans-serif";
    
    const ctx = document.getElementById('chart').getContext('2d');

    const adjustStyles = (chart, width) => {
        if (width < 768) {
            chart.data.datasets[0].borderWidth = 2;
            chart.data.datasets[0].pointHoverBorderWidth = 3;
            if(chart.data.datasets[1]) chart.data.datasets[1].borderWidth = 2;
        } else {
            chart.data.datasets[0].borderWidth = 7;
            chart.data.datasets[0].pointHoverBorderWidth = 10;
            if(chart.data.datasets[1]) chart.data.datasets[1].borderWidth = 3;
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
