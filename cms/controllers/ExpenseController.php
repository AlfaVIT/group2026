<?php

class ExpenseController
{
    public function add(): void
    {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        $amount = (float)str_replace([',', ' '], ['.', ''], input('amount'));
        $description = input('description');

        $meeting = Db::fetch('SELECT id, meeting_type FROM meetings WHERE id = ?', [$meetingId]);
        if (!$meeting) {
            http_response_code(404);
            exit('Встреча не найдена');
        }
        if ($amount <= 0) {
            set_flash('Укажите сумму расходов', 'danger');
            redirect(url('meeting', ['id' => $meetingId]));
        }

        Db::insert('expenses', [
            'meeting_id' => $meetingId,
            'user_id' => Auth::id(),
            'amount' => $amount,
            'description' => $description,
            'article' => expense_article_for_meeting_type((string)$meeting['meeting_type']),
        ]);

        set_flash('Расход записан');
        redirect(url('meeting', ['id' => $meetingId]));
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $expense = Db::fetch('SELECT * FROM expenses WHERE id = ?', [$id]);
        if (!$expense) {
            http_response_code(404);
            exit('Расход не найден');
        }
        if ((int)$expense['user_id'] !== Auth::id() && !Auth::can('view_expenses')) {
            http_response_code(403);
            exit('Доступ запрещён');
        }
        Db::delete('expenses', ['id' => $id]);
        set_flash('Расход удалён', 'info');
        redirect(url('meeting', ['id' => $expense['meeting_id']]));
    }
}