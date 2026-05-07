<?php
include_once __DIR__ . "/vendor/autoload.php";
include_once __DIR__ . '/header.php';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Noto+Sans+JP:wght@400;500;700&display=swap');
    
    body {
        margin: 0;
        font-family: 'Inter', 'Noto Sans JP', sans-serif;
        background-color: #f7f9fc;
        color: #333;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .main-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid #edf2f7;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        padding: 3rem;
        width: 100%;
        max-width: 500px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .main-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
    }
    .title {
        font-size: 2.5rem;
        font-weight: 800;
        text-align: center;
        margin-bottom: 0.5rem;
        color: #2c3e50;
        letter-spacing: -0.5px;
    }
    .main-description {
        text-align: center;
        font-size: 0.95rem;
        color: #64748b;
        margin-bottom: 2.5rem;
        line-height: 1.5;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #555;
    }
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #333;
        font-size: 1rem;
        transition: all 0.3s ease;
        outline: none;
    }
    .form-control:focus {
        border-color: #0072ff;
        box-shadow: 0 0 0 3px rgba(0, 114, 255, 0.2);
        background: #ffffff;
    }
    .form-control option {
        background: #fff;
        color: #333;
    }
    .submit-btn {
        width: 100%;
        background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
        color: white;
        border: none;
        padding: 1rem;
        border-radius: 12px;
        font-size: 1.2rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 1rem;
        box-shadow: 0 4px 15px rgba(0, 114, 255, 0.3);
    }
    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 114, 255, 0.4);
    }
    .footer-text {
        text-align: center;
        margin-top: 3rem;
        font-size: 0.85rem;
        color: #a0aec0;
    }
</style>

<div class="main-card">
    <h1 class="title">シオヨミ</h1>
    <p class="main-description">選定したスポットの潮汐・天気・風速を素早く確認するための、自分専用の潮見表ツール。</p>
    <form action="chart.php" method="GET">
        <div class="form-group">
            <label class="form-label" for="place">場所</label>
            <select class="form-control" name="place" id="place">
                <?php foreach (PLACES as $place): ?>
                    <option value="<?php echo $place['prefecture'] . '&' . $place['code']; ?>">
                        <?php echo htmlspecialchars($place['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="date">日付</label>
            <input class="form-control" id="date" type="date" name="date" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <button type="submit" class="submit-btn">
            潮見表を見る 🌊
        </button>
    </form>
    <div class="footer-text">
        Copyright &copy; <?php echo date('Y') . ' ' . MASTER_NAME; ?>
    </div>
</div>
</body>
</html>
