<?php
// pages/dashboard.php — дашборд владельца/руководителя
require_login();
$user = current_user();
if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: index.php?page=calendar');
    exit;
}

$payments = get_payments();
$expenses = get_expenses();
$appointments = get_appointments();
$clients = get_clients();
$services = get_services();

// Выручка и прибыль за текущую неделю
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$week_revenue = 0;
$week_expenses = 0;
foreach ($payments as $p) {
    if ($p['date'] >= $week_start && $p['date'] <= $week_end && ($p['type'] == 'service' || $p['type'] == 'product')) {
        $week_revenue += $p['amount'];
    }
}
foreach ($expenses as $e) {
    if ($e['date'] >= $week_start && $e['date'] <= $week_end) {
        $week_expenses += $e['amount'];
    }
}
$week_net = $week_revenue - $week_expenses;

// Всего записей на неделю
$week_appointments = array_filter($appointments, function($a) use ($week_start, $week_end) {
    return $a['date'] >= $week_start && $a['date'] <= $week_end && $a['status'] != 'cancelled';
});
$total_week_appointments = count($week_appointments);

// Загрузка по дням недели (для графика)
$days_of_week = [];
$period = new DatePeriod(
    new DateTime($week_start),
    new DateInterval('P1D'),
    new DateTime($week_end . ' +1 day')
);
foreach ($period as $date) {
    $d = $date->format('Y-m-d');
    $count = 0;
    foreach ($appointments as $a) {
        if ($a['date'] == $d && $a['status'] != 'cancelled') $count++;
    }
    $days_of_week[] = [
        'date' => $d,
        'day' => date('D', strtotime($d)),
        'count' => $count
    ];
}

// Последние записи
usort($appointments, function($a, $b) {
    return strcmp($b['date'] . $b['time'], $a['date'] . $a['time']);
});
$recent_appointments = array_slice($appointments, 0, 5);

// Сводка по услугам
$service_stats = [];
foreach ($appointments as $a) {
    $sid = $a['service_id'];
    if (!isset($service_stats[$sid])) {
        $service_stats[$sid] = ['count' => 0, 'revenue' => 0];
    }
    $service_stats[$sid]['count']++;
    $service_stats[$sid]['revenue'] += find_service_by_id($sid)['price'] ?? 0;
}
arsort($service_stats);
$top_services = array_slice($service_stats, 0, 3, true);
?>

<div class="page-header">
    <h1>Дашборд</h1>
    <p class="page-subtitle">Обзор бизнеса за текущую неделю</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__icon">💰</div>
        <div class="stat-card__label">Выручка за неделю</div>
        <div class="stat-card__value"><?php echo format_currency($week_revenue); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon">📈</div>
        <div class="stat-card__label">Чистая прибыль</div>
        <div class="stat-card__value"><?php echo format_currency($week_net); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon">📅</div>
        <div class="stat-card__label">Записей на неделю</div>
        <div class="stat-card__value"><?php echo $total_week_appointments; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon">👥</div>
        <div class="stat-card__label">Всего клиентов</div>
        <div class="stat-card__value"><?php echo count($clients); ?></div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h2>Загрузка графика на неделю</h2>
        <div class="bar-chart">
            <?php foreach ($days_of_week as $d): 
                $max_count = max(array_column($days_of_week, 'count')) ?: 1;
                $height = ($d['count'] / $max_count) * 100;
            ?>
                <div class="bar-chart__item">
                    <div class="bar-chart__bar" style="height: <?php echo $height; ?>%"></div>
                    <div class="bar-chart__label"><?php echo $d['day']; ?></div>
                    <div class="bar-chart__value"><?php echo $d['count']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>Последние записи</h2>
        <ul class="list">
            <?php foreach ($recent_appointments as $a): 
                $client = find_client_by_id($a['client_id']);
                $service = find_service_by_id($a['service_id']);
            ?>
                <li class="list-item">
                    <div class="list-item__main">
                        <strong><?php echo htmlspecialchars($client ? $client['name'] : '—'); ?></strong>
                        <span class="list-item__meta"><?php echo format_date($a['date']); ?> в <?php echo $a['time']; ?></span>
                    </div>
                    <span class="badge badge--service"><?php echo htmlspecialchars($service ? $service['name'] : '—'); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card">
        <h2>Топ услуг</h2>
        <ul class="list">
            <?php foreach ($top_services as $sid => $stat): 
                $service = find_service_by_id($sid);
            ?>
                <li class="list-item">
                    <div class="list-item__main">
                        <strong><?php echo htmlspecialchars($service ? $service['name'] : '—'); ?></strong>
                        <span class="list-item__meta"><?php echo $stat['count']; ?> записей</span>
                    </div>
                    <span class="badge badge--money"><?php echo format_currency($stat['revenue']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="ai-hint">
    <p>💡 Хотите быстрый анализ? Перейдите в <a href="index.php?page=ai">AI-панель</a> и спросите, например: «Какая чистая прибыль была на прошлой неделе?»</p>
</div>