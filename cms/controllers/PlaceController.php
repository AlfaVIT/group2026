<?php

class PlaceController
{
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $place = $id ? Db::fetch('SELECT * FROM places WHERE id = ?', [$id]) : null;
        if ($id && !$place) {
            http_response_code(404);
            view('errors/404', ['title' => 'Место не найдено']);
            return;
        }

        $prev = $_SESSION['place_form'] ?? null;
        unset($_SESSION['place_form']);
        $data = $prev ?? $place ?? [
            'title' => '', 'owner_id' => '', 'region' => '', 'district' => '', 'locality' => '',
            'street' => '', 'house' => '', 'house_letter' => '', 'entrance' => '', 'floor' => '',
            'apartment' => '', 'intercom' => '', 'geo_link' => '', 'note' => '',
        ];

        $owners = Db::fetchAll("SELECT id, address_term, name FROM users WHERE is_active = 1 AND role != 'Гость' ORDER BY name");
        $photos = $place ? Db::fetchAll('SELECT * FROM place_photos WHERE place_id = ? ORDER BY sort, id', [$id]) : [];

        view('place_edit', [
            'title' => $place ? 'Редактирование места встречи' : 'Новое место встречи',
            'place' => $place,
            'data' => $data,
            'owners' => $owners,
            'photos' => $photos,
            'errors' => [],
        ]);
    }

    public function save(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $ownerId = (int)input('owner_id') ?: null;
        if ($ownerId && !Db::fetch('SELECT id FROM users WHERE id = ?', [$ownerId])) {
            $ownerId = null;
        }

        $data = [
            'title' => input('title'),
            'owner_id' => $ownerId,
            'region' => input('region'),
            'district' => input('district'),
            'locality' => input('locality'),
            'street' => input('street'),
            'house' => input('house'),
            'house_letter' => input('house_letter'),
            'entrance' => input('entrance'),
            'floor' => input('floor'),
            'apartment' => input('apartment'),
            'intercom' => input('intercom'),
            'geo_link' => input('geo_link'),
            'note' => input('note'),
        ];

        if (mb_strlen($data['title']) < 2) {
            $_SESSION['place_form'] = $data;
            set_flash('Укажите наименование места', 'danger');
            redirect(url('place_edit', ['id' => $id ?: null]));
        }

        if ($id && !Db::fetch('SELECT id FROM places WHERE id = ?', [$id])) {
            http_response_code(404);
            exit('Место не найдено');
        }

        if ($id) {
            Db::update('places', $data, ['id' => $id]);
            $placeId = $id;
        } else {
            $placeId = Db::insert('places', $data);
        }

        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            $sort = (int)(Db::value('SELECT COALESCE(MAX(sort), -1) FROM place_photos WHERE place_id = ?', [$placeId]) ?? -1) + 1;
            foreach ($_FILES['photos']['name'] as $i => $name) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $tmp = $_FILES['photos']['tmp_name'][$i];
                $info = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($info, $tmp);
                finfo_close($info);
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mime, $allowed, true)) {
                    continue;
                }
                if ($_FILES['photos']['size'][$i] > 10 * 1024 * 1024) {
                    continue;
                }
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
                $filename = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($tmp, UPLOAD_DIR . '/' . $filename)) {
                    Db::insert('place_photos', [
                        'place_id' => $placeId,
                        'file_path' => $filename,
                        'sort' => $sort++,
                    ]);
                }
            }
        }

        set_flash('Место встречи сохранено');
        redirect(url('place_edit', ['id' => $placeId]));
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $place = Db::fetch('SELECT * FROM places WHERE id = ?', [$id]);
        if (!$place) {
            http_response_code(404);
            exit('Место не найдено');
        }
        foreach (Db::fetchAll('SELECT file_path FROM place_photos WHERE place_id = ?', [$id]) as $photo) {
            $file = UPLOAD_DIR . '/' . $photo['file_path'];
            if (is_file($file)) {
                @unlink($file);
            }
        }
        Db::delete('places', ['id' => $id]);
        set_flash('Место встречи удалено', 'info');
        redirect(url('settings'));
    }

    public function deletePhoto(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $photo = Db::fetch('SELECT * FROM place_photos WHERE id = ?', [$id]);
        if (!$photo) {
            http_response_code(404);
            exit('Фотография не найдена');
        }
        $file = UPLOAD_DIR . '/' . $photo['file_path'];
        if (is_file($file)) {
            @unlink($file);
        }
        Db::delete('place_photos', ['id' => $id]);
        set_flash('Фотография удалена', 'info');
        redirect(url('place_edit', ['id' => $photo['place_id']]));
    }
}