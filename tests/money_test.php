<?php

/**
 * Тесты денежных агрегатов (php tests/money_test.php).
 *
 * Главное требование: итог не зависит от порядка сложения и совпадает
 * с точной суммой в копейках (float-накопление теряет копейку на границе
 * округления, это и было причиной расхождения на 1 ₽).
 */

define('BASE_PATH', dirname(__DIR__) . '/cms');
require BASE_PATH . '/app/helpers.php';

$failures = 0;
$checks = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($ok) {
        echo "  ok   $name\n";
        return;
    }
    $failures++;
    echo "  FAIL $name" . ($detail !== '' ? " ($detail)" : '') . "\n";
}

function eq(string $name, $expected, $actual): void
{
    check($name, $expected === $actual, 'ожидалось ' . var_export($expected, true) . ', получено ' . var_export($actual, true));
}

echo "Копейки\n";
eq('1500.55 -> 150055', 150055, amount_cents('1500.55'));
eq('1500.55 обратно', 1500.55, cents_amount(150055));
eq('0', 0, amount_cents('0'));
eq('0.01', 1, amount_cents('0.01'));
eq('округление половины', 100, amount_cents('0.995'));

echo "\nСумма не зависит от порядка\n";
$values = [1234.56, 0.1, 0.2, 999.99, 7.5, 0.01, 100000.01, 3.33, 55.55, 18.9];
$sum = amount_sum($values);
eq('amount_sum точная', 102320.15, $sum);
eq('money(amount_sum)', '102 320 ₽', money($sum));
$reversed = amount_sum(array_reverse($values));
eq('обратный порядок — тот же результат', $sum, $reversed);
eq('сумма в копейках', 10232015, amount_cents($sum));

echo "\nГраница округления (случай 232 871,50)\n";
$groups = [23287.15, 23287.15, 23287.15, 23287.15, 23287.15, 23287.15, 23287.15, 23287.15, 23287.15, 23287.15];
$naive = 0.0;
foreach ($groups as $g) {
    $naive += $g;
}
$exact = amount_sum($groups);
eq('точная сумма', 232871.5, $exact);
eq('точный итог в рублях', '232 872 ₽', money($exact));
eq('float-накопление в этом же случае', '232 871 ₽', money($naive));
eq('amount_cents(float) восстанавливает копейку', 23287150, amount_cents($naive));

echo "\nРазность\n";
eq('amount_diff', 608.25, amount_diff(233479.75, 232871.50));
eq('amount_diff отрицательная', -316.75, amount_diff(1000.25, 1317.00));
eq('money(amount_diff)', '608 ₽', money(amount_diff(233479.75, 232871.50)));

echo "\nСумма по строкам = итог\n";
$rows = [1500.55, 999.99, 0.01, 100000.01, 7.5, 18.9, 3.33, 55.55, 0.1, 0.2];
$byArticle = ['трапеза' => 0, 'взнос' => 0];
foreach ($rows as $i => $v) {
    $byArticle[$i % 2 === 0 ? 'трапеза' : 'взнос'] += amount_cents($v);
}
$total = amount_sum($rows);
eq('итог = сумма статей', array_sum($byArticle), amount_cents($total));
eq('статья «трапеза»', 1511.49, cents_amount($byArticle['трапеза']));
eq('статья «взнос»', 101074.65, cents_amount($byArticle['взнос']));

echo "\n" . ($failures === 0 ? "ВСЁ ХОРОШО: $checks проверок" : "ПРОВАЛОВ: $failures из $checks") . "\n";
exit($failures === 0 ? 0 : 1);
