<?php

class StatsController
{
    public function index(): void
    {
        $sort = (string)($_GET['sort'] ?? 'name');
        if (!in_array($sort, ['name', 'last'], true)) {
            $sort = 'name';
        }
        $dir = (string)($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $visits = Db::fetchAll(
            'SELECT v.*, u.address_term, u.name
             FROM visits v
             JOIN users u ON u.id = v.user_id
             ORDER BY v.id DESC
             LIMIT 50'
        );

        // Сводка: сколько входов у каждого участника по месяцам + последний вход
        $byUser = [];
        $monthKeys = [];
        foreach (Db::fetchAll(
            "SELECT user_id, substr(created_at, 1, 7) AS ym, COUNT(*) AS cnt, MAX(created_at) AS last_at
             FROM visits GROUP BY user_id, substr(created_at, 1, 7)"
        ) as $row) {
            $uid = (int)$row['user_id'];
            $ym = (string)$row['ym'];
            $byUser[$uid]['months'][$ym] = (int)$row['cnt'];
            $byUser[$uid]['total'] = ($byUser[$uid]['total'] ?? 0) + (int)$row['cnt'];
            $last = (string)$row['last_at'];
            if (!isset($byUser[$uid]['last']) || $last > $byUser[$uid]['last']) {
                $byUser[$uid]['last'] = $last;
            }
            $monthKeys[$ym] = true;
        }

        $months = $this->monthsList(array_keys($monthKeys));

        // В таблицу попадают только те, у кого есть входы
        $users = array_values(array_filter(
            Db::fetchAll('SELECT id, address_term, name, role, is_active FROM users'),
            static fn(array $u): bool => isset($byUser[(int)$u['id']])
        ));
        usort($users, function (array $a, array $b) use ($sort, $dir, $byUser): int {
            if ($sort === 'last') {
                $la = (string)($byUser[(int)$a['id']]['last'] ?? '');
                $lb = (string)($byUser[(int)$b['id']]['last'] ?? '');
                // участники без входов — всегда в конце
                if (($la === '') !== ($lb === '')) {
                    return $la === '' ? 1 : -1;
                }
                $cmp = strcmp($la, $lb);
                if ($cmp === 0) {
                    $cmp = mb_strtolower($a['name']) <=> mb_strtolower($b['name']);
                }
            } else {
                $cmp = mb_strtolower($a['name']) <=> mb_strtolower($b['name']);
            }
            return $dir === 'desc' ? -$cmp : $cmp;
        });

        view('stats', [
            'title' => 'Статистика',
            'visits' => $visits,
            'users' => $users,
            'byUser' => $byUser,
            'months' => $months,
            'monthLabels' => $this->monthLabels($months),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /** Непрерывный список месяцев (не больше 12) от первого входа до текущего. */
    private function monthsList(array $keys): array
    {
        $current = date('Y-m');
        if (!$keys) {
            return [$current];
        }
        sort($keys, SORT_STRING);
        $from = $keys[0] < $current ? $keys[0] : $current;
        $list = [];
        $ts = strtotime($from . '-01');
        $end = strtotime($current . '-01');
        while ($ts !== false && $ts <= $end) {
            $list[] = date('Y-m', $ts);
            $ts = strtotime('+1 month', $ts);
        }
        return count($list) > 12 ? array_slice($list, -12) : $list;
    }

    /** Подписи месяцев вида «ноя 26». */
    private function monthLabels(array $months): array
    {
        $names = [1 => 'янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        $out = [];
        foreach ($months as $m) {
            $ts = strtotime($m . '-01');
            if ($ts === false) {
                $out[$m] = $m;
                continue;
            }
            $out[$m] = $names[(int)date('n', $ts)] . ' ' . date('y', $ts);
        }
        return $out;
    }
}
