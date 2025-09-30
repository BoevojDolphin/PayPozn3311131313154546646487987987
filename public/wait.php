<?php
// В начале каждого public/*.php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

// Подключаем автозагрузчик Composer из родительской папки
require __DIR__ . '/../vendor/autoload.php';

use App\Bootstrap;
use App\Storage\TempStorage;

Bootstrap::init();

if (empty($_GET['t'])) {
    header('Location: /');
    exit;
}

$token = preg_replace('/[^a-f0-9]/', '', $_GET['t']);
$data = TempStorage::read($token);
$orderId = $data['order_id'] ?? '—';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Генерация счёта №<?= htmlspecialchars($orderId) ?></title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 40px; }
        .progress { width: 300px; height: 20px; background: #eee; margin: 20px auto; border-radius: 10px; }
        .bar { height: 100%; background: #4CAF50; border-radius: 10px; transition: width 0.3s; width: 0; }
        .message { margin-top: 20px; color: #333; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>🧾 Генерация счёта</h1>
    <p>Заказ № <strong><?= htmlspecialchars($orderId) ?></strong></p>
    
    <?php if (!$data): ?>
        <div class="error">Данные не найдены или время ожидания истекло</div>
    <?php else: ?>
        <div class="progress"><div class="bar" id="progress-bar"></div></div>
        <div class="message" id="message">Подготавливаем ваш счёт...</div>
    <?php endif; ?>

    <script>
    <?php if ($data): ?>
    (function() {
        const token = '<?= $token ?>';
        let attempts = 0;
        const maxAttempts = 60;

        function checkStatus() {
            fetch(`/poll.php?t=${token}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'ready') {
                        window.location.href = data.url;
                    } else if (data.status === 'processing') {
                        attempts++;
                        const progress = Math.min(90, (attempts / maxAttempts) * 100);
                        document.getElementById('progress-bar').style.width = progress + '%';
                        
                        if (attempts < maxAttempts) {
                            setTimeout(checkStatus, 2000);
                        } else {
                            document.getElementById('message').textContent = 'Время ожидания истекло';
                        }
                    } else {
                        document.getElementById('message').innerHTML = 
                            '<span class="error">Ошибка: ' + (data.message || 'Неизвестная ошибка') + '</span>';
                    }
                })
                .catch(e => {
                    document.getElementById('message').innerHTML = 
                        '<span class="error">Ошибка сети: ' + e.message + '</span>';
                });
        }

        checkStatus();
    })();
    <?php endif; ?>
    </script>
</body>
</html>
