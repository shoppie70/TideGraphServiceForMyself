<?php

include_once __DIR__ . "/vendor/autoload.php";

use App\UseCases\Date\GetRequestDateAction;
use App\Requests\TideGraphRequest;
use Carbon\Carbon;
use App\Services\TideGraphService;

Carbon::setLocale('ja');

try {
    $request         = (new TideGraphRequest($_REQUEST))();
    $date            = (new GetRequestDateAction())($request);
    $tide_data       = (new TideGraphService($request['year'], $request['month'], $request['date'], $request['prefecture'], $request['code']));
    $tide_data_array = $tide_data->get_tide_data_array();

    if ($tide_data_array['status'] !== 200) {
        throw new \RuntimeException('データの取得に失敗しました。');
    }
} catch (Exception $e) {
    echo $e->getMessage();
    exit;
}

include_once __DIR__ . '/header.php';

?>
<div class="content-wrap">
    <div class="tide-description">
        <h1 class="date mb-4">
            <?php echo $date->isoFormat('YYYY年MM月DD日(ddd)'); ?>
        </h1>
        <h2 class="text-center mb-4">
            <?php echo $tide_data_array['port']; ?>
        </h2>
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
<script src="assets/js/app.js"></script>
<script>
    const tide_data = <?php echo json_encode($tide_data_array['tide'], JSON_THROW_ON_ERROR); ?>;

    const tide_time = get_dataset(tide_data, 'time');
    const tide_cm = get_dataset(tide_data, 'cm');
    draw_chart(tide_time, tide_cm);
</script>
</body>
</html>
