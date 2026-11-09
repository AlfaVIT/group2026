<?php

class ArticleController
{
    public function index(): void
    {
        $articles = Article::all();
        $usage = [];
        foreach ($articles as $a) {
            $usage[(int)$a['id']] = Article::usage($a);
        }

        view('articles', [
            'title' => 'Статьи казны',
            'kindLabels' => Article::KINDS,
            'articles' => $articles,
            'usage' => $usage,
            'pairs' => Article::similarPairs(),
            'suggestUrl' => url('article_suggest'),
        ]);
    }

    public function save(): void
    {
        $kind = input('kind', 'income');
        $error = Article::create($kind, input('name'));
        set_flash($error ?? 'Статья добавлена в справочник', $error ? 'danger' : 'success');
        redirect(url('articles'));
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $do = input('do', 'rename');
        $error = null;
        $done = 'Статья переименована';
        if ($do === 'archive') {
            $error = Article::setArchived($id, true);
            $done = 'Статья скрыта из списков (в отчётах остаётся)';
        } elseif ($do === 'restore') {
            $error = Article::setArchived($id, false);
            $done = 'Статья снова доступна для выбора';
        } elseif ($do === 'delete') {
            $error = Article::delete($id);
            $done = 'Статья удалена';
        } else {
            $error = Article::rename($id, input('name'));
        }
        set_flash($error ?? $done, $error ? 'danger' : 'success');
        redirect(url('articles'));
    }

    public function merge(): void
    {
        $from = (int)($_POST['from'] ?? 0);
        $to = (int)($_POST['to'] ?? 0);
        $error = Article::merge($from, $to);
        set_flash($error ?? 'Статьи объединены', $error ? 'danger' : 'success');
        redirect(url('articles'));
    }

    public function dismiss(): void
    {
        $kind = input('kind');
        $keyA = input('key_a');
        $keyB = input('key_b');
        if (Article::isValidKind($kind) && $keyA !== '' && $keyB !== '') {
            Article::dismissPair($kind, $keyA, $keyB);
            set_flash('Пара помечена как «разные статьи» и больше не предлагается', 'info');
        }
        redirect(url('articles'));
    }

    /** JSON-подсказки по похожести для поля ввода статьи. */
    public function suggest(): void
    {
        $kind = (string)($_GET['kind'] ?? 'income');
        if (!Article::isValidKind($kind)) {
            $kind = 'income';
        }
        $items = Article::suggest($kind, (string)($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
