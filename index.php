<?php
include_once __DIR__ . "/vendor/autoload.php";
include_once __DIR__ . '/header.php';
?>
<div class="flex h-screen items-center justify-center bg-sky-100 p-4">
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
                    <button type="submit" class="px-8 py-2 bg-blue-200 rounded">
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