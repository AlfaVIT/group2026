<?php

/**
 * Точка запуска резервной копии внешним планировщиком (cron хостинга или
 * внешний сервис): ?p=backup_cron&token=...&dry=1.
 *
 * Расписания внутри сайта нет: каждый вызов (кроме dry) создаёт и отправляет
 * бекап на почту администратора. Токен лежит в настройках (вкладка
 * «Обслуживание») и меняется кнопкой.
 */
class BackupController
{
    public function cron(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $expected = (string)Setting::get('cron_token', '');
        $token = get_str('token');
        if ($expected === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Неверный токен'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // ?crypto=1 — довести шифрование персональных данных до конца (порциями).
        // Тот же токен, что и у бекапов: отдельного доступа не нужно.
        if (get_str('crypto') === '1') {
            $res = crypto_sweep(20);
            echo json_encode([
                'ok'        => $res['ok'],
                'done'      => $res['done'],
                'encrypted' => $res['encrypted'],
                'left'      => crypto_count_open(),
                'error'     => $res['error'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (get_str('dry') === '1') {
            $status = backup_status();
            echo json_encode([
                'ok'         => true,
                'has_email'  => Setting::adminEmail() !== '',
                'last'       => $status['last'],
                'last_error' => $status['last_error'],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $res = backup_send();
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}
