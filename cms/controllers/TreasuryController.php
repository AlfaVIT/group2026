<?php

class TreasuryController
{
    public function index(): void
    {
        // По умолчанию фильтры периода — текущий месяц
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $start = $_GET['from'] ?? $monthStart;
        $end = $_GET['to'] ?? $monthEnd;
        $purposeFilter = $_GET['purpose'] ?? '';
        $expStart = $_GET['exp_from'] ?? $monthStart;
        $expEnd = $_GET['exp_to'] ?? $monthEnd;
        $expType = $_GET['exp_type'] ?? '';

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
        if ($purposeFilter !== '') {
            $where[] = 't.purpose = ?';
            $params[] = $purposeFilter;
        }
        $wSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $entries = Db::fetchAll(
            "SELECT t.*, u.address_term AS from_term, u.name AS from_name
             FROM treasury_entries t
             LEFT JOIN users u ON u.id = t.from_user_id
             $wSql
             ORDER BY t.date DESC, t.id DESC"
        , $params);

        $incomeByArticle = [];
        foreach (Db::fetchAll("SELECT purpose AS article, SUM(amount) AS total FROM treasury_entries GROUP BY purpose") as $r) {
            if ($r['article'] !== '') {
                $incomeByArticle[$r['article']] = (float)$r['total'];
            }
        }
        $expenseByArticle = [];
        foreach (Db::fetchAll("SELECT type AS article, SUM(amount) AS total FROM treasury_expenses GROUP BY type") as $r) {
            if ($r['article'] !== '') {
                $expenseByArticle[$r['article']] = (float)$r['total'];
            }
        }

        $articles = array_values(array_unique(array_merge(array_keys($incomeByArticle), array_keys($expenseByArticle))));
        usort($articles, fn($a, $b) => mb_strtolower($a) <=> mb_strtolower($b));

        $articleStats = [];
        foreach ($articles as $a) {
            $income = $incomeByArticle[$a] ?? 0.0;
            $expense = $expenseByArticle[$a] ?? 0.0;
            $articleStats[$a] = [
                'income' => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
            ];
        }
        $incomeTotal = (float)array_sum(array_column($articleStats, 'income'));
        $expenseTotal = (float)array_sum(array_column($articleStats, 'expense'));
        $balanceTotal = $incomeTotal - $expenseTotal;

        $expWhere = [];
        $expParams = [];
        if ($expStart !== '') {
            $expWhere[] = 'date >= ?';
            $expParams[] = $expStart;
        }
        if ($expEnd !== '') {
            $expWhere[] = 'date <= ?';
            $expParams[] = $expEnd;
        }
        if ($expType !== '') {
            $expWhere[] = 'type = ?';
            $expParams[] = $expType;
        }
        $expW = $expWhere ? 'WHERE ' . implode(' AND ', $expWhere) : '';

        $expenses = Db::fetchAll(
            "SELECT treasury_expenses.*, u.address_term AS to_term, u.name AS to_name
             FROM treasury_expenses
             LEFT JOIN users u ON u.id = treasury_expenses.to_user_id
             $expW ORDER BY date DESC, id DESC",
            $expParams
        );
        $expensesTotal = (float)(Db::value("SELECT COALESCE(SUM(amount), 0) FROM treasury_expenses $expW", $expParams) ?? 0);
        $incomeEntriesTotal = (float)(Db::value("SELECT COALESCE(SUM(t.amount), 0) FROM treasury_entries t $wSql", $params) ?? 0);

        $meetingExpenses = [];
        foreach (Db::fetchAll(
            "SELECT e.*, m.date AS m_date, m.topic AS m_topic, u.address_term AS u_term, u.name AS u_name
             FROM expenses e
             JOIN meetings m ON m.id = e.meeting_id
             LEFT JOIN users u ON u.id = e.user_id
             ORDER BY m.date DESC, e.user_id, e.created_at DESC, e.id DESC"
        ) as $e) {
            $mid = (int)$e['meeting_id'];
            if (!isset($meetingExpenses[$mid])) {
                $meetingExpenses[$mid] = [
                    'date' => $e['m_date'],
                    'topic' => $e['m_topic'],
                    'total' => 0.0,
                    'users' => [],
                ];
            }
            $meetingExpenses[$mid]['total'] += (float)$e['amount'];
            $uid = $e['user_id'] ? (int)$e['user_id'] : 0;
            if (!isset($meetingExpenses[$mid]['users'][$uid])) {
                $meetingExpenses[$mid]['users'][$uid] = [
                    'name' => $e['user_id'] ? ($e['u_term'] . ' ' . $e['u_name']) : 'Без указания',
                    'total' => 0.0,
                    'items' => [],
                ];
            }
            $meetingExpenses[$mid]['users'][$uid]['total'] += (float)$e['amount'];
            $meetingExpenses[$mid]['users'][$uid]['items'][] = $e;
        }

        $purposes = array_values(array_unique(array_column(
            Db::fetchAll("SELECT DISTINCT purpose FROM treasury_entries WHERE purpose != '' ORDER BY purpose"),
            'purpose'
        )));

        // Статьи расходов: три предустановленные + ранее использованные
        $usedExpenseTypes = array_column(
            Db::fetchAll("SELECT DISTINCT type FROM treasury_expenses WHERE type != ''"),
            'type'
        );
        $expenseArticles = array_values(array_unique(array_merge(EXPENSE_ARTICLES, $usedExpenseTypes)));

        $chart = $this->buildChart();

        $users = Db::fetchAll('SELECT id, address_term, name FROM users WHERE is_active = 1 ORDER BY name');

        view('treasury', [
            'title' => 'Казна',
            'entries' => $entries,
            'articleStats' => $articleStats,
            'incomeTotal' => $incomeTotal,
            'incomeEntriesTotal' => $incomeEntriesTotal,
            'expenseTotal' => $expenseTotal,
            'balanceTotal' => $balanceTotal,
            'expenses' => $expenses,
            'expensesTotal' => $expensesTotal,
            'meetingExpenses' => $meetingExpenses,
            'chart' => $chart,
            'users' => $users,
            'purposes' => $purposes,
            'expenseArticles' => $expenseArticles,
            'filters' => ['from' => $start, 'to' => $end, 'purpose' => $purposeFilter],
            'expFilters' => ['from' => $expStart, 'to' => $expEnd, 'type' => $expType],
        ]);
    }

    /**
     * Консолидированный отчёт: участники (строки) × статьи (колонки).
     * На пересечении — взнос участника по статье; для статей с расходами на
     * участников добавляются колонки «Возмещено» и «Остаток» (взнос − возмещено).
     * Вид отчёта: кассовый (срез по полю «Дата») или бюджетный (по полю «Период»).
     */
    public function report(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-t');
        $mode = ($_GET['mode'] ?? 'cash') === 'budget' ? 'budget' : 'cash';
        $data = $this->reportData($from, $to, $mode);

        view('treasury_report', array_merge([
            'title' => 'Консолидированный отчёт',
            'from' => $from,
            'to' => $to,
            'mode' => $mode,
        ], $data));
    }

    /** Экспорт консолидированного отчёта в Excel (.xlsx, при отсутствии zip — CSV). */
    public function reportExport(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-t');
        $mode = ($_GET['mode'] ?? 'cash') === 'budget' ? 'budget' : 'cash';
        $d = $this->reportData($from, $to, $mode);

        if (class_exists('ZipArchive')) {
            $bin = $this->reportXlsx($d);
            if ($bin !== '') {
                $filename = 'konsolidirovanniy_otchet_' . $from . '_' . $to . '.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($bin));
                header('Cache-Control: no-store');
                echo $bin;
                exit;
            }
        }

        $csv = $this->reportCsv($d);
        $filename = 'konsolidirovanniy_otchet_' . $from . '_' . $to . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo $csv;
        exit;
    }

    /** Формирует xlsx (ZIP + XML) консолидированного отчёта. */
    private function reportXlsx(array $d): string
    {
        $xesc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $num = static fn($v): string => number_format((float)$v, 2, '.', '');

        $hasExpByArt = [];
        $head = ['Участник'];
        foreach ($d['articles'] as $a) {
            $hasExp = (float)($d['articleTotals'][$a]['expense'] ?? 0) > 0;
            $hasExpByArt[$a] = $hasExp;
            $head[] = $a . ': Взнос';
            if ($hasExp) {
                $head[] = $a . ': Возмещено';
                $head[] = $a . ': Остаток';
            }
        }
        $head[] = 'Расходы';

        $rows = [];
        $rows[] = array_map(static fn($h) => ['n' => false, 'v' => $h, 'b' => true], $head);

        foreach ($d['users'] as $u) {
            $uid = (int)$u['id'];
            $row = [['n' => false, 'v' => full_name($u), 'b' => false]];
            foreach ($d['articles'] as $a) {
                $vz = (float)($d['contrib'][$uid][$a] ?? 0);
                $vo = (float)($d['expense'][$uid][$a] ?? 0);
                $row[] = ['n' => true, 'v' => $num($vz), 'b' => false];
                if ($hasExpByArt[$a]) {
                    $row[] = ['n' => true, 'v' => $num($vo), 'b' => false];
                    $row[] = ['n' => true, 'v' => $num($vz - $vo), 'b' => false];
                }
            }
            $row[] = ['n' => true, 'v' => $num($d['userExpenseTotal'][$uid] ?? 0), 'b' => false];
            $rows[] = $row;
        }

        $tot = [['n' => false, 'v' => 'Итого', 'b' => true]];
        foreach ($d['articles'] as $a) {
            $inc = (float)($d['articleTotals'][$a]['income'] ?? 0);
            $exp = (float)($d['articleTotals'][$a]['expense'] ?? 0);
            $tot[] = ['n' => true, 'v' => $num($inc), 'b' => true];
            if ($hasExpByArt[$a]) {
                $tot[] = ['n' => true, 'v' => $num($exp), 'b' => true];
                $tot[] = ['n' => true, 'v' => $num($inc - $exp), 'b' => true];
            }
        }
        $tot[] = ['n' => true, 'v' => $num(array_sum($d['userExpenseTotal'])), 'b' => true];
        $rows[] = $tot;

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $ri = 0;
        foreach ($rows as $row) {
            $ri++;
            $sheet .= '<row r="' . $ri . '">';
            $ci = 0;
            foreach ($row as $cell) {
                $ref = $this->xlsxCol($ci) . $ri;
                $ci++;
                if ($cell['n']) {
                    $style = $cell['b'] ? 3 : 2;
                    $sheet .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $cell['v'] . '</v></c>';
                } else {
                    $style = $cell['b'] ? 1 : 0;
                    $sheet .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
                        . $xesc((string)$cell['v']) . '</t></is></c>';
                }
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Отчёт" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            return '';
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $bin = (string)@file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    /** Буквенное обозначение колонки Excel по индексу (0 → A). */
    private function xlsxCol(int $i): string
    {
        $i++;
        $s = '';
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    }

    /** Формирует CSV консолидированного отчёта (UTF-8 BOM, разделитель «;»). */
    private function reportCsv(array $d): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM, чтобы Excel корректно прочитал UTF-8
        $sep = ';';
        $num = static fn($v): string => number_format((float)$v, 2, ',', '');

        $head = ['Участник'];
        foreach ($d['articles'] as $a) {
            $hasExp = (float)($d['articleTotals'][$a]['expense'] ?? 0) > 0;
            $head[] = $a . ': Взнос';
            if ($hasExp) {
                $head[] = $a . ': Возмещено';
                $head[] = $a . ': Остаток';
            }
        }
        $head[] = 'Расходы';
        fputcsv($out, $head, $sep, '"', '');

        foreach ($d['users'] as $u) {
            $uid = (int)$u['id'];
            $row = [full_name($u)];
            foreach ($d['articles'] as $a) {
                $hasExp = (float)($d['articleTotals'][$a]['expense'] ?? 0) > 0;
                $vz = (float)($d['contrib'][$uid][$a] ?? 0);
                $vo = (float)($d['expense'][$uid][$a] ?? 0);
                $row[] = $num($vz);
                if ($hasExp) {
                    $row[] = $num($vo);
                    $row[] = $num($vz - $vo);
                }
            }
            $row[] = $num($d['userExpenseTotal'][$uid] ?? 0);
            fputcsv($out, $row, $sep, '"', '');
        }

        $tot = ['Итого'];
        foreach ($d['articles'] as $a) {
            $hasExp = (float)($d['articleTotals'][$a]['expense'] ?? 0) > 0;
            $inc = (float)($d['articleTotals'][$a]['income'] ?? 0);
            $exp = (float)($d['articleTotals'][$a]['expense'] ?? 0);
            $tot[] = $num($inc);
            if ($hasExp) {
                $tot[] = $num($exp);
                $tot[] = $num($inc - $exp);
            }
        }
        $tot[] = $num(array_sum($d['userExpenseTotal']));
        fputcsv($out, $tot, $sep, '"', '');

        rewind($out);
        $csv = (string)stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    /** Данные консолидированного отчёта (общие для страницы и экспорта). */
    private function reportData(string $from, string $to, string $mode): array
    {
        $df = $mode === 'budget' ? 'period' : 'date';

        $contrib = [];        // [uid][article] => взнос
        $expense = [];        // [uid][article] => возмещено
        $articleTotals = [];  // [article] => ['income'=>, 'expense'=>]

        foreach (Db::fetchAll(
            "SELECT from_user_id AS uid, purpose AS article, SUM(amount) AS total
             FROM treasury_entries
             WHERE from_user_id IS NOT NULL AND $df >= ? AND $df <= ?
             GROUP BY from_user_id, purpose",
            [$from, $to]
        ) as $r) {
            $uid = (int)$r['uid'];
            $art = (string)$r['article'];
            $contrib[$uid][$art] = ($contrib[$uid][$art] ?? 0) + (float)$r['total'];
            $articleTotals[$art]['income'] = ($articleTotals[$art]['income'] ?? 0) + (float)$r['total'];
        }

        foreach (Db::fetchAll(
            "SELECT to_user_id AS uid, type AS article, SUM(amount) AS total
             FROM treasury_expenses
             WHERE to_user_id IS NOT NULL AND $df >= ? AND $df <= ?
             GROUP BY to_user_id, type",
            [$from, $to]
        ) as $r) {
            $uid = (int)$r['uid'];
            $art = (string)$r['article'];
            $expense[$uid][$art] = ($expense[$uid][$art] ?? 0) + (float)$r['total'];
            $articleTotals[$art]['expense'] = ($articleTotals[$art]['expense'] ?? 0) + (float)$r['total'];
        }

        $articles = array_keys($articleTotals);
        usort($articles, fn($a, $b) => mb_strtolower($a) <=> mb_strtolower($b));

        $users = Db::fetchAll('SELECT id, address_term, name FROM users WHERE is_active = 1 ORDER BY name');

        $userExpenseTotal = [];
        foreach ($expense as $uid => $byArt) {
            $userExpenseTotal[$uid] = array_sum($byArt);
        }

        return [
            'users' => $users,
            'articles' => $articles,
            'articleTotals' => $articleTotals,
            'contrib' => $contrib,
            'expense' => $expense,
            'userExpenseTotal' => $userExpenseTotal,
        ];
    }

    private function buildChart(): array
    {
        $income = [];
        foreach (Db::fetchAll('SELECT date, purpose AS article, amount FROM treasury_entries ORDER BY date, id') as $r) {
            if ($r['article'] === '') {
                continue;
            }
            $income[$r['date']][$r['article']] = ($income[$r['date']][$r['article']] ?? 0) + (float)$r['amount'];
        }
        $expense = [];
        foreach (Db::fetchAll('SELECT date, type AS article, amount FROM treasury_expenses ORDER BY date, id') as $r) {
            if ($r['article'] === '') {
                continue;
            }
            $expense[$r['date']][$r['article']] = ($expense[$r['date']][$r['article']] ?? 0) + (float)$r['amount'];
        }

        $articleSet = [];
        foreach ($income as $byDate) {
            foreach (array_keys($byDate) as $a) {
                $articleSet[$a] = true;
            }
        }
        foreach ($expense as $byDate) {
            foreach (array_keys($byDate) as $a) {
                $articleSet[$a] = true;
            }
        }
        $articles = array_keys($articleSet);
        usort($articles, fn($a, $b) => mb_strtolower($a) <=> mb_strtolower($b));

        $dates = array_unique(array_merge(array_keys($income), array_keys($expense)));
        sort($dates, SORT_STRING);

        $cum = array_fill_keys($articles, 0.0);
        $series = array_fill_keys($articles, []);
        foreach ($dates as $date) {
            foreach ($articles as $a) {
                $cum[$a] += ($income[$date][$a] ?? 0) - ($expense[$date][$a] ?? 0);
                $series[$a][] = ['date' => $date, 'balance' => round($cum[$a], 2)];
            }
        }
        return ['series' => $series, 'articles' => $articles];
    }

    public function save(): void
    {
        $date = input('date');
        $from = input('from');
        $purpose = input('purpose');
        $amount = (float)str_replace([',', ' '], ['.', ''], input('amount'));
        $note = input('note');

        // «От кого» — одно поле: если значение совпало с участником, пишем его id,
        // иначе сохраняем как введённый вручную текст.
        $fromUserId = 0;
        $fromText = '';
        if ($from !== '') {
            $found = Db::fetch(
                "SELECT id FROM users WHERE address_term || ' ' || name = ?",
                [$from]
            );
            if ($found) {
                $fromUserId = (int)$found['id'];
            } else {
                $fromText = $from;
            }
        }

        if (!strtotime($date)) {
            set_flash('Укажите корректную дату поступления', 'danger');
            redirect(url('treasury'));
        }
        if ($purpose === '') {
            set_flash('Укажите, за что поступление', 'danger');
            redirect(url('treasury'));
        }
        if ($amount <= 0) {
            set_flash('Укажите сумму поступления', 'danger');
            redirect(url('treasury'));
        }
        if (!$fromUserId && mb_strlen($fromText) < 2) {
            set_flash('Укажите, от кого поступление (или выберите из списка)', 'danger');
            redirect(url('treasury'));
        }

        Db::insert('treasury_entries', [
            'date' => $date,
            'period' => input('period'),
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

    public function addExpense(): void
    {
        $date = input('date');
        $amount = (float)str_replace([',', ' '], ['.', ''], input('amount'));
        $type = input('type');
        $description = input('description');
        $to = input('to');

        // «Кому» — необязательное поле: участник (по ФИО) или введённый вручную текст
        $toUserId = 0;
        $toText = '';
        if ($to !== '') {
            $found = Db::fetch("SELECT id FROM users WHERE address_term || ' ' || name = ?", [$to]);
            if ($found) {
                $toUserId = (int)$found['id'];
            } else {
                $toText = $to;
            }
        }

        if (!strtotime($date)) {
            set_flash('Укажите корректную дату расхода', 'danger');
            redirect(url('treasury'));
        }
        if ($amount <= 0) {
            set_flash('Укажите сумму расхода', 'danger');
            redirect(url('treasury'));
        }
        if (mb_strlen($type) < 2) {
            set_flash('Укажите статью расхода', 'danger');
            redirect(url('treasury'));
        }
        if (mb_strlen($description) < 2) {
            set_flash('Опишите, на что потрачено', 'danger');
            redirect(url('treasury'));
        }

        Db::insert('treasury_expenses', [
            'date' => $date,
            'period' => input('period'),
            'amount' => $amount,
            'type' => $type,
            'description' => $description,
            'to_user_id' => $toUserId ?: null,
            'to_text' => $toText,
        ]);

        set_flash('Расход записан');
        redirect(url('treasury'));
    }

    public function deleteExpense(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if (!Db::fetch('SELECT id FROM treasury_expenses WHERE id = ?', [$id])) {
            http_response_code(404);
            exit('Запись не найдена');
        }
        Db::delete('treasury_expenses', ['id' => $id]);
        set_flash('Расход удалён', 'info');
        redirect(url('treasury'));
    }
}