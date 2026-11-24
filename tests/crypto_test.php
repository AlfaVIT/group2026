<?php

/**
 * Тесты слоя шифрования (запуск: php tests/crypto_test.php).
 *
 * Файл лежит вне cms/, поэтому на прод не выкладывается.
 */

define('BASE_PATH', dirname(__DIR__) . '/cms');
define('CRYPTO_ENABLED', true);

$tmpKey = sys_get_temp_dir() . '/cms-crypto-test-' . getmypid() . '/master.key';
define('CRYPTO_KEY_FILE', $tmpKey);

require BASE_PATH . '/app/crypto.php';

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

function expectException(string $name, callable $fn): void
{
    try {
        $fn();
        check($name, false, 'исключения не было');
    } catch (CryptoException $e) {
        check($name, true);
    }
}

echo "Окружение\n";
check('openssl + aes-256-gcm доступны', Crypto::available());

// --- Эталонный вектор AES-256-GCM (McGrew–Viega, тест 14: пустой AAD) ---
$katKey = str_repeat("\0", 32);
$katIv = str_repeat("\0", 12);
$katPlain = str_repeat("\0", 16);
$katTag = '';
$katCt = openssl_encrypt($katPlain, 'aes-256-gcm', $katKey, OPENSSL_RAW_DATA, $katIv, $katTag, '', 16);
eq('эталон AES-256-GCM: шифртекст', 'cea7403d4d606b6e074ec5d3baf39d18', bin2hex((string)$katCt));
eq('эталон AES-256-GCM: тег', 'd0d1c8a799996bf0265b98b5d48ab919', bin2hex($katTag));

echo "\nКлюч\n";
eq('файла ключа ещё нет', false, is_file(CRYPTO_KEY_FILE));
Crypto::createKey();
check('файл ключа создан', is_file(CRYPTO_KEY_FILE));
check('ключ валиден', Crypto::keyIsValid());
check('повторное создание не перезаписывает', Crypto::keyIsValid() && filesize(CRYPTO_KEY_FILE) > 0);
check('шифрование включено', Crypto::enabled());
eq('.htaccess рядом с ключом', true, is_file(dirname(CRYPTO_KEY_FILE) . '/.htaccess'));

echo "\nШифрование/расшифровка\n";
eq('пустая строка остаётся пустой', '', Crypto::encrypt(''));
$phone = '+7 (916) 123-45-67';
$enc = Crypto::encrypt($phone);
check('значение получило префикс e1:', Crypto::isEncrypted($enc), $enc);
check('шифртекст не содержит открытый текст', strpos($enc, '916') === false, $enc);
eq('расшифровка совпадает', $phone, Crypto::decrypt($enc));
eq('обычный текст проходит насквозь', 'просто текст', Crypto::decrypt('просто текст'));
check('кириллица и юникод', 'Иоанн Предтеча — ёжик 🙂' === Crypto::decrypt(Crypto::encrypt('Иоанн Предтеча — ёжик 🙂')));
$bin = random_bytes(512);
eq('бинарные данные', bin2hex($bin), bin2hex(Crypto::decrypt(Crypto::encrypt($bin))));
eq('большой текст (50 КБ)', str_repeat('строка ', 7000), Crypto::decrypt(Crypto::encrypt(str_repeat('строка ', 7000))));

$a = Crypto::encrypt($phone);
$b = Crypto::encrypt($phone);
check('два шифртекста одного значения различаются (случайный nonce)', $a !== $b);

echo "\nЦелостность\n";
$tampered = substr($enc, 0, -6) . (substr($enc, -6) === 'AAAAAA' ? 'BBBBBB' : 'AAAAAA');
expectException('испорченный шифртекст отвергается', fn() => Crypto::decrypt($tampered));
expectException('обрезанный шифртекст отвергается', fn() => Crypto::decrypt('e1:' . base64_encode(random_bytes(10))));
expectException('не-base64 отвергается', fn() => Crypto::decrypt('e1:@@@not-base64@@@'));
eq('tryDecrypt не падает и не теряет значение', $tampered, Crypto::tryDecrypt($tampered, 'test'));

echo "\nСлепой индекс\n";
$i1 = Crypto::blindIndex('users.email', 'ivan@example.com');
$i2 = Crypto::blindIndex('users.email', 'ivan@example.com');
check('детерминирован', $i1 === $i2 && strlen($i1) === 32, $i1);
check('разные области не совпадают', Crypto::blindIndex('users.login', 'ivan@example.com') !== $i1);
check('разные значения не совпадают', Crypto::blindIndex('users.email', 'petr@example.com') !== $i1);
eq('для пустого значения пусто', '', Crypto::blindIndex('users.email', ''));
check('не совпадает с открытым значением', strpos($i1, 'ivan') === false);

echo "\nСмена ключа\n";
$old = Crypto::encrypt('секрет');
Crypto::createKey();
Crypto::resetKeys();
expectException('старый шифртекст не читается новым ключом', fn() => Crypto::decrypt($old));
eq('новый ключ работает', 'секрет', Crypto::decrypt(Crypto::encrypt('секрет')));

echo "\nПроизводительность\n";
$n = 2000;
$t0 = hrtime(true);
for ($i = 0; $i < $n; $i++) {
    Crypto::decrypt(Crypto::encrypt('+7 (916) 123-45-67'));
}
$per = (hrtime(true) - $t0) / $n;
echo sprintf("  encrypt+decrypt одного телефона: %.1f мкс (%.0f тыс. полей/с)\n", $per / 1000, 1e6 / $per);

$big = str_repeat('x', 4096);
$t0 = hrtime(true);
for ($i = 0; $i < 200; $i++) {
    Crypto::decrypt(Crypto::encrypt($big));
}
$mbps = (200 * 4096 * 2) / ((hrtime(true) - $t0) / 1e9) / 1048576;
echo sprintf("  пропускная способность на 4 КБ блоках: %.0f МБ/с\n", $mbps);

echo "\n" . ($failures === 0 ? "ВСЁ ХОРОШО: $checks проверок" : "ПРОВАЛОВ: $failures из $checks") . "\n";
exit($failures === 0 ? 0 : 1);
