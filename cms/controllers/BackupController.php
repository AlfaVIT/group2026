<?php

/**
 * Точка запуска обслуживания по расписанию (cron хостинга или внешний
 * планировщик): ?p=backup_cron&token=...&force=1&dry=1.
 *
 * Токен лежит в настройках (вкладка «Обслуживание») и меняется кнопкой.
 * Без force бекап уходит только когда наступил запланированный момент.
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

        $s = backup_schedule();
        if (get_str('dry') === '1') {
            $slot = backup_last_slot($s);
            $next = backup_next_slot($s);
            echo json_encode([
                'ok'         => true,
                'enabled'    => $s['enabled'],
                'due'        => backup_is_due($s),
                'has_email'  => Setting::adminEmail() !== '',
                'last'       => $s['last'],
                'last_slot'  => $slot !== null ? date('Y-m-d H:i:s', $slot) : null,
                'next_slot'  => $next !== null ? date('Y-m-d H:i:s', $next) : null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $res = backup_send(get_str('force') === '1');
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}
