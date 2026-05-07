<?php

include_once __DIR__ . "/vendor/autoload.php";
include_once __DIR__ . '/header.php';

use App\Services\CalendarService;
use App\Services\WeatherService;
use Carbon\Carbon;

Carbon::setLocale('ja');

$place = $_GET['place'] ?? null;
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

if (!$place) {
    header("Location: /");
    exit;
}

$place_array = explode('&', $place);
if (count($place_array) !== 2) {
    echo "無効な場所です。";
    exit;
}

$prefecture = $place_array[0];
$code = $place_array[1];

$calendar_service = new CalendarService((int)$year, (int)$month, $prefecture, $code);
$data = $calendar_service->get_monthly_tide_data();

if ($data['status'] !== 200) {
    echo "データの取得に失敗しました。";
    exit;
}

$port = $data['port'];
$chart = $data['chart'];

$weather_service = new WeatherService($data['lat'], $data['lng'], '');
$weather_response = $weather_service->get_monthly_weather_data((int)$year, (int)$month);
$monthly_weather = $weather_response['status'] === 200 ? $weather_response['data'] : [];

$firstDay = new Carbon("{$year}-{$month}-01");
$lastDay = clone $firstDay;
$lastDay->endOfMonth();

$prevMonth = clone $firstDay;
$prevMonth->subMonth();
$nextMonth = clone $firstDay;
$nextMonth->addMonth();

$place_param = urlencode($place);
$prev_url = "?place={$place_param}&year={$prevMonth->year}&month={$prevMonth->month}";
$next_url = "?place={$place_param}&year={$nextMonth->year}&month={$nextMonth->month}";
$map_url = "https://www.google.com/maps/search/?api=1&query={$data['lat']},{$data['lng']}";

function getMoonColor($title) {
    if (strpos($title, '大潮') !== false) return '#e74c3c';
    if (strpos($title, '中潮') !== false) return '#3498db';
    if (strpos($title, '小潮') !== false) return '#2ecc71';
    if (strpos($title, '長潮') !== false) return '#9b59b6';
    if (strpos($title, '若潮') !== false) return '#f1c40f';
    return '#7f8c8d';
}

?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+JP:wght@400;500;700&display=swap');
    
    body {
        margin: 0;
        font-family: 'Inter', 'Noto Sans JP', sans-serif;
        background-color: #f7f9fc;
        color: #333;
    }

    /* トップヘッダー */
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

    .current-date {
        font-size: 1.2rem;
        font-weight: 800;
        min-width: 120px;
        text-align: center;
        color: #333;
    }

    /* カレンダーエリア */
    .calendar-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .location-title {
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0 0 1.5rem 0;
        color: #2c3e50;
        text-align: center;
    }

    .calendar-wrapper {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        overflow: hidden;
        border: 1px solid #edf2f7;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: #edf2f7;
        gap: 1px;
    }

    .calendar-day-header {
        text-align: center;
        font-weight: 700;
        padding: 0.8rem 0.5rem;
        background: #f8fafc;
        color: #4a5568;
        font-size: 0.95rem;
    }

    .calendar-cell {
        background: #fff;
        padding: 0.6rem;
        display: flex;
        flex-direction: column;
        text-decoration: none;
        color: #333;
        transition: background 0.2s;
        min-height: 110px;
        position: relative;
    }
    
    .calendar-cell:hover {
        background: #f0f7ff;
    }

    .calendar-cell.empty {
        background: #fcfcfc;
        cursor: default;
    }
    .calendar-cell.empty:hover {
        background: #fcfcfc;
    }

    .cell-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.4rem;
    }

    .day-number {
        font-size: 1.1rem;
        font-weight: 800;
        color: #2d3748;
    }

    .moon-title {
        font-size: 0.8rem;
        font-weight: 700;
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .weather-info {
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.3rem;
        margin-bottom: 0.4rem;
    }
    .temp-info {
        font-size: 0.75rem;
        font-weight: 600;
    }

    .sun-info {
        font-size: 0.7rem;
        color: #718096;
        margin-top: auto;
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        font-family: monospace;
    }

    /* スマホ表示対応 */
    @media (max-width: 768px) {
        .top-header {
            flex-direction: column;
            height: auto;
            padding: 0.5rem;
            gap: 0.5rem;
        }
        .header-center {
            width: 100%;
            justify-content: space-between;
        }
        .calendar-container {
            padding: 0 0.5rem;
            margin: 1rem auto;
        }
        .calendar-cell {
            padding: 0.3rem;
            min-height: 90px;
        }
        .day-number {
            font-size: 0.9rem;
        }
        .moon-title {
            font-size: 0.65rem;
            padding: 0.1rem 0.2rem;
        }
        .weather-info {
            font-size: 0.9rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 0;
        }
        .temp-info {
            font-size: 0.65rem;
        }
        .sun-info {
            font-size: 0.6rem;
            letter-spacing: -0.5px;
        }
    }
</style>

<header class="top-header">
    <div class="header-left">
        <a href="/" class="brand">シオヨミ <span style="font-size: 0.75rem; color: #64748b; margin-left: 0.5rem; font-weight: normal;">自分専用の潮汐・天気・風速確認ツール</span></a>
    </div>
    
    <div class="header-center">
        <a href="<?php echo $prev_url; ?>" class="nav-btn">&larr; 前月</a>
        <div class="current-date"><?php echo $firstDay->format('Y年n月'); ?></div>
        <a href="<?php echo $next_url; ?>" class="nav-btn">翌月 &rarr;</a>
    </div>

    <div class="header-right">
        <a href="<?php echo $map_url; ?>" target="_blank" class="nav-btn" title="Google Mapで位置を確認">🗺️ Map</a>
    </div>
</header>

<div class="calendar-container">
    <h1 class="location-title">📍 <?php echo htmlspecialchars($port); ?></h1>

    <div class="calendar-wrapper">
        <div class="calendar-grid">
            <div class="calendar-day-header" style="color:#e53e3e">日</div>
            <div class="calendar-day-header">月</div>
            <div class="calendar-day-header">火</div>
            <div class="calendar-day-header">水</div>
            <div class="calendar-day-header">木</div>
            <div class="calendar-day-header">金</div>
            <div class="calendar-day-header" style="color:#3182ce">土</div>
            
            <?php
            $startDayOfWeek = $firstDay->dayOfWeek; // 0 (Sun) to 6 (Sat)
            
            // Empty cells
            for ($i = 0; $i < $startDayOfWeek; $i++) {
                echo '<div class="calendar-cell empty"></div>';
            }
            
            // Days
            $currentDay = clone $firstDay;
            while ($currentDay->month == $month) {
                $dateStr = $currentDay->format('Y-m-d');
                $tideInfo = $chart[$dateStr] ?? null;
                $moonTitle = $tideInfo ? $tideInfo['moon']['title'] : '-';
                $moonColor = getMoonColor($moonTitle);
                
                $sunrise = $tideInfo['sun']['rise'] ?? '-';
                $sunset = $tideInfo['sun']['set'] ?? '-';
                
                $weather = $monthly_weather[$dateStr] ?? null;
                
                $link = "chart.php?place={$place_param}&date={$dateStr}";
                
                echo "<a href='{$link}' class='calendar-cell'>";
                echo "<div class='cell-top'>";
                echo "  <div class='day-number'>{$currentDay->day}</div>";
                if ($tideInfo) {
                    echo "  <div class='moon-title' style='color: {$moonColor}; border-color: {$moonColor}'>{$moonTitle}</div>";
                }
                echo "</div>";

                if ($weather) {
                    echo "<div class='weather-info'>";
                    echo "  <span>{$weather['icon']}</span>";
                    echo "  <span class='temp-info'>";
                    echo "      <span style='color: #e53e3e'>{$weather['temp_max']}°</span>/";
                    echo "      <span style='color: #3182ce'>{$weather['temp_min']}°</span>";
                    echo "  </span>";
                    echo "</div>";
                }

                if ($tideInfo) {
                    echo "<div class='sun-info'>";
                    echo "  <span>🌅 {$sunrise}</span>";
                    echo "  <span>🌇 {$sunset}</span>";
                    echo "</div>";
                }

                echo "</a>";
                
                $currentDay->addDay();
            }

            // Fill remaining cells to complete the grid
            $endDayOfWeek = $lastDay->dayOfWeek;
            for ($i = $endDayOfWeek + 1; $i <= 6; $i++) {
                echo '<div class="calendar-cell empty"></div>';
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>
