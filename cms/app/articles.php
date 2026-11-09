<?php

/**
 * Справочник статей казны.
 *
 * Статьи живут в отдельной таблице (kind + key + name), а в самих записях казны
 * остаётся текст названия: отчёты и выгрузки продолжают работать как есть,
 * а переименование и объединение просто переписывают этот текст.
 *
 * key — нормализованное название (article_key), поэтому «Трапеза» и «трапеза»
 * не могут существовать как две разные статьи.
 */
class Article
{
    public const KINDS = ['income' => 'Поступление', 'expense' => 'Расход'];

    /** Таблица и колонка, где хранятся названия статей данного вида. */
    private static function storage(string $kind): array
    {
        return $kind === 'expense'
            ? ['treasury_expenses', 'type']
            : ['treasury_entries', 'purpose'];
    }

    public static function isValidKind(string $kind): bool
    {
        return isset(self::KINDS[$kind]);
    }

    /** @return array<int, array> */
    public static function all(?string $kind = null, bool $withArchived = true): array
    {
        $where = [];
        $params = [];
        if ($kind !== null) {
            $where[] = 'kind = ?';
            $params[] = $kind;
        }
        if (!$withArchived) {
            $where[] = 'archived = 0';
        }
        $sql = 'SELECT * FROM treasury_articles'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $rows = Db::fetchAll($sql, $params);
        usort($rows, fn($a, $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));
        return $rows;
    }

    /** Названия активных статей для выпадающих списков. */
    public static function names(string $kind): array
    {
        return array_values(array_map(static fn($a) => $a['name'], self::all($kind, false)));
    }

    public static function find(int $id): ?array
    {
        return Db::fetch('SELECT * FROM treasury_articles WHERE id = ?', [$id]);
    }

    /**
     * Находит статью по названию (без учёта регистра и пробелов) или создаёт новую.
     * Возвращает каноническое название, которое нужно записать в казну.
     */
    public static function resolve(string $kind, string $name): string
    {
        $name = article_canonical($name);
        if ($name === '') {
            return '';
        }
        $key = article_key($name);
        $row = Db::fetch('SELECT * FROM treasury_articles WHERE kind = ? AND key = ?', [$kind, $key]);
        if ($row) {
            return $row['name'];
        }
        try {
            Db::insert('treasury_articles', ['kind' => $kind, 'key' => $key, 'name' => $name]);
        } catch (Throwable $e) {
            // гонка: статью уже создал параллельный запрос
            $row = Db::fetch('SELECT name FROM treasury_articles WHERE kind = ? AND key = ?', [$kind, $key]);
            return $row ? $row['name'] : $name;
        }
        return $name;
    }

    /** Создание статьи из интерфейса. Возвращает текст ошибки или null. */
    public static function create(string $kind, string $name): ?string
    {
        if (!self::isValidKind($kind)) {
            return 'Неизвестный вид статьи';
        }
        $name = article_canonical($name);
        if (mb_strlen($name) < 2) {
            return 'Название статьи — не короче 2 символов';
        }
        if (mb_strlen($name) > 100) {
            return 'Название статьи — не длиннее 100 символов';
        }
        $key = article_key($name);
        if (Db::fetch('SELECT id FROM treasury_articles WHERE kind = ? AND key = ?', [$kind, $key])) {
            return 'Такая статья уже есть в справочнике';
        }
        Db::insert('treasury_articles', ['kind' => $kind, 'key' => $key, 'name' => $name]);
        return null;
    }

    /** Переименование статьи с переносом всех записей. */
    public static function rename(int $id, string $name): ?string
    {
        $row = self::find($id);
        if (!$row) {
            return 'Статья не найдена';
        }
        $name = article_canonical($name);
        if (mb_strlen($name) < 2) {
            return 'Название статьи — не короче 2 символов';
        }
        $key = article_key($name);
        if (Db::fetch('SELECT id FROM treasury_articles WHERE kind = ? AND key = ? AND id != ?', [$row['kind'], $key, $id])) {
            return 'Статья с таким названием уже есть — объедините их, а не переименовывайте';
        }
        if ($name === $row['name']) {
            return null;
        }
        [$table, $column] = self::storage($row['kind']);
        foreach (Db::fetchAll("SELECT DISTINCT $column AS value FROM $table") as $v) {
            if (article_key((string)$v['value']) === $row['key']) {
                Db::q("UPDATE $table SET $column = ? WHERE $column = ?", [$name, (string)$v['value']]);
            }
        }
        Db::update('treasury_articles', ['name' => $name, 'key' => $key, 'archived' => 0], ['id' => $id]);
        return null;
    }

    public static function setArchived(int $id, bool $archived): ?string
    {
        $row = self::find($id);
        if (!$row) {
            return 'Статья не найдена';
        }
        Db::update('treasury_articles', ['archived' => $archived ? 1 : 0], ['id' => $id]);
        return null;
    }

    /** Удалить можно только статью без записей. */
    public static function delete(int $id): ?string
    {
        $row = self::find($id);
        if (!$row) {
            return 'Статья не найдена';
        }
        $usage = self::usage($row);
        if ($usage['count'] > 0) {
            return 'У статьи есть записи — объедините её с другой или скройте из списка';
        }
        Db::delete('treasury_articles', ['id' => $id]);
        Db::q(
            'DELETE FROM treasury_article_pairs WHERE kind = ? AND (key_a = ? OR key_b = ?)',
            [$row['kind'], $row['key'], $row['key']]
        );
        return null;
    }

    /** Объединение статей: все записи переписываются на название целевой. */
    public static function merge(int $fromId, int $toId): ?string
    {
        $from = self::find($fromId);
        $to = self::find($toId);
        if (!$from || !$to) {
            return 'Статья не найдена';
        }
        if ((int)$from['id'] === (int)$to['id'] || $from['kind'] !== $to['kind']) {
            return 'Объединять можно только две разные статьи одного вида';
        }
        [$table, $column] = self::storage($from['kind']);
        foreach (Db::fetchAll("SELECT DISTINCT $column AS value FROM $table") as $v) {
            if (article_key((string)$v['value']) === $from['key']) {
                Db::q("UPDATE $table SET $column = ? WHERE $column = ?", [$to['name'], (string)$v['value']]);
            }
        }
        Db::delete('treasury_articles', ['id' => $fromId]);
        Db::q(
            'DELETE FROM treasury_article_pairs WHERE kind = ? AND (key_a = ? OR key_b = ?)',
            [$from['kind'], $from['key'], $from['key']]
        );
        return null;
    }

    /** Сколько записей и на какую сумму ссылается статья. */
    public static function usage(array $article): array
    {
        [$table, $column] = self::storage($article['kind']);
        $count = 0;
        $sum = 0.0;
        foreach (Db::fetchAll(
            "SELECT $column AS value, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
             FROM $table GROUP BY $column"
        ) as $r) {
            if (article_key((string)$r['value']) === $article['key']) {
                $count += (int)$r['cnt'];
                $sum += (float)$r['total'];
            }
        }
        return ['count' => $count, 'sum' => $sum];
    }

    /** Подсказки по похожести для поля ввода. */
    public static function suggest(string $kind, string $needle, int $limit = 5): array
    {
        $needle = trim($needle);
        if (mb_strlen($needle) < 2) {
            return [];
        }
        $out = [];
        foreach (self::all($kind, false) as $a) {
            $score = self::score($needle, $a['name']);
            if ($score >= 55) {
                $out[] = ['id' => (int)$a['id'], 'name' => $a['name'], 'score' => $score];
            }
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, $limit);
    }

    /**
     * Пары статей, похожих по написанию (для ручного объединения).
     * Отклонённые пользователем пары не показываются.
     */
    public static function similarPairs(int $minScore = 80): array
    {
        $out = [];
        foreach (array_keys(self::KINDS) as $kind) {
            $list = self::all($kind, false);
            $n = count($list);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $score = self::score($list[$i]['name'], $list[$j]['name']);
                    if ($score < $minScore) {
                        continue;
                    }
                    if (self::isPairDismissed($kind, $list[$i]['key'], $list[$j]['key'])) {
                        continue;
                    }
                    $out[] = [
                        'kind' => $kind,
                        'score' => $score,
                        'a' => $list[$i] + ['usage' => self::usage($list[$i])],
                        'b' => $list[$j] + ['usage' => self::usage($list[$j])],
                    ];
                }
            }
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return $out;
    }

    /** «Это разные статьи» — больше не предлагать к объединению. */
    public static function dismissPair(string $kind, string $keyA, string $keyB): void
    {
        if ($keyA === $keyB) {
            return;
        }
        [$a, $b] = strcmp($keyA, $keyB) < 0 ? [$keyA, $keyB] : [$keyB, $keyA];
        Db::q(
            'INSERT OR IGNORE INTO treasury_article_pairs (kind, key_a, key_b) VALUES (?, ?, ?)',
            [$kind, $a, $b]
        );
    }

    private static function isPairDismissed(string $kind, string $keyA, string $keyB): bool
    {
        [$a, $b] = strcmp($keyA, $keyB) < 0 ? [$keyA, $keyB] : [$keyB, $keyA];
        return (bool)Db::value(
            'SELECT 1 FROM treasury_article_pairs WHERE kind = ? AND key_a = ? AND key_b = ?',
            [$kind, $a, $b]
        );
    }

    /** Похожесть двух названий: 0..100, с учётом кириллицы. */
    public static function score(string $a, string $b): int
    {
        $ka = article_key($a);
        $kb = article_key($b);
        if ($ka === '' || $kb === '') {
            return 0;
        }
        if ($ka === $kb) {
            return 100;
        }
        $score = self::levenshteinPercent($ka, $kb);
        // «Трапеза» и «Трапеза на агапу» — один корень, подсказываем к объединению
        $short = mb_strlen($ka) <= mb_strlen($kb) ? $ka : $kb;
        $long = mb_strlen($ka) <= mb_strlen($kb) ? $kb : $ka;
        if (mb_strlen($short) >= 4 && str_starts_with($long, $short)) {
            $score = max($score, 80);
        }
        return $score;
    }

    /** Посимвольное расстояние Левенштейна (strlen/levenshtein работают с байтами). */
    private static function levenshteinPercent(string $a, string $b): int
    {
        $left = mb_str_split($a);
        $right = mb_str_split($b);
        $la = count($left);
        $lb = count($right);
        if ($la === 0 || $lb === 0) {
            return 0;
        }
        $prev = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $left[$i - 1] === $right[$j - 1] ? 0 : 1;
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $cur;
        }
        $distance = $prev[$lb];
        return (int)round((1 - $distance / max($la, $lb)) * 100);
    }
}
