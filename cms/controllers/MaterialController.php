<?php

class MaterialController
{
    private const PER_PAGE = 10;

    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $tag = trim((string)($_GET['tag'] ?? ''));
        $author = (int)($_GET['author'] ?? 0);
        $sortKey = 'date';
        $order = 'desc';
        if (preg_match('/^(date|title)_(asc|desc)$/', (string)($_GET['sort'] ?? ''), $m)) {
            $sortKey = $m[1];
            $order = $m[2];
        }
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = [];
        $params = [];
        if ($tag !== '') {
            $where[] = 'm.tag = ?';
            $params[] = $tag;
        }
        if ($author > 0) {
            $where[] = 'm.user_id = ?';
            $params[] = $author;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $materials = Db::fetchAll(
            "SELECT m.*, u.address_term AS author_term, u.name AS author_name,
                    u2.address_term AS editor_term, u2.name AS editor_name
             FROM materials m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN users u2 ON u2.id = m.updated_by
             $whereSql",
            $params
        );

        if ($q !== '') {
            $materials = array_values(array_filter($materials, function (array $m) use ($q): bool {
                return mb_stripos($m['title'], $q) !== false
                    || mb_stripos($m['description'], $q) !== false
                    || mb_stripos($m['content'], $q) !== false;
            }));
        }

        if ($sortKey === 'title') {
            usort($materials, fn(array $a, array $b) => strcmp(mb_strtolower($a['title']), mb_strtolower($b['title'])));
        } else {
            usort($materials, fn(array $a, array $b) => [$a['created_at'], $a['id']] <=> [$b['created_at'], $b['id']]);
        }
        if ($order === 'desc') {
            $materials = array_reverse($materials);
        }

        $total = count($materials);
        $totalPages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $materials = array_slice($materials, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $tags = array_column(Db::fetchAll("SELECT DISTINCT tag FROM materials WHERE tag != '' ORDER BY tag"), 'tag');
        $authors = Db::fetchAll(
            'SELECT DISTINCT u.id, u.address_term, u.name
             FROM materials m JOIN users u ON u.id = m.user_id ORDER BY u.name'
        );

        $editMaterial = null;
        $edit = (int)($_GET['edit'] ?? 0);
        if ($edit) {
            $editMaterial = Db::fetch('SELECT * FROM materials WHERE id = ?', [$edit]);
        }

        view('materials', [
            'title' => 'Материалы',
            'materials' => $materials,
            'total' => $total,
            'pageNum' => $page,
            'totalPages' => $totalPages,
            'tags' => $tags,
            'authors' => $authors,
            'q' => $q,
            'tag' => $tag,
            'author' => $author,
            'sortKey' => $sortKey,
            'order' => $order,
            'editMaterial' => $editMaterial,
            'formData' => $_SESSION['material_form'] ?? null,
        ]);
        unset($_SESSION['material_form']);
    }

    public function save(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $existing = $id ? Db::fetch('SELECT * FROM materials WHERE id = ?', [$id]) : null;
        if ($id && !$existing) {
            http_response_code(404);
            exit('Материал не найден');
        }

        $title = input('title');
        $description = input('description');
        $content = input('content');
        $tag = mb_substr(input('tag'), 0, 60);

        $errors = [];
        if ($title === '') {
            $errors[] = 'Укажите название материала';
        }
        $imagePath = $existing['image_path'] ?? '';
        if (!empty($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $mime = material_image_mime($_FILES['image']['tmp_name']);
            if ($mime === null) {
                $errors[] = 'Файл не является изображением (допустимы JPG, PNG, GIF, WebP)';
            } elseif ($_FILES['image']['size'] > 10 * 1024 * 1024) {
                $errors[] = 'Изображение не должно быть больше 10 МБ';
            }
        }

        if ($errors) {
            $_SESSION['material_form'] = [
                'title' => $title, 'description' => $description, 'content' => $content, 'tag' => $tag,
            ];
            set_flash(implode('<br>', $errors), 'danger');
            redirect(url('materials', $id ? ['edit' => $id] : []));
        }

        if (!empty($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $mime = material_image_mime($_FILES['image']['tmp_name']);
            if ($mime !== null) {
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
                $filename = 'mat-' . date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
                    if ($imagePath !== '' && $imagePath !== $filename && is_file(UPLOAD_DIR . '/' . $imagePath)) {
                        @unlink(UPLOAD_DIR . '/' . $imagePath);
                    }
                    $imagePath = $filename;
                }
            }
        }

        $data = [
            'title' => $title,
            'description' => $description,
            'content' => $content,
            'tag' => $tag,
            'image_path' => $imagePath,
        ];

        if ($id) {
            $data['updated_by'] = Auth::id();
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::update('materials', $data, ['id' => $id]);
            set_flash('Материал обновлён');
        } else {
            $data['user_id'] = Auth::id();
            $data['created_at'] = date('Y-m-d H:i:s');
            Db::insert('materials', $data);
            set_flash('Материал размещён');
        }
        redirect(url('materials'));
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $material = Db::fetch('SELECT * FROM materials WHERE id = ?', [$id]);
        if (!$material) {
            http_response_code(404);
            exit('Материал не найден');
        }
        if (!Auth::can('manage_materials') && (int)$material['user_id'] !== Auth::id()) {
            http_response_code(403);
            view('errors/403', ['title' => 'Доступ запрещён']);
            exit;
        }
        if ($material['image_path'] !== '' && is_file(UPLOAD_DIR . '/' . $material['image_path'])) {
            @unlink(UPLOAD_DIR . '/' . $material['image_path']);
        }
        Db::delete('materials', ['id' => $id]);
        set_flash('Материал удалён', 'info');
        redirect(url('materials'));
    }
}

function material_image_mime(string $path): ?string
{
    $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) ? $mime : null;
}