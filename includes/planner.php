<?php
declare(strict_types=1);

function company_plan_options(): array
{
    return [
        'starter' => 'Starter',
        'sme' => 'Business',
        'office' => 'Pro',
    ];
}

function normalize_company_plan(string $plan): string
{
    $plan = strtolower(trim($plan));
    // Friendly aliases from marketing / SA copy.
    $plan = match ($plan) {
        'business', 'ledger', 'studio' => 'sme',
        'pro', 'crest', 'practice', 'office' => 'office',
        'starter', 'quill', 'solo' => 'starter',
        default => $plan,
    };
    return array_key_exists($plan, company_plan_options()) ? $plan : 'sme';
}

function company_plan_label(?array $company = null): string
{
    $company = $company ?? current_company();
    $plan = normalize_company_plan((string) ($company['plan'] ?? 'sme'));
    return company_plan_options()[$plan];
}

function plan_includes_planner(string $plan): bool
{
    return in_array(normalize_company_plan($plan), ['sme', 'office'], true);
}

function company_planner_enabled(?array $company = null): bool
{
    $company = $company ?? current_company();
    if (!$company) {
        return false;
    }
    return (int) ($company['planner_enabled'] ?? 0) === 1;
}

function planner_resolve_enabled(string $plan, bool $checkboxOn, ?array $previous = null): int
{
    $plan = normalize_company_plan($plan);
    $prevPlan = normalize_company_plan((string) ($previous['plan'] ?? ''));
    // Switching onto Business or Pro turns Planner on automatically.
    if (plan_includes_planner($plan) && !plan_includes_planner($prevPlan)) {
        return 1;
    }
    // Creating a new Business/Pro desk with no previous row.
    if ($previous === null && plan_includes_planner($plan)) {
        return 1;
    }
    return $checkboxOn ? 1 : 0;
}

function require_planner(): array
{
    $user = require_desk_admin();
    if (!company_planner_enabled()) {
        flash('Planner is not on for this desk. Ask Vellisys if you need Business or Pro.', 'err');
        redirect('dashboard.php');
    }
    return $user;
}

function planner_priorities(): array
{
    return [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'essential' => 'Essential',
    ];
}

function planner_normalize_priority(string $raw): string
{
    $raw = strtolower(trim($raw));
    return array_key_exists($raw, planner_priorities()) ? $raw : 'normal';
}

function planner_event_kinds(): array
{
    return [
        'appointment' => 'Appointment',
        'deadline' => 'Deadline',
        'program' => 'Program',
        'reminder' => 'Reminder',
        'other' => 'Other',
    ];
}

function planner_normalize_event_kind(string $raw): string
{
    $raw = strtolower(trim($raw));
    return array_key_exists($raw, planner_event_kinds()) ? $raw : 'appointment';
}

function planner_budget_kinds(): array
{
    return [
        'income' => 'Income target',
        'expense' => 'Expense budget',
    ];
}

function planner_note_save(array $data, int $id = 0): int
{
    $cid = current_company_id();
    $uid = (int) (current_user()['id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $priority = planner_normalize_priority((string) ($data['priority'] ?? 'normal'));
    $pinned = !empty($data['pinned']) ? 1 : 0;
    if ($title === '') {
        throw new RuntimeException('Give the note a title.');
    }
    if ($id > 0) {
        $row = db_one('SELECT id FROM planner_notes WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if (!$row) {
            throw new RuntimeException('Note not found.');
        }
        db_exec(
            'UPDATE planner_notes SET title=?, body=?, priority=?, pinned=?, updated_at=NOW() WHERE id=? AND company_id=?',
            'sssiii',
            [$title, $body, $priority, $pinned, $id, $cid]
        );
        return $id;
    }
    return db_exec(
        'INSERT INTO planner_notes (company_id, user_id, title, body, priority, pinned) VALUES (?,?,?,?,?,?)',
        'iisssi',
        [$cid, $uid, $title, $body, $priority, $pinned]
    );
}

function planner_note_delete(int $id): void
{
    db_exec('DELETE FROM planner_notes WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
}

function planner_goal_save(array $data, int $id = 0): int
{
    $cid = current_company_id();
    $uid = (int) (current_user()['id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $priority = planner_normalize_priority((string) ($data['priority'] ?? 'normal'));
    $status = (string) ($data['status'] ?? 'open');
    if ($status !== 'hit') {
        $status = 'open';
    }
    $due = trim((string) ($data['due_date'] ?? ''));
    $due = $due === '' ? null : $due;
    if ($due !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        $due = null;
    }
    if ($title === '') {
        throw new RuntimeException('Name the task or goal.');
    }
    if ($id > 0) {
        $row = db_one('SELECT id FROM planner_goals WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if (!$row) {
            throw new RuntimeException('Task not found.');
        }
        db_exec(
            'UPDATE planner_goals SET title=?, body=?, due_date=?, status=?, priority=?, updated_at=NOW() WHERE id=? AND company_id=?',
            'sssssii',
            [$title, $body !== '' ? $body : null, $due, $status, $priority, $id, $cid]
        );
        if (function_exists('record_company_activity')) {
            record_company_activity('planner', ($status === 'hit' ? 'Hit: ' : 'Updated task: ') . $title, [
                'href' => 'planner_goals.php?edit=' . $id,
                'ref_type' => 'goal',
                'ref_id' => $id,
            ]);
        }
        return $id;
    }
    $newId = db_exec(
        'INSERT INTO planner_goals (company_id, user_id, title, body, due_date, status, priority) VALUES (?,?,?,?,?,?,?)',
        'iisssss',
        [$cid, $uid, $title, $body !== '' ? $body : null, $due, $status, $priority]
    );
    if (function_exists('record_company_activity')) {
        record_company_activity('planner', 'New task: ' . $title, [
            'href' => 'planner_goals.php?edit=' . $newId,
            'ref_type' => 'goal',
            'ref_id' => $newId,
        ]);
    }
    if (function_exists('push_notify_item')) {
        push_notify_item([
            'title' => $title,
            'meta' => $due ? 'Due ' . format_date($due) : 'New task',
            'href' => url('planner_goals.php?edit=' . $newId),
            'key' => 'goal:' . $newId,
        ], 'company');
    }
    return $newId;
}

function planner_goal_delete(int $id): void
{
    db_exec('DELETE FROM planner_goals WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
}

function planner_goals(?string $status = null): array
{
    try {
        $cid = current_company_id();
        if ($status === 'open' || $status === 'hit') {
            return db_all(
                'SELECT * FROM planner_goals WHERE company_id = ? AND status = ? ORDER BY FIELD(priority,\'essential\',\'high\',\'normal\',\'low\'), due_date IS NULL, due_date, id DESC',
                'is',
                [$cid, $status]
            );
        }
        return db_all(
            'SELECT * FROM planner_goals WHERE company_id = ? ORDER BY FIELD(status,\'open\',\'hit\'), FIELD(priority,\'essential\',\'high\',\'normal\',\'low\'), due_date IS NULL, due_date, id DESC',
            'i',
            [$cid]
        );
    } catch (Throwable $e) {
        return [];
    }
}

function planner_notes(?string $q = null): array
{
    $cid = current_company_id();
    if ($q !== null && trim($q) !== '') {
        $like = '%' . trim($q) . '%';
        return db_all(
            'SELECT * FROM planner_notes WHERE company_id = ? AND (title LIKE ? OR body LIKE ?) ORDER BY pinned DESC, FIELD(priority,\'essential\',\'high\',\'normal\',\'low\'), updated_at DESC, id DESC',
            'iss',
            [$cid, $like, $like]
        );
    }
    return db_all(
        'SELECT * FROM planner_notes WHERE company_id = ? ORDER BY pinned DESC, FIELD(priority,\'essential\',\'high\',\'normal\',\'low\'), updated_at DESC, id DESC',
        'i',
        [$cid]
    );
}

function planner_budget_save(array $data, int $id = 0): int
{
    $cid = current_company_id();
    $uid = (int) (current_user()['id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $category = trim((string) ($data['category'] ?? 'General'));
    $kind = (string) ($data['kind'] ?? 'expense');
    if (!isset(planner_budget_kinds()[$kind])) {
        $kind = 'expense';
    }
    $amount = (float) preg_replace('/[^0-9.\-]/', '', (string) ($data['amount'] ?? '0'));
    $month = trim((string) ($data['month_key'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }
    $notes = trim((string) ($data['notes'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('Name this budget line.');
    }
    if ($id > 0) {
        $row = db_one('SELECT id FROM planner_budget_items WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if (!$row) {
            throw new RuntimeException('Budget line not found.');
        }
        db_exec(
            'UPDATE planner_budget_items SET title=?, category=?, kind=?, amount=?, month_key=?, notes=? WHERE id=? AND company_id=?',
            'sssdssii',
            [$title, $category !== '' ? $category : 'General', $kind, $amount, $month, $notes !== '' ? $notes : null, $id, $cid]
        );
        return $id;
    }
    return db_exec(
        'INSERT INTO planner_budget_items (company_id, user_id, title, category, kind, amount, month_key, notes) VALUES (?,?,?,?,?,?,?,?)',
        'iisssdss',
        [$cid, $uid, $title, $category !== '' ? $category : 'General', $kind, $amount, $month, $notes !== '' ? $notes : null]
    );
}

function planner_budget_delete(int $id): void
{
    db_exec('DELETE FROM planner_budget_items WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
}

function planner_budget_items(string $monthKey): array
{
    return db_all(
        'SELECT * FROM planner_budget_items WHERE company_id = ? AND month_key = ? ORDER BY kind, id',
        'is',
        [current_company_id(), $monthKey]
    );
}

function planner_budget_actuals(string $monthKey): array
{
    $cid = current_company_id();
    $from = $monthKey . '-01';
    $to = date('Y-m-t', strtotime($from));
    $currency = default_currency();
    $income = 0.0;
    $expense = 0.0;
    $invoices = attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id
         WHERE d.company_id = ? AND d.kind = 'invoice' AND d.status <> 'void' AND d.date >= ? AND d.date <= ?",
        'iss',
        [$cid, $from, $to]
    ));
    foreach ($invoices as $doc) {
        $income += convert_money((float) ($doc['totals']['total'] ?? 0), doc_currency($doc), $currency);
    }
    $expenses = attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name FROM documents d JOIN parties p ON p.id = d.party_id
         WHERE d.company_id = ? AND d.kind = 'expense' AND d.status <> 'void' AND d.date >= ? AND d.date <= ?",
        'iss',
        [$cid, $from, $to]
    ));
    foreach ($expenses as $doc) {
        $expense += convert_money((float) ($doc['totals']['total'] ?? 0), doc_currency($doc), $currency);
    }
    return [
        'income' => round($income, 2),
        'expense' => round($expense, 2),
        'currency' => $currency,
        'invoice_count' => count($invoices),
        'expense_count' => count($expenses),
    ];
}

function planner_event_save(array $data, int $id = 0): int
{
    $cid = current_company_id();
    $uid = (int) (current_user()['id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $date = trim((string) ($data['event_date'] ?? ''));
    $time = trim((string) ($data['event_time'] ?? ''));
    $end = trim((string) ($data['end_date'] ?? ''));
    $kind = planner_normalize_event_kind((string) ($data['kind'] ?? 'appointment'));
    $priority = planner_normalize_priority((string) ($data['priority'] ?? 'normal'));
    $partyId = (int) ($data['party_id'] ?? 0);
    $docId = (int) ($data['document_id'] ?? 0);
    $done = !empty($data['done']) ? 1 : 0;
    if ($title === '') {
        throw new RuntimeException('Name the event.');
    }
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new RuntimeException('Pick a valid date.');
    }
    if ($time !== '' && !preg_match('/^\d{2}:\d{2}/', $time)) {
        $time = '';
    } elseif ($time !== '') {
        $time = substr($time, 0, 5) . ':00';
    }
    if ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end = '';
    }
    if ($partyId > 0) {
        $party = db_one('SELECT id FROM parties WHERE id = ? AND company_id = ?', 'ii', [$partyId, $cid]);
        if (!$party) {
            $partyId = 0;
        }
    }
    if ($docId > 0) {
        $doc = db_one('SELECT id FROM documents WHERE id = ? AND company_id = ?', 'ii', [$docId, $cid]);
        if (!$doc) {
            $docId = 0;
        }
    }
    $timeSql = $time !== '' ? $time : null;
    $endSql = $end !== '' ? $end : null;
    $partySql = $partyId > 0 ? $partyId : null;
    $docSql = $docId > 0 ? $docId : null;
    $bodySql = $body !== '' ? $body : null;
    if ($id > 0) {
        $row = db_one('SELECT id FROM planner_events WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]);
        if (!$row) {
            throw new RuntimeException('Event not found.');
        }
        db_exec(
            'UPDATE planner_events SET title=?, body=?, event_date=?, event_time=?, end_date=?, kind=?, priority=?, party_id=?, document_id=?, done=?, updated_at=NOW() WHERE id=? AND company_id=?',
            'sssssssiiiii',
            [$title, $bodySql, $date, $timeSql, $endSql, $kind, $priority, $partySql, $docSql, $done, $id, $cid]
        );
        return $id;
    }
    $newId = (int) db_exec(
        'INSERT INTO planner_events (company_id, user_id, title, body, event_date, event_time, end_date, kind, priority, party_id, document_id, done) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        'iisssssssiii',
        [$cid, $uid, $title, $bodySql, $date, $timeSql, $endSql, $kind, $priority, $partySql, $docSql, $done]
    );
    planner_event_after_save($newId, $title, $date);
    return $newId;
}

function planner_event_after_save(int $id, string $title, string $date): void
{
    if (!function_exists('push_notify_item')) {
        return;
    }
    push_notify_item([
        'title' => $title,
        'meta' => 'Planner · ' . format_date($date),
        'href' => url('planner_calendar.php?date=' . urlencode($date) . '&edit=' . $id),
        'key' => 'event:' . $id,
    ], 'company');
}

function planner_event_delete(int $id): void
{
    db_exec('DELETE FROM planner_events WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
}

function planner_event_toggle_done(int $id): void
{
    db_exec(
        'UPDATE planner_events SET done = IF(done=1,0,1), updated_at=NOW() WHERE id = ? AND company_id = ?',
        'ii',
        [$id, current_company_id()]
    );
}

function planner_events_between(string $from, string $to): array
{
    return db_all(
        'SELECT e.*, p.name AS party_name, d.number AS document_number
         FROM planner_events e
         LEFT JOIN parties p ON p.id = e.party_id
         LEFT JOIN documents d ON d.id = e.document_id
         WHERE e.company_id = ? AND e.event_date >= ? AND e.event_date <= ?
         ORDER BY e.event_date, e.event_time IS NULL, e.event_time, e.id',
        'iss',
        [current_company_id(), $from, $to]
    );
}

function planner_events_on(string $date): array
{
    return planner_events_between($date, $date);
}

function planner_due_invoices(int $days = 14): array
{
    $cid = current_company_id();
    $until = date('Y-m-d', strtotime('+' . max(1, $days) . ' days'));
    return attach_document_totals(db_all(
        "SELECT d.*, p.name AS party_name FROM documents d
         JOIN parties p ON p.id = d.party_id
         WHERE d.company_id = ? AND d.kind = 'invoice' AND d.status <> 'void'
           AND d.due_date IS NOT NULL AND d.due_date <> '' AND d.due_date <= ?
         ORDER BY d.due_date, d.id
         LIMIT 40",
        'is',
        [$cid, $until]
    ));
}

function planner_notifications(int $limit = 12): array
{
    if (!company_planner_enabled()) {
        return [];
    }
    $cid = current_company_id();
    $today = today();
    $until = date('Y-m-d', strtotime('+10 days'));
    $items = [];

    $events = db_all(
        "SELECT id, title, event_date, kind, priority, done FROM planner_events
         WHERE company_id = ? AND done = 0 AND event_date <= ?
         ORDER BY FIELD(priority,'essential','high','normal','low'), event_date, id
         LIMIT 20",
        'is',
        [$cid, $until]
    );
    foreach ($events as $ev) {
        $when = (string) $ev['event_date'];
        $label = $when < $today ? 'Overdue' : ($when === $today ? 'Today' : format_date($when));
        $items[] = [
            'type' => 'event',
            'tone' => $when < $today || $ev['priority'] === 'essential' ? 'warn' : 'info',
            'title' => (string) $ev['title'],
            'meta' => ucfirst((string) $ev['kind']) . ' · ' . $label,
            'href' => url('planner_calendar.php?date=' . urlencode($when) . '&edit=' . (int) $ev['id']),
            'sort' => $when . '-' . $ev['priority'],
        ];
    }

    $notes = db_all(
        "SELECT id, title, priority, updated_at FROM planner_notes
         WHERE company_id = ? AND priority IN ('essential','high')
         ORDER BY FIELD(priority,'essential','high'), updated_at DESC LIMIT 8",
        'i',
        [$cid]
    );
    foreach ($notes as $note) {
        $items[] = [
            'type' => 'note',
            'tone' => $note['priority'] === 'essential' ? 'warn' : 'info',
            'title' => (string) $note['title'],
            'meta' => ucfirst((string) $note['priority']) . ' note',
            'href' => url('planner_notes.php?edit=' . (int) $note['id']),
            'sort' => '9-' . $note['priority'],
        ];
    }

    try {
        $goals = db_all(
            "SELECT id, title, due_date, priority FROM planner_goals
             WHERE company_id = ? AND status = 'open' AND (due_date IS NULL OR due_date <= ?)
             ORDER BY FIELD(priority,'essential','high','normal','low'), due_date IS NULL, due_date, id
             LIMIT 8",
            'is',
            [$cid, $until]
        );
        foreach ($goals as $goal) {
            $due = (string) ($goal['due_date'] ?? '');
            $label = $due === '' ? 'Open task' : ($due < $today ? 'Overdue' : ($due === $today ? 'Due today' : 'Due ' . format_date($due)));
            $items[] = [
                'type' => 'goal',
                'tone' => ($due !== '' && $due < $today) || $goal['priority'] === 'essential' ? 'warn' : 'info',
                'title' => (string) $goal['title'],
                'meta' => $label,
                'href' => url('planner_goals.php?edit=' . (int) $goal['id']),
                'sort' => ($due !== '' ? $due : '9') . '-goal',
            ];
        }
    } catch (Throwable $e) {
        // Goals table arrives with schema 41.
    }

    foreach (planner_due_invoices(7) as $doc) {
        if ((float) ($doc['balance'] ?? 0) <= 0.009) {
            continue;
        }
        $due = (string) ($doc['due_date'] ?? '');
        $items[] = [
            'type' => 'invoice',
            'tone' => $due < $today ? 'warn' : 'info',
            'title' => $doc['party_name'] . ' · ' . $doc['number'],
            'meta' => 'Invoice due ' . format_date($due) . ' · ' . money($doc['balance'], doc_currency($doc)),
            'href' => url('document_view.php?id=' . (int) $doc['id']),
            'sort' => $due . '-inv',
        ];
    }

    usort($items, static fn ($a, $b) => strcmp((string) $a['sort'], (string) $b['sort']));
    return array_slice($items, 0, $limit);
}

function planner_month_matrix(int $year, int $month): array
{
    $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $startPad = ((int) $first->format('N')) % 7; // Sunday-first feel: convert Mon=1..Sun=7 → Sun=0
    // Use Monday-first for business desks.
    $startPad = (int) $first->format('N') - 1;
    $daysInMonth = (int) $first->format('t');
    $cells = [];
    for ($i = 0; $i < $startPad; $i++) {
        $cells[] = null;
    }
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $cells[] = sprintf('%04d-%02d-%02d', $year, $month, $d);
    }
    while (count($cells) % 7 !== 0) {
        $cells[] = null;
    }
    return $cells;
}

function render_planner_subnav(string $active): void
{
    $tabs = [
        ['planner.php', 'Overview', 'desk'],
        ['planner_notes.php', 'Notes', 'letter'],
        ['planner_goals.php', 'Tasks', 'flag'],
        ['planner_budget.php', 'Budget', 'bank'],
        ['planner_calendar.php', 'Calendar', 'calendar'],
    ];
    ?>
  <nav class="planner-tabs" aria-label="Planner sections">
    <?php foreach ($tabs as [$href, $label, $iconName]): ?>
      <a class="planner-tab<?= $active === $href ? ' is-on' : '' ?>" href="<?= h(url($href)) ?>"><?= icon($iconName, 16) ?><span><?= h($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
    <?php
}

function render_priority_select(string $name, string $value, string $id = ''): void
{
    $id = $id !== '' ? $id : $name;
    ?>
  <select id="<?= h($id) ?>" name="<?= h($name) ?>">
    <?php foreach (planner_priorities() as $key => $label): ?>
      <option value="<?= h($key) ?>" <?= $value === $key ? 'selected' : '' ?>><?= h($label) ?></option>
    <?php endforeach; ?>
  </select>
    <?php
}
