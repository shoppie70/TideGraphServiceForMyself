<?php

require_once __DIR__ . "/vendor/autoload.php";

use App\UseCases\Date\GetRequestDateAction;
use App\Requests\TideGraphRequest;
use App\Services\PlaceService;
use Carbon\Carbon;
use App\Services\TideGraphService;

Carbon::setLocale('ja');


try {
    $request = (new TideGraphRequest($_REQUEST))();
    $date = (new GetRequestDateAction())($request);
    $tide_data = (new TideGraphService($request['year'], $request['month'], $request['date'], $request['prefecture'], $request['code']));
    $tide_data_array = $tide_data->get_tide_data_array();
    $place_service = new PlaceService();
    $place_data = $place_service->get_data($request['prefecture'], $request['code']);

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
    <link rel="stylesheet" href="assets/css/app.css">
</head>

<body>
    <!-- ここからモーダルエリアです。 -->
    <div class="modal micromodal-slide" id="modal-1" aria-hidden="true">
        <div class="modal__overlay" tabindex="-1" data-micromodal-close>
            <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
                <header class="modal__header">
                    <h2 class="modal__title" id="modal-1-title">
                        場所を変更する
                    </h2>
                    <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                </header>
                <main class="modal__content" id="modal-1-content">
                    <ul class="place-list">
                        <?php foreach (ALL_PLACES as $place) : ?>
                            <li class="place-list-item">
                                <a href="chart.php?place=<?php echo $place['prefecture_code']; ?>%26<?php echo $place['port_code']; ?>&date=2023-01-15" class="place-list-item__link">
                                    <?php echo $place['prefecture_name'] . ' ' . $place['port_name']; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </main>
                <footer class="modal__footer">
                    <button class="modal__btn modal__btn-primary" data-micromodal-close aria-label="Close this dialog window">閉じる</button>
                </footer>
            </div>
        </div>
    </div>
    <div class="content-wrap">
        <div class="tide-description">
            <h1 class="date">
                <?php echo $date->isoFormat('YYYY年MM月DD日(ddd)'); ?>
            </h1>
            <dl class="dl">
                <dt class="dt">
                    場所
                </dt>
                <dd class="dd">
                    <button class="modal__btn modal__btn-primary" data-micromodal-trigger="modal-1" role="button">
                        <?php echo $place_data['prefecture_name'] . ' ' . $place_data['port_name']; ?>
                    </button>
                </dd>
            </dl>
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
                    <?php foreach ($tide_data_array['flood'] as $flood) : ?>
                        <?php echo $flood['time']; ?>,
                    <?php endforeach; ?>
                </dd>
            </dl>
            <dl class="dl">
                <dt class="dt">
                    干潮
                </dt>
                <dd class="dd">
                    <?php foreach ($tide_data_array['edd'] as $edd) : ?>
                        <?php echo $edd['time']; ?>,
                    <?php endforeach; ?>
                </dd>
            </dl>
        </div>
        <div class="chart-container">
            <canvas class="canvas" id="chart"></canvas>
        </div>
    </div>
    <script src="//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.js"></script>
    <script src="//cdn.jsdelivr.net/npm/micromodal/dist/micromodal.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        const tide_data = <?php echo json_encode($tide_data_array['tide'], JSON_THROW_ON_ERROR); ?>;
        const tide_time = get_dataset(tide_data, 'time');
        const tide_cm = get_dataset(tide_data, 'cm');
        draw_chart(tide_time, tide_cm);

        MicroModal.init({
            awaitCloseAnimation: true,
            awaitOpenAnimation: true,
            disableScroll: true
        });
    </script>
</body>

</html>