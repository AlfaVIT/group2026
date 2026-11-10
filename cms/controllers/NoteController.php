<?php

/**
 * Конспекты встреч: текст (в т.ч. ссылки), изображения и аудиозаписи.
 *
 * Заметка одна на участника и встречу. Видимость: «личная» (по умолчанию) —
 * видит только автор, «для всех» — видят все авторизованные участники.
 * Файлы лежат в data/notes (каталог закрыт от HTTP), отдаются через file().
 */
class NoteController
{
    private const MAX_IMAGE = 10 * 1024 * 1024;
    private const MAX_AUDIO = 25 * 1024 * 1024;
    private const MAX_FILES = 5;

    /** mime => [kind, extension] */
    private const TYPES = [
        'image/jpeg' => ['image', 'jpg'],
        'image/png'  => ['image', 'png'],
        'image/gif'  => ['image', 'gif'],
        'image/webp' => ['image', 'webp'],
        'audio/webm' => ['audio', 'webm'],
        'video/webm' => ['audio', 'webm'], // MediaRecorder в Chrome отдаёт video/webm
        'audio/ogg'  => ['audio', 'ogg'],
        'audio/mpeg' => ['audio', 'mp3'],
        'audio/mp4'  => ['audio', 'm4a'],
        'audio/x-m4a' => ['audio', 'm4a'],
        'audio/wav'  => ['audio', 'wav'],
        'audio/x-wav' => ['audio', 'wav'],
    ];

    private const MIME_BY_EXT = [
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'webm' => 'audio/webm',
        'ogg'  => 'audio/ogg',
        'mp3'  => 'audio/mpeg',
        'm4a'  => 'audio/mp4',
        'wav'  => 'audio/wav',
    ];

    private static function dir(): string
    {
        $dir = BASE_PATH . '/data/notes';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function save(): void
    {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        if (!Db::fetch('SELECT id FROM meetings WHERE id = ?', [$meetingId])) {
            http_response_code(404);
            exit('Встреча не найдена');
        }
        $userId = (int)Auth::id();
        $back = url('meeting', ['id' => $meetingId]) . '#note';

        $body = trim((string)($_POST['body'] ?? ''));
        $visibility = (string)($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private';

        $errors = [];
        if (mb_strlen($body) > 20000) {
            $errors[] = 'Текст конспекта — не длиннее 20 000 символов';
        }

        $note = Db::fetch('SELECT * FROM meeting_notes WHERE meeting_id = ? AND user_id = ?', [$meetingId, $userId]);
        $noteId = $note ? (int)$note['id'] : 0;
        $existing = $noteId
            ? (int)Db::value('SELECT COUNT(*) FROM meeting_note_files WHERE note_id = ?', [$noteId])
            : 0;

        $uploads = $this->collectUploads($errors);
        if ($existing + count($uploads) > self::MAX_FILES) {
            $errors[] = 'К конспекту можно приложить не больше ' . self::MAX_FILES . ' файлов';
            $uploads = [];
        }
        if ($body === '' && $existing === 0 && !$uploads) {
            $errors[] = 'Добавьте текст, изображение или запись звука';
        }

        if ($errors) {
            set_flash(implode('<br>', $errors), 'danger');
            redirect($back);
        }

        $now = date('Y-m-d H:i:s');
        if ($noteId) {
            Db::update('meeting_notes', [
                'body' => $body,
                'visibility' => $visibility,
                'updated_at' => $now,
            ], ['id' => $noteId]);
        } else {
            $noteId = Db::insert('meeting_notes', [
                'meeting_id' => $meetingId,
                'user_id' => $userId,
                'body' => $body,
                'visibility' => $visibility,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($uploads as $file) {
            $name = 'note-' . date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $file['ext'];
            if (@move_uploaded_file($file['tmp'], self::dir() . '/' . $name)) {
                Db::insert('meeting_note_files', [
                    'note_id' => $noteId,
                    'kind' => $file['kind'],
                    'file_path' => $name,
                    'created_at' => $now,
                ]);
            }
        }

        set_flash($visibility === 'public' ? 'Конспект сохранён и доступен участникам' : 'Личный конспект сохранён');
        redirect($back);
    }

    public function delete(): void
    {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        $note = Db::fetch(
            'SELECT * FROM meeting_notes WHERE meeting_id = ? AND user_id = ?',
            [$meetingId, (int)Auth::id()]
        );
        if ($note) {
            foreach (Db::fetchAll('SELECT file_path FROM meeting_note_files WHERE note_id = ?', [(int)$note['id']]) as $f) {
                $path = self::dir() . '/' . $f['file_path'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            Db::delete('meeting_notes', ['id' => (int)$note['id']]);
            set_flash('Конспект удалён', 'info');
        }
        redirect(url('meeting', ['id' => $meetingId]) . '#note');
    }

    public function fileDelete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $back = url('home');
        $file = Db::fetch(
            'SELECT f.*, n.user_id, n.meeting_id FROM meeting_note_files f
             JOIN meeting_notes n ON n.id = f.note_id WHERE f.id = ?',
            [$id]
        );
        if (!$file) {
            http_response_code(404);
            exit('Файл не найден');
        }
        $back = url('meeting', ['id' => (int)$file['meeting_id']]) . '#note';
        if ((int)$file['user_id'] !== (int)Auth::id()) {
            http_response_code(403);
            exit('Доступ запрещён');
        }
        $path = self::dir() . '/' . $file['file_path'];
        if (is_file($path)) {
            @unlink($path);
        }
        Db::delete('meeting_note_files', ['id' => $id]);
        set_flash('Файл удалён', 'info');
        redirect($back);
    }

    /** Отдача файла конспекта с проверкой доступа (личный конспект — только автору). */
    public function file(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $file = Db::fetch(
            'SELECT f.*, n.user_id, n.visibility FROM meeting_note_files f
             JOIN meeting_notes n ON n.id = f.note_id WHERE f.id = ?',
            [$id]
        );
        if (!$file) {
            http_response_code(404);
            exit('Файл не найден');
        }
        $isOwner = (int)$file['user_id'] === (int)Auth::id();
        if ($file['visibility'] !== 'public' && !$isOwner) {
            http_response_code(403);
            exit('Доступ запрещён');
        }
        $path = self::dir() . '/' . $file['file_path'];
        if (!is_file($path)) {
            http_response_code(404);
            exit('Файл не найден');
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIME_BY_EXT[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . $ext . '"');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** Проверка загруженных файлов (поля files[] и audio) до записи в БД. */
    private function collectUploads(array &$errors): array
    {
        $out = [];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        foreach (['files', 'audio'] as $field) {
            if (empty($_FILES[$field]) || !isset($_FILES[$field]['name'])) {
                continue;
            }
            $names = (array)$_FILES[$field]['name'];
            $tmps = (array)$_FILES[$field]['tmp_name'];
            $sizes = (array)$_FILES[$field]['size'];
            $errs = (array)$_FILES[$field]['error'];
            foreach ($names as $i => $_) {
                $err = $errs[$i] ?? UPLOAD_ERR_NO_FILE;
                if ($err === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                    $errors[] = 'Файл больше допустимого размера на сервере';
                    continue;
                }
                if ($err !== UPLOAD_ERR_OK) {
                    $errors[] = 'Не удалось загрузить файл (код ' . (int)$err . ')';
                    continue;
                }
                $tmp = (string)($tmps[$i] ?? '');
                $size = (int)($sizes[$i] ?? 0);
                $mime = (string)finfo_file($finfo, $tmp);
                if (!isset(self::TYPES[$mime])) {
                    $errors[] = 'Неподдерживаемый тип файла: ' . e($mime);
                    continue;
                }
                [$kind, $ext] = self::TYPES[$mime];
                $max = $kind === 'image' ? self::MAX_IMAGE : self::MAX_AUDIO;
                if ($size > $max) {
                    $errors[] = 'Файл больше ' . human_size($max);
                    continue;
                }
                $out[] = ['tmp' => $tmp, 'kind' => $kind, 'ext' => $ext];
            }
        }
        return $out;
    }
}
