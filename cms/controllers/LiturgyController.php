<?php

/**
 * Страница «Литургия» для встречи.
 *
 * Текст чина показывается из приложения molitva.app (как и фолбэк вечерни для
 * особых периодов): приложение само выбирает вид Литургии по дате — Златоуста,
 * Василия Великого или Преждеосвященные Дары. Назначения чтецов здесь нет.
 */
class LiturgyController
{
    public function view(): void
    {
        $meetingId = (int)($_GET['meeting_id'] ?? 0);
        $meeting = $meetingId
            ? Db::fetch('SELECT id, date, topic, tag FROM meetings WHERE id = ?', [$meetingId])
            : null;

        if (!$meeting) {
            http_response_code(404);
            view('errors/404', ['title' => 'Встреча не найдена']);
            return;
        }

        $frameDate = date('Y-m-d', strtotime($meeting['date']));
        $frameUrl = 'https://molitva.app/#/date/' . $frameDate . '/service/' . rawurlencode('Литургия');

        view('liturgy', [
            'title' => 'Литургия',
            'meeting' => $meeting,
            'frameDate' => $frameDate,
            'frameUrl' => $frameUrl,
        ]);
    }
}
