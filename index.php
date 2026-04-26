//лабораторная работа 2
<?php
//  Работа с датой и временем 
function getWorkDaysInMonth($month, $year) {
    $date = new DateTime("$year-$month-01");
    $daysInMonth = $date->format('t');
    $workDays = [];
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $currentDate = new DateTime("$year-$month-$day");
        $dayOfWeek = $currentDate->format('N'); // 1 (пн) – 7 (вс)
        if ($dayOfWeek < 6) { // рабочие дни: пн-пт
            $workDays[] = $day;
        }
    }
    return $workDays;
}

// Цикл while (n = 22) 
$n = 22;
$iterations = $n + 5; 
$fullName = "Поплутин Кирилл"; 
$nameRepeat = "";
$counter = 0;
while ($counter < $iterations) {
    $nameRepeat .= $fullName . "<br>";
    $counter++;
}

// Работа с массивами 
$originalArray = [22, 4, 7, 52, 5];
$modifiedArray = $originalArray;
$minValue = min($modifiedArray);
$minIndex = array_search($minValue, $modifiedArray);
$lastIndex = count($modifiedArray) - 1;
// Перестановка минимального с последним
$temp = $modifiedArray[$minIndex];
$modifiedArray[$minIndex] = $modifiedArray[$lastIndex];
$modifiedArray[$lastIndex] = $temp;

//  Работа со строками 
$S1 = "Я люблю Беларусь";
$S2 = "Я учусь в Политехническом колледже";
$lengthS1 = strlen($S1);
$S2_cleaned = str_replace("учусь", "", $S2);
$S1_replaced = str_replace("ю", "*", $S1);

//   функция с обработкой ошибок 
function calculateFormula($x) {
    $denominator = 4 * $x * $x - 1;
    if ($denominator == 0) {
        throw new Exception("Ошибка: деление на ноль (знаменатель = 0) при x = $x");
    }
    $y = (2 * $x * $x) / $denominator;
    return $y;
}

// Пример расчётов для разных x
$x_values = [0.5, 1, 2, -0.5, 0];
$calcResults = [];
foreach ($x_values as $x) {
    try {
        $y = calculateFormula($x);
        $calcResults[] = "y($x) = " . round($y, 4);
    } catch (Exception $e) {
        $calcResults[] = "y($x): " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title> Вариант 22</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .task { background: white; margin: 20px 0; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
        pre { background: #ecf0f1; padding: 10px; border-radius: 5px; }
        code { color: #e74c3c; }
    </style>
</head>
<body>
    <h1>Лабораторная работа №22 — Основы синтаксиса PHP</h1>
    <p><strong>Вариант 22 (n=22)</strong> | Фамилия Имя выводятся <?= $iterations ?> раз</p>

    
    <div class="task">
        <h2>Задание 2: Рабочие дни в заданном месяце</h2>
        <?php
        $month = 4; 
        $year = 2026;
        $workDays = getWorkDaysInMonth($month, $year);
        ?>
        <p>Месяц: <?= $month ?>/<?= $year ?></p>
        <p><strong>Рабочие дни (пн-пт):</strong> <?= implode(", ", $workDays) ?></p>
        <p>Всего рабочих дней: <?= count($workDays) ?></p>
    </div>

    <div class="task">
        <h2>Задание 3: Вывод Фамилии и Имени (цикл while)</h2>
        <p>Выведено <?= $iterations ?> раз (n+5 = 22+5):</p>
        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
            <?= $nameRepeat ?>
        </div>
    </div>

    <div class="task">
        <h2>Задание 4: Работа с массивами</h2>
        <p><strong>Исходный массив:</strong> <?= implode(", ", $originalArray) ?></p>
        <p>Минимальный элемент: <code><?= $minValue ?></code> (на позиции <?= $minIndex+1 ?>)</p>
        <p><strong>Изменённый массив</strong> (мин. элемент с последним): <?= implode(", ", $modifiedArray) ?></p>
    </div>

<div class="task">
        <h2>Задание 5: Работа со строками</h2>
        <p><strong>S1:</strong> <?= $S1 ?></p>
        <p><strong>S2:</strong> <?= $S2 ?></p>
        <p>1. Длина строки S1: <code><?= $lengthS1 ?></code></p>
        <p>2. Удаляем "учусь" из S2: <code><?= $S2_cleaned ?></code></p>
        <p>3. Замена "ю" → "я" в S1: <code><?= $S1_replaced ?></code></p>
    </div>

    <div class="task">
        <h2>Задание 6: Пользовательская функция</h2>
        <p><strong>Формула:</strong> y = 2x² / (4x² - 1)</p>
        <h3>Результаты расчётов:</h3>
        <ul>
            <?php foreach ($calcResults as $res): ?>
                <li><?= $res ?></li>
            <?php endforeach; ?>
        </ul>
        <p><em>При x = 0.5 и x = -0.5 знаменатель обращается в ноль (4*0.25 - 1 = 0), поэтому выводится сообщение об ошибке.</em></p>
    </div>
</body>
</html>
