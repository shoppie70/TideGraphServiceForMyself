<?php
require_once "vendor/autoload.php";
?>
<!DOCTYPE HTML>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover"/>
    <meta name="robots" content="noindex,nofollow"/>
    <title>Tide Graph Chart for Myself</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
</head>

<body>
<div class="flex h-screen items-center justify-center bg-red-100 p-4">
    <div class="w-full max-w-lg rounded-2xl border bg-white p-4 shadow-x1 md:p-10">
        <div class="flex flex-col items-center space-y-4">
            <h1 class="text-center text-2xl font-bold text-gray-700 mb-8">
                Tide Graph Chart for Myself
            </h1>
            <form action="chart.php" method="GET">
                <dl class="flex items-center mb-4">
                    <dt class="">
                        <label for="place">
                            場所
                        </label>
                    </dt>
                    <dd class="ml-4">
                        <select class="border p-1" name="place" id="place">
                            <?php foreach (PLACES as $place): ?>
                                <option value="<?php echo $place['prefecture'] . '&' . $place['code']; ?>">
                                    <?php echo $place['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </dd>
                </dl>
                <dl class="flex items-center">
                    <dt class="">
                        <label for="date">
                            日付
                        </label>
                    </dt>
                    <dd class="ml-4">
                        <input class="border p-1" id="date" type="date" name="date">
                    </dd>
                </dl>
                <div class="text-center mt-8">
                    <button type="submit" class="px-8 py-2 bg-pink-200 rounded">
                        Access Tide Graph!
                    </button>
                </div>
            </form>
            <small class="block text-center mt-16">
                Copyright © <?php echo date('Y') . ' ' . MASTER_NAME; ?>.
            </small>
        </div>
    </div>
</div>
</body>

</html>