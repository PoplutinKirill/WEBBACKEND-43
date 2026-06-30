<?php
// =========================================================================
// НАСТРОЙКИ — отредактируйте под свой сервер
// =========================================================================
// Если существует config.php (создаётся install.php) — берём оттуда
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'aquatica');
    define('DB_USER', 'aquatica_user');
    define('DB_PASS', 'Aquatica_2025!');
}

// ── Formspree — отправка писем без SMTP и паролей ──────────────────────────
// 1. Зайдите на https://formspree.io → Sign Up (бесплатно)
// 2. New Form → укажите poplutinkirill@gmail.com → скопируйте endpoint
// 3. Вставьте сюда, например: 'https://formspree.io/f/xpwzabcd'
define('FORMSPREE_URL', 'https://formspree.io/f/mjgqrpbk');
define('ADMIN_EMAIL',   'poplutinkirill@gmail.com');

define('ADMIN_PASS', 'admin123');
define('F_ORDERS',   __DIR__ . '/db_orders.txt'); // резервный лог

// =========================================================================
// ИНИЦИАЛИЗАЦИЯ
// =========================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) session_start();

// =========================================================================
// MySQL
// =========================================================================
function db(): ?PDO {
    static $pdo = null, $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    try {
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (PDOException $e) { $pdo = null; }
    return $pdo;
}

$pdo      = db();
$db_ok    = (bool)$pdo;
$db_error = $db_ok ? '' : '⚠️ MySQL недоступен. Откройте install.php для создания базы.';

// Загружаем услуги
if ($db_ok) {
    $all_tours = $pdo->query("SELECT id, title, category, price, descr AS `desc` FROM services WHERE active=1 ORDER BY id")->fetchAll();
} else {
    $all_tours = [
        ['id'=>1,'title'=>'Peak Performance Buoyancy','category'=>'courses','price'=>12000,'desc'=>'Курс идеальной плавучести.'],
        ['id'=>2,'title'=>'Deep Diver Course','category'=>'courses','price'=>16500,'desc'=>'Погружения до 40 м.'],
        ['id'=>3,'title'=>'Night Diver','category'=>'courses','price'=>14000,'desc'=>'Ночные погружения.'],
        ['id'=>4,'title'=>'Погружение на Баренцевом море','category'=>'tours','price'=>45000,'desc'=>'Тур на Север.'],
        ['id'=>5,'title'=>'Дайв-тур в Египет','category'=>'tours','price'=>68000,'desc'=>'Рифы Красного моря.'],
        ['id'=>6,'title'=>'Экспедиция на Мальдивы','category'=>'tours','price'=>185000,'desc'=>'VIP сафари на яхте.'],
    ];
}

// =========================================================================
// FORMSPREE — отправка письма без SMTP и паролей
// Требуется только бесплатный аккаунт на formspree.io
// =========================================================================
function smtp_send(string $to, string $subject, string $body): bool|string {
    if (strpos(FORMSPREE_URL, 'ВАША_ССЫЛКА') !== false) {
        return 'Formspree не настроен. Вставьте свою ссылку в FORMSPREE_URL в index.php';
    }
    $data = json_encode([
        '_replyto' => $to,
        'email'    => $to,
        'subject'  => $subject,
        'message'  => $body,
    ]);
    $ch = curl_init(FORMSPREE_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return "cURL ошибка: $err";
    $json = json_decode($resp, true);
    return isset($json['ok']) && $json['ok'] === true ? true : "Formspree: " . ($json['error'] ?? $resp);
}

// =========================================================================
// Счётчик визитов
// =========================================================================
$visit_count = isset($_COOKIE['visit_count']) ? (int)$_COOKIE['visit_count'] + 1 : 1;
setcookie('visit_count', $visit_count, time() + 3600*24*7, '/');

// =========================================================================
// Навигация
// =========================================================================
$page = $_POST['page'] ?? $_GET['page'] ?? 'home';
if (isset($_POST['auto_service'])) $_SESSION['chosen_service'] = $_POST['auto_service'];
if (isset($_POST['logout_trigger'])) { session_destroy(); header("Location: index.php"); exit; }

$review_msg = $order_msg = $login_error = $register_error = '';
$register_success = false;

// =========================================================================
// ОТЗЫВЫ
// =========================================================================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_review_action'])) {
    $rname = strip_tags(trim($_POST['review_name']));
    $rtext = strip_tags(trim($_POST['review_text']));
    if (mb_strlen($rtext)>=5 && !empty($rname)) {
        if ($db_ok) {
            $pdo->prepare("INSERT INTO reviews (author,body) VALUES (?,?)")->execute([$rname, $rtext]);
        }
        $review_msg = '<p class="msg-ok">Отзыв успешно добавлен!</p>';
    } else {
        $review_msg = '<p class="msg-err">Текст слишком короткий (минимум 5 символов).</p>';
    }
    $page = 'reviews';
}

// =========================================================================
// РЕГИСТРАЦИЯ
// =========================================================================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['register_action'])) {
    $rn = strip_tags(trim($_POST['reg_name']));
    $re = filter_var(trim($_POST['reg_email']), FILTER_SANITIZE_EMAIL);
    $rp = trim($_POST['reg_password']);
    if ($rn && $re && $rp) {
        if ($db_ok) {
            $exists = $pdo->prepare("SELECT id FROM users WHERE email=?")->execute([$re]);
            $row    = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $row->execute([$re]);
            if ($row->fetch()) {
                $register_error = 'Пользователь с таким Email уже существует!'; $page='register';
            } else {
                $pdo->prepare("INSERT INTO users (email,pass_hash,name) VALUES (?,?,?)")->execute([$re, password_hash($rp, PASSWORD_BCRYPT), $rn]);
                $register_success = true; $page='login';
            }
        } else {
            $register_error = 'База данных недоступна. Попробуйте позже.'; $page='register';
        }
    } else { $register_error = 'Заполните все поля!'; $page='register'; }
}

// =========================================================================
// АВТОРИЗАЦИЯ
// =========================================================================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['login_action'])) {
    $le = trim($_POST['log_email']);
    $lp = trim($_POST['log_password']);
    if ($le==='admin@aquatica.ru' && $lp===ADMIN_PASS) {
        $_SESSION['user'] = ['email'=>$le,'name'=>'Администратор','role'=>'admin']; $page='home';
    } elseif ($db_ok) {
        $st = $pdo->prepare("SELECT * FROM users WHERE email=?"); $st->execute([$le]);
        $u  = $st->fetch();
        if ($u && password_verify($lp, $u['pass_hash'])) {
            $_SESSION['user'] = ['email'=>$u['email'],'name'=>$u['name'],'role'=>$u['role']]; $page='home';
        } else { $login_error='Неверный Email или пароль!'; $page='login'; }
    } else { $login_error='База данных недоступна.'; $page='login'; }
}

// =========================================================================
// ОФОРМЛЕНИЕ ЗАКАЗА
// =========================================================================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['order_action'])) {
    $oname    = strip_tags(trim($_POST['order_name']));
    $oemail   = filter_var($_POST['order_email'], FILTER_VALIDATE_EMAIL);
    $oservice = strip_tags($_POST['order_service']);
    $ofood    = $_POST['order_food'] ?? 'Не выбрано';
    $oopts    = isset($_POST['order_opts']) ? implode(', ', $_POST['order_opts']) : 'Нет';
    $odoc     = (isset($_FILES['order_doc']) && $_FILES['order_doc']['error']===UPLOAD_ERR_OK)
                    ? $_FILES['order_doc']['name'] : '';

    if ($oname && $oemail && $oservice) {
        // Сохраняем в БД
        $mail_sent = 0;
        if ($db_ok) {
            $pdo->prepare("INSERT INTO orders (client_name,client_email,service,food,options,doc_name) VALUES (?,?,?,?,?,?)")
                ->execute([$oname, $oemail, $oservice, $ofood, $oopts, $odoc]);
        }
        // Резервный лог
        $log = "Дата: ".date('d.m.Y H:i')."\nКлиент: $oname ($oemail)\nПрограмма: $oservice\nПитание: $ofood\nОпции: $oopts\n";
        if ($odoc) $log .= "Файл: $odoc\n";
        file_put_contents(F_ORDERS, $log."------\n", FILE_APPEND);

        // --- Письмо администратору ---
        $adminBody = "Новый заказ!\n\n".$log;
        $adminRes  = smtp_send(ADMIN_EMAIL, "🌊 Новый заказ: $oservice", $adminBody);

        // --- Письмо клиенту ---
        $clientBody = "Здравствуйте, $oname!\n\nВаш заказ принят. Мы свяжемся с вами в ближайшее время.\n\n"
            ."=== Детали заказа ===\nПрограмма: $oservice\nПитание: $ofood\nОпции: $oopts\n\n"
            ."С уважением,\nДайвинг-клуб «Акватика»";
        $clientRes = smtp_send($oemail, "Ваш заказ в Акватике — $oservice", $clientBody);

        if ($db_ok) {
            $sent = ($adminRes===true && $clientRes===true) ? 1 : 0;
            $pdo->prepare("UPDATE orders SET mail_sent=? WHERE id=LAST_INSERT_ID()")->execute([$sent]);
        }

        unset($_SESSION['chosen_service']);
        $mailNote = ($clientRes===true)
            ? "<br><span style='color:#1a7a1a'>✉️ Подтверждение отправлено на <b>$oemail</b> и на <b>".ADMIN_EMAIL."</b></span>"
            : "<br><span style='color:#c06000'>⚠️ Письмо не отправлено: ".htmlspecialchars((string)$clientRes)."</span>";

        $order_msg = "<div class='order-ok'>✅ Заказ оформлен!$mailNote</div>";
        $page = 'order';
    } else {
        $order_msg = "<div class='order-err'>Заполните все обязательные поля.</div>";
        $page = 'order';
    }
}

// =========================================================================
// ОТЗЫВЫ — список
// =========================================================================
$reviews_data = [];
if ($db_ok) {
    $reviews_data = $pdo->query("SELECT author, body, DATE_FORMAT(created_at,'%d.%m.%Y %H:%i') AS dt FROM reviews ORDER BY id DESC LIMIT 50")->fetchAll();
}

// =========================================================================
// ФИЛЬТРЫ КАТАЛОГА
// =========================================================================
if ($_SERVER['REQUEST_METHOD']==='POST' && ($POST['page']??'')==='catalog') {
    $_SESSION['cat_filter']   = $_POST['cat_filter']   ?? 'all';
    $_SESSION['search_query'] = $_POST['search_query'] ?? '';
    $_SESSION['sort_order']   = $_POST['sort_order']   ?? 'asc';
}
$cat_filter   = $_SESSION['cat_filter']   ?? 'all';
$search_query = $_SESSION['search_query'] ?? '';
$sort_order   = $_SESSION['sort_order']   ?? 'asc';
$filtered = $all_tours;
if ($cat_filter!=='all')    $filtered = array_filter($filtered, fn($t)=>$t['category']===$cat_filter);
if (!empty($search_query))  $filtered = array_filter($filtered, fn($t)=>mb_stripos($t['title'],$search_query)!==false);
usort($filtered, fn($a,$b)=>$sort_order==='asc' ? $a['price']-$b['price'] : $b['price']-$a['price']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Акватика — Дайвинг в Москве</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=PT+Sans:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet"/>
<style>
:root{--dk:#003366;--md:#0055aa;--bl:#0077cc;--lt:#00aadd;--pale:#e0f4fb;--ac:#00c3f0;--wh:#fff;--tx:#2c3e50;--tl:#666;--bg:#f7fbfe}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'PT Sans',sans-serif;color:var(--tx);background:var(--wh);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.container{max-width:1140px;margin:0 auto;padding:0 20px}
.btn{display:inline-block;padding:12px 28px;border-radius:4px;font-family:'PT Sans',sans-serif;font-weight:700;font-size:15px;cursor:pointer;transition:all .25s;border:2px solid transparent;background:none;text-align:center}
.btn-p{background:var(--lt);color:var(--wh);border-color:var(--lt)}.btn-p:hover{background:var(--bl);border-color:var(--bl)}
.btn-lg{padding:16px 40px;font-size:17px}
.btn-ow{background:transparent;color:var(--wh);border-color:var(--wh);font-size:14px;padding:8px 18px}.btn-ow:hover{background:var(--wh);color:var(--bl)}
.btn-sm{background:var(--lt);color:var(--wh);padding:8px 18px;font-size:13px;border-radius:3px;border:none;font-weight:bold;cursor:pointer}.btn-sm:hover{background:var(--bl)}
.sec{padding:70px 0}
.sec-title{font-family:'Oswald',sans-serif;font-size:clamp(30px,4vw,44px);font-weight:700;text-align:center;color:var(--tx);margin-bottom:36px;line-height:1.15}
.sec-title em{color:var(--lt);font-style:normal}
/* header */
.hdr{position:absolute;top:0;left:0;right:0;z-index:100;background:rgba(0,20,50,.88);backdrop-filter:blur(4px)}
.hdr-in{display:flex;align-items:center;gap:24px;padding:14px 0}
.logo{display:flex;align-items:center;gap:10px;flex-shrink:0;cursor:pointer}
.logo-t{font-family:'Oswald',sans-serif;font-size:22px;font-weight:600;color:var(--wh);letter-spacing:1px}
.nav{display:flex;gap:18px;flex:1;justify-content:center;align-items:center}
.nb{background:none;border:none;color:rgba(255,255,255,.9);font-size:14px;font-weight:700;letter-spacing:.5px;cursor:pointer;font-family:'PT Sans',sans-serif;transition:color .2s;padding:5px 10px}
.nb:hover,.nb.act{color:var(--ac)}
.hdr-act{display:flex;align-items:center;gap:14px;flex-shrink:0}
/* hero */
.hero{position:relative;min-height:500px;background:linear-gradient(180deg,#001e3c 0%,#003366 100%);display:flex;align-items:center;padding-top:60px}
.hero-c{position:relative;z-index:10;max-width:560px;color:#fff}
.hero-sub{font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--ac);margin-bottom:16px}
.hero-h{font-family:'Oswald',sans-serif;font-size:clamp(32px,4.5vw,52px);font-weight:700;line-height:1.15;margin-bottom:32px}
.hero-h span{color:var(--ac)}
.badges{display:flex;gap:28px;margin-top:24px;color:rgba(255,255,255,.85);font-size:14px;font-weight:700}
/* features */
.feat{background:var(--wh);padding:40px 0}
.feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.fc{background:var(--pale);border-radius:8px;padding:28px 24px;text-align:center;border-top:3px solid var(--lt)}
.fc h3{font-family:'Oswald',sans-serif;font-size:18px;font-weight:600;color:var(--dk);margin-bottom:8px}
.fc p{font-size:14px;color:var(--tl);margin-bottom:16px}
/* cards */
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:20px}
.card{background:var(--wh);border-radius:8px;overflow:hidden;box-shadow:0 4px 16px rgba(0,50,120,.1);display:flex;flex-direction:column}
.card-img{height:140px;display:flex;align-items:center;justify-content:center;font-size:36px;color:#fff;font-family:'Oswald',sans-serif;font-weight:bold;background:linear-gradient(135deg,#0055aa,#00aadd)}
.card-body{padding:16px;flex:1;display:flex;flex-direction:column;justify-content:space-between}
.card-body h4{font-family:'Oswald',sans-serif;font-size:16px;font-weight:600;color:var(--dk);margin-bottom:8px}
.card-body p{font-size:13px;color:var(--tl);line-height:1.5;margin-bottom:12px}
/* reviews */
.rev-item{background:rgba(0,0,0,.02);padding:20px;border-radius:8px;border-left:4px solid var(--lt);margin-bottom:14px}
.rev-item strong{font-family:'Oswald',sans-serif;color:var(--dk);font-size:16px}
.rev-item small{float:right;color:#999}
.rev-item p{font-size:14px;margin-top:8px;color:#444}
/* form */
.form-box{max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.06);border:1px solid #eee}
.fi{width:100%;padding:11px 14px;border:1px solid #ccc;border-radius:4px;margin-top:6px;margin-bottom:16px;font-size:14px;font-family:inherit}
.fi:focus{border-color:var(--lt);outline:none}
label{font-size:14px;font-weight:700;color:var(--dk)}
/* messages */
.msg-ok{color:green;font-weight:bold;text-align:center;margin-bottom:15px}
.msg-err{color:red;font-weight:bold;text-align:center;margin-bottom:15px}
.order-ok{background:#e0f4fb;padding:15px;color:#004a90;border-radius:4px;font-weight:bold;text-align:center;margin-bottom:20px;line-height:1.8}
.order-err{background:#ffecec;padding:15px;color:red;border-radius:4px;font-weight:bold;text-align:center;margin-bottom:20px}
.badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:bold;margin-bottom:14px}
.b-ok{background:#d4edda;color:#155724}.b-err{background:#fff3cd;color:#856404}
.db-warn{background:#fff3cd;color:#856404;padding:10px 20px;text-align:center;font-size:13px}
/* footer */
.footer{background:var(--dk);color:#fff;padding:36px 0;margin-top:40px}
.footer-in{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
/* burger */
.burger{display:none;flex-direction:column;justify-content:center;gap:5px;width:38px;height:38px;cursor:pointer;z-index:200;background:none;border:none;padding:4px}
.burger span{display:block;height:3px;width:100%;background:#fff}
.mob-menu{display:none;position:fixed;inset:0;background:rgba(0,20,50,.96);z-index:150;flex-direction:column;align-items:center;justify-content:center;gap:22px}
.mob-menu.open{display:flex}
@media(max-width:900px){.burger{display:flex}.nav,.hdr-act{display:none}.cards{grid-template-columns:1fr 1fr}.feat-grid{grid-template-columns:1fr}}
@media(max-width:600px){.cards{grid-template-columns:1fr}}
</style>
</head>
<body>

<header class="hdr">
  <div class="container hdr-in">
    <div class="logo" onclick="go('home')"><span class="logo-t">🌊 Акватика</span></div>
    <form method="POST" action="index.php" class="nav" id="nf">
      <input type="hidden" name="page" id="np" value="<?=htmlspecialchars($page)?>">
      <button type="button" class="nb <?=$page==='home'?'act':''?>" onclick="go('home')">Главная</button>
      <button type="button" class="nb <?=$page==='catalog'?'act':''?>" onclick="go('catalog')">Каталог</button>
      <button type="button" class="nb <?=$page==='reviews'?'act':''?>" onclick="go('reviews')">Отзывы</button>
      <button type="button" class="nb <?=$page==='order'?'act':''?>" onclick="go('order')">Оформить заказ</button>
      <?php if(isset($_SESSION['user'])): ?>
        <span style="color:var(--ac);font-weight:bold;font-size:14px;">[<?=htmlspecialchars($_SESSION['user']['name'])?>]</span>
        <?php if($_SESSION['user']['role']==='admin'): ?>
          <button type="button" class="nb" style="color:#ff5555" onclick="go('admin')">Админка</button>
        <?php endif; ?>
        <button type="submit" name="logout_trigger" class="nb" style="color:#ccc">Выход</button>
      <?php else: ?>
        <button type="button" class="nb <?=$page==='login'?'act':''?>" onclick="go('login')">Вход</button>
        <button type="button" class="nb <?=$page==='register'?'act':''?>" onclick="go('register')">Регистрация</button>
      <?php endif; ?>
    </form>
    <div class="hdr-act">
      <button class="btn btn-ow" onclick="go('order')">Записаться</button>
      <span style="font-size:11px;background:#ffaa00;padding:4px 8px;border-radius:10px;color:#000;font-weight:bold;">Визиты: <?=$visit_count?></span>
    </div>
    <button class="burger" onclick="toggleMenu()"><span></span><span></span><span></span></button>
  </div>
</header>

<div class="mob-menu" id="mm">
  <button class="nb" onclick="gom('home')">Главная</button>
  <button class="nb" onclick="gom('catalog')">Каталог</button>
  <button class="nb" onclick="gom('reviews')">Отзывы</button>
  <button class="nb" onclick="gom('order')">Оформить заказ</button>
  <?php if(isset($_SESSION['user'])): ?>
    <button class="nb" onclick="document.getElementById('nf').submit()">Выход</button>
  <?php else: ?>
    <button class="nb" onclick="gom('login')">Вход</button>
    <button class="nb" onclick="gom('register')">Регистрация</button>
  <?php endif; ?>
</div>

<script>
function go(p){document.getElementById('np').value=p;document.getElementById('nf').submit();}
function gom(p){toggleMenu();go(p);}
function toggleMenu(){document.getElementById('mm').classList.toggle('open');}
</script>

<main style="padding-top:80px;min-height:70vh">

<?php if($db_error): ?>
<div class="db-warn"><?=$db_error?> &nbsp;|&nbsp; <a href="install.php" style="color:#0055aa;font-weight:bold;">Запустить установщик</a></div>
<?php endif; ?>

<?php if($page==='home'): ?>
<section class="hero">
  <div class="container hero-c">
    <p class="hero-sub">Дайвинг клуб Акватика</p>
    <h1 class="hero-h">Путешествие в тысячу миль<br/><span>Начинается с первого клика</span></h1>
    <button onclick="go('catalog')" class="btn btn-p btn-lg">Перейти в каталог</button>
    <div class="badges"><span>✔ Работаем с 2009 года</span><span>✔ Более 2500 учеников</span></div>
  </div>
</section>
<section class="feat container">
  <div class="feat-grid">
    <div class="fc"><h3>Доступный дайвинг</h3><p>Акции и особые предложения на курсы</p><button onclick="go('catalog')" class="btn-sm">В каталог</button></div>
    <div class="fc"><h3>Онлайн отзывы</h3><p>Мы прислушиваемся к каждому мнению</p><button onclick="go('reviews')" class="btn-sm">Читать отзывы</button></div>
    <div class="fc"><h3>Запись на курсы</h3><p>Оформите заявку онлайн за 1 минуту</p><button onclick="go('order')" class="btn-sm">Оформить заявку</button></div>
  </div>
</section>

<?php elseif($page==='catalog'): ?>
<section class="sec"><div class="container">
  <h2 class="sec-title">Каталог <em>предложений</em></h2>
  <span class="badge <?=$db_ok?'b-ok':'b-err'?>"><?=$db_ok?'✅ Услуги из MySQL':'⚠️ Демо-данные'?></span>
  <form method="POST" action="index.php" style="background:var(--pale);padding:20px;border-radius:6px;display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end;margin-bottom:30px">
    <input type="hidden" name="page" value="catalog">
    <div style="flex:1;min-width:200px"><label>Поиск:</label><input type="text" name="search_query" class="fi" style="margin-bottom:0" value="<?=htmlspecialchars($search_query)?>" placeholder="Ключевое слово..."></div>
    <div><label>Категория:</label>
      <select name="cat_filter" class="fi" style="margin-bottom:0" onchange="this.form.submit()">
        <option value="all" <?=$cat_filter==='all'?'selected':''?>>Все</option>
        <option value="courses" <?=$cat_filter==='courses'?'selected':''?>>Курсы</option>
        <option value="tours" <?=$cat_filter==='tours'?'selected':''?>>Туры</option>
      </select></div>
    <div><label>Сортировка:</label>
      <select name="sort_order" class="fi" style="margin-bottom:0" onchange="this.form.submit()">
        <option value="asc" <?=$sort_order==='asc'?'selected':''?>>Цена ↑</option>
        <option value="desc" <?=$sort_order==='desc'?'selected':''?>>Цена ↓</option>
      </select></div>
    <button type="submit" class="btn btn-p" style="padding:11px 24px">Найти</button>
  </form>
  <div class="cards">
    <?php if(empty($filtered)): ?>
      <p style="grid-column:1/-1;text-align:center;color:gray;padding:40px;font-weight:bold">Ничего не найдено.</p>
    <?php else: foreach($filtered as $t): ?>
      <div class="card">
        <div class="card-img"><?=number_format($t['price'],0,'.',' ')?> ₽</div>
        <div class="card-body">
          <div><h4><?=htmlspecialchars($t['title'])?></h4><p><?=htmlspecialchars($t['desc'])?></p></div>
          <form method="POST" action="index.php">
            <input type="hidden" name="page" value="order">
            <input type="hidden" name="auto_service" value="<?=htmlspecialchars($t['title'])?>">
            <button type="submit" class="btn-sm" style="width:100%;margin-top:8px">Заказать →</button>
          </form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div></section>

<?php elseif($page==='reviews'): ?>
<section class="sec"><div class="container">
  <h2 class="sec-title">Отзывы <em>наших клиентов</em></h2>
  <?=$review_msg?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;align-items:start">
    <div>
      <?php if(empty($reviews_data)): ?>
        <div class="rev-item"><p>Отзывов пока нет. Будьте первыми!</p></div>
      <?php else: foreach($reviews_data as $rev): ?>
        <div class="rev-item">
          <strong><?=htmlspecialchars($rev['author'])?></strong>
          <small><?=$rev['dt']?></small>
          <p><?=htmlspecialchars($rev['body'])?></p>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <div class="form-box" style="width:100%">
      <h3 style="font-family:'Oswald',sans-serif;margin-bottom:15px">Оставить отзыв</h3>
      <form method="POST" action="index.php">
        <input type="hidden" name="page" value="reviews">
        <label>Имя:</label>
        <input type="text" name="review_name" required class="fi" value="<?=isset($_SESSION['user'])?htmlspecialchars($_SESSION['user']['name']):''?>">
        <label>Текст:</label>
        <textarea name="review_text" required class="fi" style="height:120px;resize:none"></textarea>
        <button type="submit" name="add_review_action" class="btn btn-p" style="width:100%">Опубликовать</button>
      </form>
    </div>
  </div>
</div></section>

<?php elseif($page==='order'): ?>
<section class="sec"><div class="container">
  <h2 class="sec-title">Оформление <em>заказа</em></h2>
  <?=$order_msg?>
  <div class="form-box">
    <p style="font-size:13px;color:#666;margin-bottom:18px">После оформления вы и администратор получите письма на указанный Email.</p>
    <form method="POST" action="index.php" enctype="multipart/form-data">
      <input type="hidden" name="page" value="order">
      <label>ФИО *</label>
      <input type="text" name="order_name" required class="fi" value="<?=isset($_SESSION['user'])?htmlspecialchars($_SESSION['user']['name']):''?>">
      <label>Email * <span style="font-weight:normal;color:#888">(сюда придёт подтверждение)</span></label>
      <input type="email" name="order_email" required class="fi" value="<?=isset($_SESSION['user'])?htmlspecialchars($_SESSION['user']['email']):''?>">
      <label>Программа / Курс:</label>
      <input type="text" name="order_service" readonly class="fi" style="background:#eee" value="<?=htmlspecialchars($_SESSION['chosen_service']??'Индивидуальная консультация')?>">
      <div style="margin-bottom:15px">
        <label>Питание:</label><br>
        <input type="radio" name="order_food" value="Включено (Пицца)" checked> Пицца на борт<br>
        <input type="radio" name="order_food" value="Не требуется"> Без питания
      </div>
      <div style="margin-bottom:15px">
        <label>Дополнительно:</label><br>
        <input type="checkbox" name="order_opts[]" value="Аренда снаряжения"> Снаряжение в аренду<br>
        <input type="checkbox" name="order_opts[]" value="Страховка"> Дайв-страховка
      </div>
      <label>Прикрепить документ:</label>
      <input type="file" name="order_doc" style="display:block;margin-top:5px;margin-bottom:20px">
      <button type="submit" name="order_action" class="btn btn-p" style="width:100%">Отправить заказ</button>
    </form>
  </div>
</div></section>

<?php elseif($page==='login'): ?>
<section class="sec"><div class="container">
  <h2 class="sec-title">Вход в <em>Личный кабинет</em></h2>
  <?php if($register_success): ?><p class="msg-ok">Регистрация успешна! Войдите.</p><?php endif; ?>
  <?php if($login_error): ?><p class="msg-err"><?=$login_error?></p><?php endif; ?>
  <div class="form-box" style="max-width:380px">
    <form method="POST" action="index.php">
      <input type="hidden" name="page" value="login">
      <label>Email:</label><input type="email" name="log_email" required class="fi">
      <label>Пароль:</label><input type="password" name="log_password" required class="fi">
      <button type="submit" name="login_action" class="btn btn-p" style="width:100%">Войти</button>
    </form>
  </div>
</div></section>

<?php elseif($page==='register'): ?>
<section class="sec"><div class="container">
  <h2 class="sec-title">Регистрация <em>аккаунта</em></h2>
  <?php if($register_error): ?><p class="msg-err"><?=$register_error?></p><?php endif; ?>
  <div class="form-box" style="max-width:380px">
    <form method="POST" action="index.php">
      <input type="hidden" name="page" value="register">
      <label>Имя:</label><input type="text" name="reg_name" required class="fi">
      <label>Email:</label><input type="email" name="reg_email" required class="fi">
      <label>Пароль:</label><input type="password" name="reg_password" required class="fi">
      <button type="submit" name="register_action" class="btn btn-p" style="width:100%">Зарегистрироваться</button>
    </form>
  </div>
</div></section>

<?php elseif($page==='admin' && isset($_SESSION['user']) && $_SESSION['user']['role']==='admin'): ?>
<section class="sec"><div class="container">
  <h2 class="sec-title" style="color:red">Панель <em>Администратора</em></h2>
  <?php if($db_ok): ?>
  <!-- Таблица заказов -->
  <h3 style="font-family:'Oswald',sans-serif;margin-bottom:10px;color:var(--dk)">Заказы из MySQL</h3>
  <div style="overflow-x:auto;margin-bottom:30px">
    <table style="width:100%;border-collapse:collapse;font-size:13px;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
      <tr style="background:var(--dk);color:#fff">
        <th style="padding:10px">ID</th><th style="padding:10px">Клиент</th><th style="padding:10px">Email</th>
        <th style="padding:10px">Программа</th><th style="padding:10px">Письмо</th><th style="padding:10px">Дата</th>
      </tr>
      <?php foreach($pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 100")->fetchAll() as $o): ?>
      <tr style="border-top:1px solid #eee">
        <td style="padding:8px;text-align:center"><?=$o['id']?></td>
        <td style="padding:8px"><?=htmlspecialchars($o['client_name'])?></td>
        <td style="padding:8px"><?=htmlspecialchars($o['client_email'])?></td>
        <td style="padding:8px"><?=htmlspecialchars($o['service'])?></td>
        <td style="padding:8px;text-align:center"><?=$o['mail_sent']?'✅':'❌'?></td>
        <td style="padding:8px"><?=$o['created_at']?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <!-- Услуги -->
  <h3 style="font-family:'Oswald',sans-serif;margin-bottom:10px;color:var(--dk)">Услуги (таблица services)</h3>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
      <tr style="background:var(--md);color:#fff">
        <th style="padding:10px">ID</th><th style="padding:10px">Название</th><th style="padding:10px">Категория</th><th style="padding:10px">Цена</th>
      </tr>
      <?php foreach($all_tours as $t): ?>
      <tr style="border-top:1px solid #eee">
        <td style="padding:8px;text-align:center"><?=$t['id']?></td>
        <td style="padding:8px"><?=htmlspecialchars($t['title'])?></td>
        <td style="padding:8px"><?=$t['category']?></td>
        <td style="padding:8px;text-align:right"><?=number_format($t['price'],0,'.',' ')?> ₽</td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
  <div style="background:#222;color:#0f0;padding:20px;border-radius:6px;font-family:monospace;white-space:pre-wrap">
    <b>Файловый лог (db_orders.txt):</b>
    <hr style="border-color:#444;margin:10px 0">
    <?=file_exists(F_ORDERS)?htmlspecialchars(file_get_contents(F_ORDERS)):'Заказов нет.'?>
  </div>
  <?php endif; ?>
</div></section>

<?php endif; ?>
</main>

<footer class="footer">
  <div class="container footer-in">
    <div>&copy; Дайвинг-клуб «Акватика» | <?=date('H:i:s')?></div>
    <div style="font-size:12px;color:rgba(255,255,255,.5)">
      MySQL: <?=$db_ok?'✅ подключено':'❌ не подключено'?> &nbsp;|&nbsp;
      Email: Formspree &nbsp;|&nbsp;
      Письма → <?=ADMIN_EMAIL?>
    </div>
  </div>
</footer>
</body>
</html>
