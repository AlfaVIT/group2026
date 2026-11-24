<?php

class TreasuryController
{
    public function index(): void
    {
        // По умолчанию фильтры периода — текущий месяц
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $start = get_str('from', $monthStart);
        $end = get_str('to', $monthEnd);
        $purposeFilter = get_str('purpose');
        $expStart = get_str('exp_from', $monthStart);
        $expEnd = get_str('exp_to', $monthEnd);
        $expType = get_str('exp_type');

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
            // варианты написания («Трапеза» и «трапеза») — это одна статья
            $variants = [];
            $key = article_key($purposeFilter);
            foreach (Db::fetchAll("SELECT DISTINCT purpose FROM treasury_entries WHERE TRIM(purpose) != ''") as $v) {
                if (article_key((string)$v['purpose']) === $key) {
                    $variants[] = (string)$v['purpose'];
                }
            }
            if ($variants) {
                $where[] = 't.purpose IN (' . implode(',', array_fill(0, count($variants), '?')) . ')';
                $params = array_merge($params, $variants);
            } else {
                $where[] = 't.purpose = ?';
                $params[] = $purposeFilter;
            }
        }
        $wSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Фильтры расходов нужны и списку, и сводке по статьям — считаем их заранее
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

        $entries = Db::fetchAll(
            "SELECT t.*, u.address_term AS from_term, u.name AS from_name
             FROM treasury_entries t
             LEFT JOIN users u ON u.id = t.from_user_id
             $wSql
             ORDER BY t.date DESC, t.id DESC"
        , $params);

        $expenses = Db::fetchAll(
            "SELECT treasury_expenses.*, u.address_term AS to_term, u.name AS to_name
             FROM treasury_expenses
             LEFT JOIN users u ON u.id = treasury_expenses.to_user_id
             $expW ORDER BY date DESC, id DESC",
            $expParams
        );

        // Суммы считаем в PHP и в копейках: поле amount зашифровано (SUM() в SQL
        // не работает), а копейки дают точный итог независимо от порядка сложения.
        $incomeByCents = [];
        $incomeEntriesCents = 0;
        foreach ($entries as $r) {
            $cents = amount_cents($r['amount']);
            $incomeEntriesCents += $cents;
            $key = article_key((string)$r['purpose']);
            if ($key !== '') {
                $incomeByCents[$key] = ($incomeByCents[$key] ?? 0) + $cents;
            }
        }
        $expenseByCents = [];
        $expensesCents = 0;
        foreach ($expenses as $r) {
            $cents = amount_cents($r['amount']);
            $expensesCents += $cents;
            $key = article_key((string)$r['type']);
            if ($key !== '') {
                $expenseByCents[$key] = ($expenseByCents[$key] ?? 0) + $cents;
            }
        }
        $incomeEntriesTotal = cents_amount($incomeEntriesCents);
        $expensesTotal = cents_amount($expensesCents);

        $incomeNames = collect_articles('treasury_entries', 'purpose');
        $expenseNames = collect_articles('treasury_expenses', 'type');
        $articleStats = [];
        foreach (array_unique(array_merge(array_keys($incomeByCents), array_keys($expenseByCents))) as $key) {
            $name = $incomeNames[$key] ?? $expenseNames[$key] ?? $key;
            $income = cents_amount($incomeByCents[$key] ?? 0);
            $expense = cents_amount($expenseByCents[$key] ?? 0);
            $articleStats[$name] = [
                'income' => $income,
                'expense' => $expense,
                'balance' => amount_diff($income, $expense),
            ];
        }
        uksort($articleStats, fn($a, $b) => mb_strtolower((string)$a) <=> mb_strtolower((string)$b));
        $incomeTotal = cents_amount(array_sum($incomeByCents));
        $expenseTotal = cents_amount(array_sum($expenseByCents));
        $balanceTotal = amount_diff($incomeTotal, $expenseTotal);

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
                    'total' => 0,
                    'users' => [],
                ];
            }
            $meetingExpenses[$mid]['total'] += amount_cents($e['amount']);
            $uid = $e['user_id'] ? (int)$e['user_id'] : 0;
            if (!isset($meetingExpenses[$mid]['users'][$uid])) {
                $meetingExpenses[$mid]['users'][$uid] = [
                    'name' => $e['user_id'] ? ($e['u_term'] . ' ' . $e['u_name']) : 'Без указания',
                    'total' => 0,
                    'items' => [],
                ];
            }
            $meetingExpenses[$mid]['users'][$uid]['total'] += amount_cents($e['amount']);
            $meetingExpenses[$mid]['users'][$uid]['items'][] = $e;
        }
        // итоги накапливались в копейках — в рубли переводим один раз
        foreach ($meetingExpenses as &$me) {
            $me['total'] = cents_amount($me['total']);
            foreach ($me['users'] as &$pu) {
                $pu['total'] = cents_amount($pu['total']);
            }
        }
        unset($me, $pu);

        // Списки статей — из справочника (скрытые статьи не предлагаем)
        $purposes = Article::names('income');
        $expenseArticles = Article::names('expense');

        $chart = $this->buildChart();

        $users = sort_by_name(Db::fetchAll('SELECT id, address_term, name FROM users WHERE is_active = 1'));

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
            'suggestUrl' => url('article_suggest'),
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
        $from = get_str('from', date('Y-m-01'));
        $to = get_str('to', date('Y-m-t'));
        $mode = get_str('mode', 'cash') === 'budget' ? 'budget' : 'cash';
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
        $from = get_str('from', date('Y-m-01'));
        $to = get_str('to', date('Y-m-t'));
        $mode = get_str('mode', 'cash') === 'budget' ? 'budget' : 'cash';
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
            $row = [['n' => false, 'v' => full_name($u) . (empty($u['is_active']) ? ' (отключён)' : ''), 'b' => false]];
            foreach ($d['articles'] as $a) {
                $vz = (float)($d['contrib'][$uid][$a] ?? 0);
                $vo = (float)($d['expense'][$uid][$a] ?? 0);
                $row[] = ['n' => true, 'v' => $num($vz), 'b' => false];
                if ($hasExpByArt[$a]) {
                    $row[] = ['n' => true, 'v' => $num($vo), 'b' => false];
                    $row[] = ['n' => true, 'v' => $num(amount_diff($vz, $vo)), 'b' => false];
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
                $tot[] = ['n' => true, 'v' => $num(amount_diff($inc, $exp)), 'b' => true];
            }
        }
        $tot[] = ['n' => true, 'v' => $num($d['expenseGrandTotal']), 'b' => true];
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
            $row = [full_name($u) . (empty($u['is_active']) ? ' (отключён)' : '')];
            foreach ($d['articles'] as $a) {
                $hasExp = (float)($d['articleTotals'][$a]['expense'] ?? 0) > 0;
                $vz = (float)($d['contrib'][$uid][$a] ?? 0);
                $vo = (float)($d['expense'][$uid][$a] ?? 0);
                $row[] = $num($vz);
                if ($hasExp) {
                    $row[] = $num($vo);
                    $row[] = $num(amount_diff($vz, $vo));
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
                $tot[] = $num(amount_diff($inc, $exp));
            }
        }
        $tot[] = $num($d['expenseGrandTotal']);
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

        $contribCents = [];   // [uid][article] => копейки
        $expenseCents = [];   // [uid][article] => копейки
        $articleCents = [];   // [article] => ['income'=>копейки, 'expense'=>копейки]

        foreach (Db::fetchAll(
            "SELECT from_user_id AS uid, purpose AS article, amount
             FROM treasury_entries
             WHERE from_user_id IS NOT NULL AND $df >= ? AND $df <= ?
             ORDER BY id",
            [$from, $to]
        ) as $r) {
            $uid = (int)$r['uid'];
            $art = (string)$r['article'];
            $cents = amount_cents($r['amount']);
            $contribCents[$uid][$art] = ($contribCents[$uid][$art] ?? 0) + $cents;
            $articleCents[$art]['income'] = ($articleCents[$art]['income'] ?? 0) + $cents;
        }

        foreach (Db::fetchAll(
            "SELECT to_user_id AS uid, type AS article, amount
             FROM treasury_expenses
             WHERE to_user_id IS NOT NULL AND $df >= ? AND $df <= ?
             ORDER BY id",
            [$from, $to]
        ) as $r) {
            $uid = (int)$r['uid'];
            $art = (string)$r['article'];
            $cents = amount_cents($r['amount']);
            $expenseCents[$uid][$art] = ($expenseCents[$uid][$art] ?? 0) + $cents;
            $articleCents[$art]['expense'] = ($articleCents[$art]['expense'] ?? 0) + $cents;
        }

        $contrib = [];
        foreach ($contribCents as $uid => $byArt) {
            foreach ($byArt as $art => $cents) {
                $contrib[$uid][$art] = cents_amount($cents);
            }
        }
        $expense = [];
        foreach ($expenseCents as $uid => $byArt) {
            foreach ($byArt as $art => $cents) {
                $expense[$uid][$art] = cents_amount($cents);
            }
        }
        $articleTotals = [];
        foreach ($articleCents as $art => $byKind) {
            $articleTotals[$art] = [
                'income' => cents_amount($byKind['income'] ?? 0),
                'expense' => cents_amount($byKind['expense'] ?? 0),
            ];
        }

        $articles = array_keys($articleTotals);
        usort($articles, fn($a, $b) => mb_strtolower($a) <=> mb_strtolower($b));

        // В отчёт попадают все, у кого есть движения за период, даже если учётная
        // запись уже отключена: иначе «Итого» не сходится с суммой строк
        $users = Db::fetchAll(
            "SELECT id, address_term, name, is_active FROM users
             WHERE is_active = 1
                OR id IN (SELECT from_user_id FROM treasury_entries
                          WHERE from_user_id IS NOT NULL AND $df >= ? AND $df <= ?)
                OR id IN (SELECT to_user_id FROM treasury_expenses
                          WHERE to_user_id IS NOT NULL AND $df >= ? AND $df <= ?)",
            [$from, $to, $from, $to]
        );
        // имя зашифровано: порядок «сначала активные, затем по имени» — в PHP
        usort($users, static function (array $a, array $b): int {
            $byActive = (int)$b['is_active'] <=> (int)$a['is_active'];
            return $byActive !== 0 ? $byActive : strcmp((string)$a['name'], (string)$b['name']);
        });

        $userExpenseTotal = [];
        $expenseGrandCents = 0;
        foreach ($expenseCents as $uid => $byArt) {
            $sum = array_sum($byArt); // копейки — целые, порядок не важен
            $userExpenseTotal[$uid] = cents_amount($sum);
            $expenseGrandCents += $sum;
        }

        return [
            'users' => $users,
            'articles' => $articles,
            'articleTotals' => $articleTotals,
            'expenseGrandTotal' => cents_amount($expenseGrandCents),
            'contrib' => $contrib,
            'expense' => $expense,
            'userExpenseTotal' => $userExpenseTotal,
        ];
    }

    private function buildChart(): array
    {
        // копейки: сумма по дню/статье не зависит от порядка
        $income = [];
        foreach (Db::fetchAll('SELECT date, purpose AS article, amount FROM treasury_entries ORDER BY date, id') as $r) {
            if ($r['article'] === '') {
                continue;
            }
            $income[$r['date']][$r['article']] = ($income[$r['date']][$r['article']] ?? 0) + amount_cents($r['amount']);
        }
        $expense = [];
        foreach (Db::fetchAll('SELECT date, type AS article, amount FROM treasury_expenses ORDER BY date, id') as $r) {
            if ($r['article'] === '') {
                continue;
            }
            $expense[$r['date']][$r['article']] = ($expense[$r['date']][$r['article']] ?? 0) + amount_cents($r['amount']);
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

        $cum = array_fill_keys($articles, 0);
        $series = array_fill_keys($articles, []);
        foreach ($dates as $date) {
            foreach ($articles as $a) {
                $cum[$a] += ($income[$date][$a] ?? 0) - ($expense[$date][$a] ?? 0);
                $series[$a][] = ['date' => $date, 'balance' => round($cum[$a] / 100, 2)];
            }
        }
        return ['series' => $series, 'articles' => $articles];
    }

    public function save(): void
    {
        // дату приводим к машинному виду: фильтры и отчёты сравнивают строки,
        // поэтому «1.2.2026» из браузера без поддержки input[type=date] хранить нельзя
        $dateRaw = input('date');
        $dateTs = $dateRaw !== '' ? strtotime($dateRaw) : false;
        $date = $dateTs !== false ? date('Y-m-d', $dateTs) : '';
        $periodRaw = input('period');
        $periodTs = $periodRaw !== '' ? strtotime($periodRaw) : false;
        $period = $periodTs !== false ? date('Y-m-d', $periodTs) : '';
        $from = input('from');
        // статью приводим к единому виду: без лишних пробелов, с заглавной буквы
        $purpose = article_canonical(input('purpose'));
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

        if ($date === '') {
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

        // справочник: если статьи ещё нет — она появится, а написание станет единым
        $purpose = Article::resolve('income', $purpose);

        Db::insert('treasury_entries', [
            'date' => $date,
            'period' => $period,
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
        // см. save(): дата и период — только в формате YYYY-MM-DD
        $dateRaw = input('date');
        $dateTs = $dateRaw !== '' ? strtotime($dateRaw) : false;
        $date = $dateTs !== false ? date('Y-m-d', $dateTs) : '';
        $periodRaw = input('period');
        $periodTs = $periodRaw !== '' ? strtotime($periodRaw) : false;
        $period = $periodTs !== false ? date('Y-m-d', $periodTs) : '';
        $amount = (float)str_replace([',', ' '], ['.', ''], input('amount'));
        // статью приводим к единому виду: без лишних пробелов, с заглавной буквы
        $type = article_canonical(input('type'));
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

        if ($date === '') {
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

        // справочник: если статьи ещё нет — она появится, а написание станет единым
        $type = Article::resolve('expense', $type);

        Db::insert('treasury_expenses', [
            'date' => $date,
            'period' => $period,
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