<?php
/**
 * install.php — Установщик базы данных Акватика
 * Запустить ОДИН РАЗ: http://localhost/aquatica/install.php
 *
 * ⚠️ НАСТРОЙТЕ ЭТИ ДВЕ СТРОКИ ПЕРЕД ЗАПУСКОМ:
 */

define('DB_HOST',      'localhost');
define('DB_ROOT_USER', 'root');
define('DB_ROOT_PASS', 'poplutin2008');
define('DB_NAME',      'aquatica');

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Ниже ничего не трогать
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$steps = [];
$ok    = true;

function step(string $msg, bool $success, string $detail = ''): void {
    global $steps;
    $steps[] = ['msg' => $msg, 'ok' => $success, 'detail' => $detail];
}

try {
    // 1. Подключаемся к MySQL под root (без выбора БД)
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8",
        DB_ROOT_USER,
        DB_ROOT_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    step('Подключение к MySQL', true, 'Версия: ' . $pdo->query('SELECT VERSION()')->fetchColumn());

    // 2. Создаём базу данных
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8 COLLATE utf8_general_ci");
    step('База данных `' . DB_NAME . '`', true, 'Создана или уже существует');

    // 3. Переключаемся на неё
    $pdo->exec("USE `" . DB_NAME . "`");

    // 4. Таблица услуг
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS services (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            title      VARCHAR(255)           NOT NULL,
            category   ENUM('courses','tours') NOT NULL DEFAULT 'courses',
            price      INT                    NOT NULL DEFAULT 0,
            descr      TEXT,
            active     TINYINT(1)             NOT NULL DEFAULT 1,
            created_at DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
    ");
    step('Таблица `services`', true);

    // 5. Таблица заказов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            client_name  VARCHAR(150) NOT NULL,
            client_email VARCHAR(150) NOT NULL,
            service      VARCHAR(255) NOT NULL,
            food         VARCHAR(100),
            options      VARCHAR(255),
            doc_name     VARCHAR(255),
            mail_sent    TINYINT(1)   NOT NULL DEFAULT 0,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
    ");
    step('Таблица `orders`', true);

    // 6. Таблица отзывов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            author     VARCHAR(100) NOT NULL,
            body       TEXT         NOT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
    ");
    step('Таблица `reviews`', true);

    // 7. Таблица пользователей
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            email      VARCHAR(150) NOT NULL UNIQUE,
            pass_hash  VARCHAR(255) NOT NULL,
            name       VARCHAR(100) NOT NULL,
            role       ENUM('user','admin') NOT NULL DEFAULT 'user',
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
    ");
    step('Таблица `users`', true);

    // 8. Демо-данные
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    if ($cnt === 0) {
        $pdo->exec("
            INSERT INTO services (title, category, price, descr) VALUES
            ('Peak Performance Buoyancy (Курс плавучести)', 'courses', 12000, 'Курс идеальной плавучести для дайверов.'),
            ('Deep Diver Course (Глубоководный курс)',       'courses', 16500, 'Погружения до 40 метров.'),
            ('Night Diver (Ночные погружения)',              'courses', 14000, 'Ночные погружения с фонарями.'),
            ('Подводная фотография',                        'courses', 18000, 'Научитесь снимать под водой.'),
            ('Погружение на Баренцевом море',               'tours',   45000, 'Дайв-тур на Север России.'),
            ('Дайв-тур в Египет (Марса-Алам)',              'tours',   68000, 'Рифы Красного моря.'),
            ('Экспедиция на Мальдивы (VIP)',                'tours',  185000, 'Сафари на яхте: манты и китовые акулы.')
        ");
        step('Демо-данные в `services`', true, '7 записей добавлено');
    } else {
        step('Демо-данные в `services`', true, 'Уже есть ' . $cnt . ' записей — пропускаем');
    }

    // 9. Создаём config.php — подключение root напрямую (без отдельного пользователя)
    $cfg = "<?php\n"
        . "// Автогенерация install.php — " . date('d.m.Y H:i') . "\n"
        . "define('DB_HOST', '" . DB_HOST . "');\n"
        . "define('DB_NAME', '" . DB_NAME . "');\n"
        . "define('DB_USER', '" . DB_ROOT_USER . "');\n"
        . "define('DB_PASS', '" . addslashes(DB_ROOT_PASS) . "');\n";
    file_put_contents(__DIR__ . '/config.php', $cfg);
    step('Файл config.php создан', true, 'Подключение через пользователя root');

} catch (PDOException $e) {
    step('Ошибка MySQL', false, $e->getMessage());
    $ok = false;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Установщик Акватика</title>
<style>
  body{font-family:Arial,sans-serif;background:#f0f4f8;margin:0;padding:40px;}
  .box{max-width:680px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 4px 28px rgba(0,0,0,.12);overflow:hidden;}
  .hdr{background:linear-gradient(135deg,#003366,#0077cc);color:#fff;padding:30px 36px;}
  .hdr h1{margin:0;font-size:26px;}
  .hdr p{margin:8px 0 0;opacity:.75;font-size:14px;}
  .body{padding:28px 36px;}
  .step{display:flex;align-items:flex-start;gap:14px;padding:11px 0;border-bottom:1px solid #f0f4f8;}
  .step:last-child{border:none;}
  .ico{font-size:20px;margin-top:1px;flex-shrink:0;}
  .step-text strong{display:block;font-size:15px;color:#1a1a1a;}
  .step-text small{color:#888;font-size:12px;}
  .done{background:#e8f5e9;border-radius:10px;padding:22px;margin-top:20px;text-align:center;}
  .done a{display:inline-block;margin-top:12px;padding:12px 28px;background:#0077cc;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;font-size:15px;}
  .err{background:#ffecec;border-radius:10px;padding:20px;margin-top:20px;color:#a00;font-size:14px;line-height:1.7;}
  .hint{background:#fff8e1;border-radius:8px;padding:14px 18px;margin-top:18px;font-size:13px;color:#5a4000;border-left:4px solid #ffc107;}
  .hint b{display:block;margin-bottom:4px;}
</style>
</head>
<body>
<div class="box">
  <div class="hdr">
    <h1>🌊 Установщик Акватика</h1>
    <p>Создание базы данных MySQL и таблиц</p>
  </div>
  <div class="body">
    <?php foreach ($steps as $s): ?>
    <div class="step">
      <span class="ico"><?= $s['ok'] ? '✅' : '❌' ?></span>
      <div class="step-text">
        <strong><?= htmlspecialchars($s['msg']) ?></strong>
        <?php if ($s['detail']): ?><small><?= htmlspecialchars($s['detail']) ?></small><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if ($ok): ?>
    <div class="done">
      <div style="font-size:36px;">🎉</div>
      <strong style="font-size:18px;">База данных успешно создана!</strong><br>
      <span style="color:#555;font-size:14px;">Файл config.php сохранён. Установщик можно удалить.</span>
      <br><a href="index.php">Перейти на сайт →</a>
    </div>
    <?php else: ?>
    <div class="err">
      <strong>⚠️ Установка не завершена.</strong><br>
      Смотрите ошибку выше.
    </div>
    <div class="hint">
      <b>Как исправить:</b>
      Откройте <code>install.php</code> и проверьте строку <code>DB_ROOT_PASS</code>.<br>
      — В OpenServer пароль обычно <b>пустой</b> (оставьте <code>''</code>)<br>
      — В XAMPP пароль тоже обычно пустой<br>
      — Если вы его меняли — впишите свой пароль
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
