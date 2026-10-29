<?php

class StatsController
{
    public function index(): void
    {
        $visits = Db::fetchAll(
            'SELECT v.*, u.address_term, u.name
             FROM visits v
             JOIN users u ON u.id = v.user_id
             ORDER BY v.id DESC
             LIMIT 50'
        );

        view('stats', [
            'title' => 'Статистика',
            'visits' => $visits,
        ]);
    }
}
