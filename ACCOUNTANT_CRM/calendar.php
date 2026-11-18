<?php
require_once __DIR__ . '/includes/auth.php';
require_once "includes/db.php";
require_once "includes/functions.php";

$pageTitle = "Ημερολόγιο Εργασιών";

/* ---------------- ΒΑΣΙΚΕΣ ΠΑΡΑΜΕΤΡΟΙ ---------------- */
$view  = isset($_GET['view']) ? $_GET['view'] : 'month'; // month | week | day
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date("Y");
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date("n");
$day   = isset($_GET['day'])   ? (int)$_GET['day']   : (int)date("j");

/* Διορθώσεις για μήνες */
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

/* Ελληνικά ονόματα μηνών */
$monthsGreek = [
    1=>"Ιανουάριος",2=>"Φεβρουάριος",3=>"Μάρτιος",4=>"Απρίλιος",
    5=>"Μάιος",6=>"Ιούνιος",7=>"Ιούλιος",8=>"Αύγουστος",
    9=>"Σεπτέμβριος",10=>"Οκτώβριος",11=>"Νοέμβριος",12=>"Δεκέμβριος"
];

$daysGreekShort = ["Δευ", "Τρι", "Τετ", "Πεμ", "Παρ", "Σαβ", "Κυρ"];
$daysGreekFull  = [
    1 => "Δευτέρα",
    2 => "Τρίτη",
    3 => "Τετάρτη",
    4 => "Πέμπτη",
    5 => "Παρασκευή",
    6 => "Σάββατο",
    7 => "Κυριακή"
];

/* Χρήσιμη συνάρτηση για ασφαλή link-παράμετρα */
function calLink($params = []) {
    $base = 'calendar.php';
    $defaults = [
        'view'  => 'month',
        'year'  => (int)date('Y'),
        'month' => (int)date('n'),
        'day'   => (int)date('j'),
    ];
    $p = array_merge($defaults, $_GET, $params);
    return $base . '?' . http_build_query($p);
}

/* ---------------- ΦΟΡΤΩΣΗ ΕΡΓΑΣΙΩΝ ΑΝΑ VIEW ---------------- */
$tasksByDay = [];   // για month & week view
$tasksDay   = [];   // για day view

if ($view === 'month') {

    $firstDayTs   = mktime(0,0,0,$month,1,$year);
    $totalDays    = (int)date("t", $firstDayTs);
    $startWeekday = (int)date("N", $firstDayTs); // 1=Δευτέρα

    $startDate = "$year-$month-01";
    $endDate   = "$year-$month-$totalDays";

    $stmt = $mysqli->prepare("
        SELECT t.*, c.name AS client_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.task_date BETWEEN ? AND ?
        ORDER BY t.task_date ASC
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $d = (int)date("j", strtotime($row['task_date']));
        $tasksByDay[$d][] = $row;
    }

} elseif ($view === 'week') {

    // βάση: συγκεκριμένη ημέρα του επιλεγμένου μήνα/έτους
    $refTs = mktime(0,0,0,$month,$day,$year);
    $dow   = (int)date("N", $refTs); // 1=Δευτέρα ... 7=Κυριακή

    // Δευτέρα της εβδομάδας
    $weekStartTs = strtotime('-'.($dow-1).' days', $refTs);

    $weekDays = [];
    for ($i=0; $i<7; $i++) {
        $ts = strtotime("+$i days", $weekStartTs);
        $dY = (int)date("Y",$ts);
        $dM = (int)date("n",$ts);
        $dD = (int)date("j",$ts);

        $weekDays[] = [
            'ts'    => $ts,
            'year'  => $dY,
            'month' => $dM,
            'day'   => $dD,
        ];
    }

    $startDate = date("Y-m-d", $weekDays[0]['ts']);
    $endDate   = date("Y-m-d", $weekDays[6]['ts']);

    $stmt = $mysqli->prepare("
        SELECT t.*, c.name AS client_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.task_date BETWEEN ? AND ?
        ORDER BY t.task_date ASC
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $d = date("Y-m-d", strtotime($row['task_date']));
        $tasksByDay[$d][] = $row;
    }

} elseif ($view === 'day') {

    // Συγκεκριμένη ημερομηνία
    $currentTs = mktime(0,0,0,$month,$day,$year);
    $currentDate = date("Y-m-d", $currentTs);

    $stmt = $mysqli->prepare("
        SELECT t.*, c.name AS client_name
        FROM tasks t
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.task_date = ?
        ORDER BY t.task_date ASC, t.id DESC
    ");
    $stmt->bind_param("s", $currentDate);
    $stmt->execute();
    $tasksDay = $stmt->get_result();
}

require "includes/header.php";
?>

<style>
/* ============================
   CALENDAR LAYOUT & STYLE
   (ΜΟΝΟ για την σελίδα calendar)
============================ */

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    gap: 10px;
}

.calendar-nav-left,
.calendar-nav-right {
    flex: 0 0 auto;
}

.calendar-title-center h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    text-align: center;
}

.cal-btn {
    display: inline-block;
    padding: 6px 12px;
    background: #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    text-decoration: none;
    color: #111827;
}
.cal-btn:hover {
    background:#d1d5db;
}

/* View tabs (Μήνας / Εβδομάδα / Ημέρα) */
.calendar-view-switch {
    margin: 12px 0 18px 0;
    display:inline-flex;
    border-radius: 999px;
    background:#e5e7eb;
    padding:4px;
}

.calendar-view-switch a {
    padding:6px 14px;
    border-radius:999px;
    font-size:13px;
    text-decoration:none;
    color:#374151;
}

.calendar-view-switch a.active {
    background:#0d6efd;
    color:#fff;
}

/* Μηνιαίο grid */
.calendar-grid {
    display:grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap:6px;
}

.calendar-day-name {
    font-size: 12px;
    font-weight:600;
    text-align:center;
    padding:6px 0;
    color:#4b5563;
}

.calendar-empty {
    background:transparent;
}

/* Κελί ημέρας */
.calendar-cell {
    background:#ffffff;
    border-radius:10px;
    padding:6px;
    min-height:80px;
    box-shadow:0 1px 3px rgba(15,23,42,0.08);
    display:flex;
    flex-direction:column;
}

.calendar-day-number {
    font-size:12px;
    font-weight:600;
    color:#111827;
    margin-bottom:4px;
}

/* Task μέσα στο κελί */
.calendar-task-item {
    display:block;
    font-size:12px;
    padding:3px 4px;
    border-radius:6px;
    margin-bottom:2px;
    background:#f3f4f6;
    color:#111827;
    text-decoration:none;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.calendar-task-item .task-status-square {
    width:8px;
    height:8px;
    border-radius:2px;
    display:inline-block;
    margin-right:4px;
    vertical-align:middle;
}

/* Εβδομαδιαίο grid */
.calendar-week-grid {
    display:grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap:6px;
    margin-top:10px;
}

/* RESPONSIVE – mobile προσαρμογές */
@media (max-width: 900px) {
    .calendar-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .calendar-week-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {

    .calendar-header {
        flex-direction:column;
        align-items:flex-start;
    }
    .calendar-title-center h2 {
        text-align:left;
        font-size:18px;
    }

    .calendar-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .calendar-week-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .calendar-cell {
        min-height:70px;
    }
}

/* Day view table – αφήνουμε την εμφάνιση του table-wrapper ως έχει
   από το global CSS για να ταιριάζει με το υπόλοιπο CRM */
</style>

<div class="page-container">

    <!-- Επικεφαλίδα & επιλογή προβολής -->
    <div class="calendar-header">
        <div class="calendar-nav-left">
            <?php if ($view === 'month'): 
                $prevMonth = $month - 1;
                $prevYear  = $year;
                if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

                $nextMonth = $month + 1;
                $nextYear  = $year;
                if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
            ?>
                <a class="cal-btn" href="<?= calLink(['view'=>'month','month'=>$prevMonth,'year'=>$prevYear]) ?>">◀ Προηγούμενος</a>
            <?php elseif ($view === 'week'): 
                $curTs = mktime(0,0,0,$month,$day,$year);
                $prevTs = strtotime("-7 days", $curTs);
                $nextTs = strtotime("+7 days", $curTs);
            ?>
                <a class="cal-btn" href="<?= calLink([
                        'view'=>'week',
                        'year'=>date("Y",$prevTs),
                        'month'=>date("n",$prevTs),
                        'day'=>date("j",$prevTs)
                ]) ?>">◀ Προηγούμενη</a>
            <?php else: // day ?>
                <?php
                    $curTs = mktime(0,0,0,$month,$day,$year);
                    $prevTs = strtotime("-1 day", $curTs);
                    $nextTs = strtotime("+1 day", $curTs);
                ?>
                <a class="cal-btn" href="<?= calLink([
                        'view'=>'day',
                        'year'=>date("Y",$prevTs),
                        'month'=>date("n",$prevTs),
                        'day'=>date("j",$prevTs)
                ]) ?>">◀ Προηγούμενη</a>
            <?php endif; ?>
        </div>

        <div class="calendar-title-center">
            <?php if ($view === 'month'): ?>
                <h2><?= $monthsGreek[$month] . ' ' . $year ?></h2>
            <?php elseif ($view === 'week'): ?>
                <?php
                    $weekStartLabel = date("d/m/Y", $weekDays[0]['ts']);
                    $weekEndLabel   = date("d/m/Y", $weekDays[6]['ts']);
                ?>
                <h2>Εβδομάδα: <?= $weekStartLabel ?> - <?= $weekEndLabel ?></h2>
            <?php else: // day ?>
                <?php
                    $tsDay  = mktime(0,0,0,$month,$day,$year);
                    $dowDay = (int)date("N",$tsDay);
                ?>
                <h2><?= $daysGreekFull[$dowDay] . ' ' . sprintf("%02d/%02d/%04d",$day,$month,$year) ?></h2>
            <?php endif; ?>
        </div>

        <div class="calendar-nav-right">
            <?php if ($view === 'month'): ?>
                <a class="cal-btn" href="<?= calLink(['view'=>'month','month'=>$nextMonth,'year'=>$nextYear]) ?>">Επόμενος ▶</a>
            <?php elseif ($view === 'week'): ?>
                <a class="cal-btn" href="<?= calLink([
                        'view'=>'week',
                        'year'=>date("Y",$nextTs),
                        'month'=>date("n",$nextTs),
                        'day'=>date("j",$nextTs)
                ]) ?>">Επόμενη ▶</a>
            <?php else: // day ?>
                <a class="cal-btn" href="<?= calLink([
                        'view'=>'day',
                        'year'=>date("Y",$nextTs),
                        'month'=>date("n",$nextTs),
                        'day'=>date("j",$nextTs)
                ]) ?>">Επόμενη ▶</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs επιλογής προβολής -->
    <div class="calendar-view-switch">
        <a href="<?= calLink(['view'=>'month','month'=>$month,'year'=>$year]) ?>" 
           class="<?= $view==='month' ? 'active' : '' ?>">Μήνας</a>
        <a href="<?= calLink(['view'=>'week','year'=>$year,'month'=>$month,'day'=>$day]) ?>" 
           class="<?= $view==='week' ? 'active' : '' ?>">Εβδομάδα</a>
        <a href="<?= calLink(['view'=>'day','year'=>$year,'month'=>$month,'day'=>$day]) ?>" 
           class="<?= $view==='day' ? 'active' : '' ?>">Ημέρα</a>
    </div>

    <?php if ($view === 'month'): ?>

        <div class="calendar-grid">

            <!-- Επικεφαλίδες ημερών -->
            <?php foreach ($daysGreekShort as $d): ?>
                <div class="calendar-day-name"><?= $d ?></div>
            <?php endforeach; ?>

            <!-- Κενά πριν την 1η -->
            <?php
            $firstDayTs   = mktime(0,0,0,$month,1,$year);
            $totalDays    = (int)date("t", $firstDayTs);
            $startWeekday = (int)date("N", $firstDayTs);
            for ($i=1; $i < $startWeekday; $i++): ?>
                <div class="calendar-empty"></div>
            <?php endfor; ?>

            <!-- Ημέρες μήνα -->
            <?php for ($d=1; $d <= $totalDays; $d++): ?>
                <div class="calendar-cell">
                    <div class="calendar-day-number"><?= $d ?></div>

                    <?php if (!empty($tasksByDay[$d])): ?>
                        <?php foreach ($tasksByDay[$d] as $task): 
                            $statusColor = "#fd7e14"; // Σε εξέλιξη
                            if ($task['status']=="Ολοκληρωμένη") $statusColor = "#28a745";
                            if ($task['status']=="Αναμονή εξόφλησης") $statusColor = "#dc3545";
                        ?>
                            <a href="task_form.php?id=<?= $task['id'] ?>" 
                               class="calendar-task-item"
                               title="<?= htmlspecialchars($task['title']) ?>">
                                <span class="task-status-square" style="background: <?= $statusColor ?>;"></span>
                                <?= htmlspecialchars($task['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            <?php endfor; ?>

        </div>

    <?php elseif ($view === 'week'): ?>

        <div class="calendar-week-grid">
            <?php foreach ($weekDays as $dInfo): 
                $ts  = $dInfo['ts'];
                $dY  = $dInfo['year'];
                $dM  = $dInfo['month'];
                $dD  = $dInfo['day'];
                $dow = (int)date("N",$ts);
                $key = date("Y-m-d",$ts);
            ?>
                <div class="calendar-cell">
                    <div class="calendar-day-number">
                        <?= $daysGreekShort[$dow-1] . ' ' . sprintf("%02d/%02d",$dD,$dM) ?>
                    </div>

                    <?php if (!empty($tasksByDay[$key])): ?>
                        <?php foreach ($tasksByDay[$key] as $task): 
                            $statusColor = "#fd7e14";
                            if ($task['status']=="Ολοκληρωμένη") $statusColor = "#28a745";
                            if ($task['status']=="Αναμονή εξόφλησης") $statusColor = "#dc3545";
                        ?>
                            <a href="task_form.php?id=<?= $task['id'] ?>" 
                               class="calendar-task-item"
                               title="<?= htmlspecialchars($task['title']) ?>">
                                <span class="task-status-square" style="background: <?= $statusColor ?>;"></span>
                                <?= htmlspecialchars($task['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

    <?php else: /* DAY VIEW */ ?>

        <div class="table-wrapper" style="margin-top:15px;">
            <table>
                <thead>
                    <tr>
                        <th>Ώρα</th>
                        <th>Τίτλος</th>
                        <th>Πελάτης</th>
                        <th>Κατάσταση</th>
                        <th>Αμοιβή</th>
                        <th>Είσπραξη</th>
                        <th>Μέσο είσπραξης</th>
                        <th>Ενέργειες</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($tasksDay && $tasksDay->num_rows > 0): ?>
                    <?php while ($t = $tasksDay->fetch_assoc()):
                        $statusColor = "#fd7e14";
                        if ($t['status']=="Ολοκληρωμένη") $statusColor = "#28a745";
                        if ($t['status']=="Αναμονή εξόφλησης") $statusColor = "#dc3545";
                    ?>
                        <tr>
                            <td>—</td>
                            <td class="tasks-table-title">
                                <span class="task-status-square" style="background: <?= $statusColor ?>;"></span>
                                <?= htmlspecialchars($t['title']) ?>
                            </td>
                            <td><?= htmlspecialchars($t['client_name']) ?></td>
                            <td><?= htmlspecialchars($t['status']) ?></td>
                            <td><?= number_format($t['fee'],2,',','.') ?> €</td>
                            <td><?= number_format($t['collected'],2,',','.') ?> €</td>
                            <td><?= htmlspecialchars((string)($t['payment_method_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            <td class="task-actions">
                                <a class="icon-btn gray" href="task_form.php?id=<?= $t['id'] ?>">👁</a>
                                <a class="icon-btn blue" href="task_form.php?id=<?= $t['id'] ?>">✏️</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">Δεν υπάρχουν εργασίες για αυτή την ημέρα.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<?php require "includes/footer.php"; ?>
