<?php

class TreasuryController
{
    public function index(): void
    {
        $start = input('from');
        $end = input('to');
        $purpose = input('purpose');

        $where = [];
        $params = [];
        if ($start !== '') {
            $where[] = 't.date >= ?';
            $params[] = $start;
        }
        if ($end !== '') {
            $where[] = 't.date <= ?';
            $params[] = $end;
        }
        if ($purpose !== '' && in_array($purpose, TREASURY_PURPOSES, true)) {
            $where[] = 't.purpose = ?';
            $params[] = $purpose;
        }
        $wSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $entries = Db::fetchAll(
            "SELECT t.*, u.address_term AS from_term, u.name AS from_name
             FROM treasury_entries t
             LEFT JOIN users u ON u.id = t.from_user_id
             $wSql
             ORDER BY t.date DESC, t.id DESC"
        , $params);

        $totals = [];
        foreach (TREASURY_PURPOSES as $p) {
            $totals[$p] = (float)(Db::value(
                "SELECT COALESCE(SUM(amount), 0) FROM treasury_entries WHERE purpose = ?" . ($where ? ' AND ' . implode(' AND ', $where) : ''),
                array_merge([$p], $params)
            ) ?? 0);
        }

        $users = Db::fetchAll('SELECT id, address_term, name FROM users WHERE is_active = 1 ORDER BY name');

        view('treasury', [
            'title' => 'Казна',
            'entries' => $entries,
            'totals' => $totals,
            'users' => $users,
            'filters' => ['from' => $start, 'to' => $end, 'purpose' => $purpose],
        ]);
    }

    public function save(): void
    {
        $date = input('date');
        $fromUserId = (int)input('from_user_id');
        $fromText = input('from_text');
        $purpose = input('purpose');
        $amount = (float)str_replace([',', ' '], ['.', ''], input('amount'));
        $note = input('note');

        if (!strtotime($date)) {
            set_flash('Укажите корректную дату поступления', 'danger');
            redirect(url('treasury'));
        }
        if (!in_array($purpose, TREASURY_PURPOSES, true)) {
            set_flash('Некорректное назначение платежа', 'danger');
            redirect(url('treasury'));
        }
        if ($amount <= 0) {
            set_flash('Укажите сумму поступления', 'danger');
            redirect(url('treasury'));
        }
        if ($fromUserId) {
            if (!Db::fetch('SELECT id FROM users WHERE id = ?', [$fromUserId])) {
                $fromUserId = 0;
            }
        }
        if (!$fromUserId && mb_strlen($fromText) < 2) {
            set_flash('Укажите, от кого поступление (или выберите из списка)', 'danger');
            redirect(url('treasury'));
        }

        Db::insert('treasury_entries', [
            'date' => $date,
            'from_user_id' => $fromUserId ?: null,
            'from_text' => $fromText,
            'purpose' => $purpose,
            'amount' => $amount,
            'note' => $note,
        ]);

        set_flash('Поступление записано');
        redirect(url('treasury'));
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if (!Db::fetch('SELECT id FROM treasury_entries WHERE id = ?', [$id])) {
            http_response_code(404);
            exit('Запись не найдена');
        }
        Db::delete('treasury_entries', ['id' => $id]);
        set_flash('Запись удалена', 'info');
        redirect(url('treasury'));
    }
}