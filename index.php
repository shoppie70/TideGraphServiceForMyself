<?php

require_once __DIR__ . "/vendor/autoload.php";

use App\UseCases\Date\GetRequestDateAction;
use Carbon\Carbon;
use App\Services\TideGraphService;
use App\UseCases\Date\ValidateRequestDateAction;

Carbon::setLocale('ja');

try {
    $request = (new ValidateRequestDateAction())($_REQUEST);
    $date = (new GetRequestDateAction())($_REQUEST);
    $tide_data = (new TideGraphService($request['year'], $request['month'], $request['date']));
    $tide_data_array = $tide_data->get_tide_data_array();

    if ($tide_data_array['status'] !== 200) {
        throw new \RuntimeException('データの取得に失敗しました。');
    }

} catch (Exception $e) {
    echo $e->getMessage();
    exit;
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name=”viewport” content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
    <!--    <meta name="viewport" content="width=device-width, initial-scale=1.0">-->
    <script src="//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.js"></script>
    <style>
        body {
            -webkit-text-size-adjust: 100%;
        }

        .content-wrap {
            position: relative;
        }

        .chart-container {
            position: relative;
            width: 100vw;
            height: 100vh;
        }

        .tide-description {
            position: absolute;
            right: 1rem;
            bottom: 5rem;
            background-color: white;
            opacity: 0.7;
            padding: 1rem;
            border: 1px solid #eee;
            max-width: 250px;
            width: 100%;
            z-index: 2;
        }

        .dl {
            display: flex;
        }

        .dt {
            font-weight: bold;
            width: 30%;
        }

        .dd {
            text-align: right;
            width: 70%;
            margin: 0;
        }

        .date {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            margin-top: 0;
            margin-bottom: 1.5rem;
        }

        @media(max-width: 767px) {
            .chart-container {
                position: relative;
                width: 100vw;
                height: 60vh;
            }
        }
    </style>
</head>

<body>
<div class="content-wrap">
    <div class="tide-description">
        <h1 class="date">
            <?php echo $date->isoFormat('YYYY年MM月DD日(ddd)'); ?>
        </h1>
        <dl class="dl">
            <dt class="dt">
                日の出
            </dt>
            <dd class="dd">
                <?php echo $tide_data_array['sun']['rise']; ?>
            </dd>
        </dl>
        <dl class="dl">
            <dt class="dt">
                潮
            </dt>
            <dd class="dd">
                <?php echo $tide_data_array['moon']['title']; ?>
            </dd>
        </dl>
        <dl class="dl">
            <dt class="dt">
                満潮
            </dt>
            <dd class="dd">
                <?php foreach ($tide_data_array['flood'] as $flood): ?>
                    <?php echo $flood['time']; ?>,
                <?php endforeach; ?>
            </dd>
        </dl>
        <dl class="dl">
            <dt class="dt">
                干潮
            </dt>
            <dd class="dd">
                <?php foreach ($tide_data_array['edd'] as $edd): ?>
                    <?php echo $edd['time']; ?>,
                <?php endforeach; ?>
            </dd>
        </dl>
    </div>
    <div class="chart-container">
        <canvas class="canvas" id="chart"></canvas>
    </div>
</div>
<script>
    const tide_data = <?php echo json_encode($tide_data_array['tide'], JSON_THROW_ON_ERROR); ?>;
    const ctx = document.getElementById("chart");

    const get_dataset = function (parent_data, type) {
        let array = [];
        for (let i = 0; i < parent_data.length; i++) {
            array.push(parent_data[i][type])
        }

        return array;
    }

    const tide_time = get_dataset(tide_data, 'time');
    const tide_cm = get_dataset(tide_data, 'cm');

    const myLineChart = new Chart(ctx, {
        type: "line",
        data: {
            "labels": tide_time,
            "datasets": [{
                "label": "潮位",
                "data": tide_cm,
                "fill": true,
                "borderColor": "#35b0eb",
                "backgroundColor":"#a9e3ff",
                "lineTension": 0.5,
                "borderWidth": 7,
                "pointHoverBorderWidth": 10,
            }]
        },
        options: {
            title: {},
            legend: {},
            scales: {},
            maintainAspectRatio: false //これを追加
        },
    });
</script>
</body>
</html>
