<?php
// pages/client_card.php — карточка клиента
require_login();
$user = current_user();
if (!in_array($user['role'], ['admin', 'manager', 'operator'])) {
    header('Location: index.php?page=calendar');
    exit;
}

$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$client = find_client_by_id($client_id);
if (!$client) {
    header('Location: index.php?page=clients');
    exit;
}

$appointments = get_appointments();
$client_appointments = array_filter($appointments, function($a) use ($client_id) {
    return $a['client_id'] == $client_id;
});
usort($client_appointments, function($a, $b) {
    return strcmp($b['date'] . $b['time'], $a['date'] . $a['time']);
});
$total_spent = array_sum(array_column($client['visits'], 'amount'));
?>

<div class="page-header">
    <a href="index.php?page=clients" class="btn btn--link">← Назад к списку</a>
    <h1><?php echo htmlspecialchars($client['name']); ?></h1>
    <p class="page-subtitle">Карточка клиента</p>
</div>

<div class="card client-profile">
    <div class="client-profile__info">
        <div class="info-row">
            <span class="info-label">Телефон:</span>
            <span><?php echo htmlspecialchars($client['phone']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span><?php echo htmlspecialchars($client['email']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Всего визитов:</span>
            <span><?php echo count($client['visits']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Общая сумма:</span>
            <span class="text-success"><?php echo format_currency($total_spent); ?></span>
        </div>
    </div>
</div>

<div class="card">
    <h2>История визитов</h2>
    <?php if (!empty($client['visits'])): ?>
        <div class="timeline">
            <?php foreach ($client['visits'] as $visit): ?>
                <div class="timeline-item">
                    <div class="timeline-item__date"><?php echo format_date($visit['date']); ?></div>
                    <div class="timeline-item__service"><?php echo htmlspecialchars($visit['service']); ?></div>
                    <div class="timeline-item__amount"><?php echo format_currency($visit['amount']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-state">Пока нет визитов.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Предстоящие записи</h2>
    <?php if (!empty($client_appointments)): ?>
        <ul class="list">
            <?php foreach ($client_appointments as $app): 
                $service = find_service_by_id($app['service_id']);
                $is_past = $app['date'] < date('Y-m-d');
            ?>
                <li class="list-item">
                    <div class="list-item__main">
                        <strong><?php echo format_date($app['date']); ?> в <?php echo $app['time']; ?></strong>
                        <span class="list-item__meta"><?php echo htmlspecialchars($service ? $service['name'] : '—'); ?></span>
                    </div>
                    <span class="badge badge--<?php echo $app['status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                        <?php echo $app['status'] == 'confirmed' ? 'Подтверждена' : 'Ожидает'; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="empty-state">Нет предстоящих записей.</p>
    <?php endif; ?>
</div>