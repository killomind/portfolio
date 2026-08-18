<?php
// pages/clients.php — список клиентов с фильтром
require_login();
$user = current_user();
if (!in_array($user['role'], ['admin', 'manager', 'operator'])) {
    header('Location: index.php?page=calendar');
    exit;
}

$clients = get_clients();
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
if ($filter) {
    $clients = array_filter($clients, function($c) use ($filter) {
        return mb_stripos($c['name'], $filter, 0, 'UTF-8') !== false ||
               mb_stripos($c['phone'], $filter, 0, 'UTF-8') !== false ||
               mb_stripos($c['email'], $filter, 0, 'UTF-8') !== false;
    });
}
?>

<div class="page-header">
    <h1>Клиенты</h1>
    <p class="page-subtitle">База клиентов компании</p>
</div>

<div class="filter-bar">
    <form method="get" action="index.php" class="filter-form">
        <input type="hidden" name="page" value="clients">
        <input type="text" name="filter" value="<?php echo htmlspecialchars($filter); ?>" placeholder="Поиск по имени, телефону, email..." class="input">
        <button type="submit" class="btn btn--primary">Найти</button>
        <?php if ($filter): ?>
            <a href="index.php?page=clients" class="btn btn--link">Сбросить</a>
        <?php endif; ?>
    </form>
</div>

<div class="clients-grid">
    <?php foreach ($clients as $client): ?>
        <div class="card client-card">
            <div class="client-card__header">
                <h3><?php echo htmlspecialchars($client['name']); ?></h3>
                <a href="index.php?page=client_card&id=<?php echo $client['id']; ?>" class="btn btn--small">Открыть</a>
            </div>
            <div class="client-card__info">
                <div class="client-card__row">
                    <span class="client-card__label">📞</span>
                    <span><?php echo htmlspecialchars($client['phone']); ?></span>
                </div>
                <div class="client-card__row">
                    <span class="client-card__label">✉️</span>
                    <span><?php echo htmlspecialchars($client['email']); ?></span>
                </div>
                <div class="client-card__row">
                    <span class="client-card__label">📅</span>
                    <span>Визитов: <?php echo count($client['visits']); ?></span>
                </div>
                <?php if (!empty($client['visits'])): 
                    $total = array_sum(array_column($client['visits'], 'amount'));
                ?>
                    <div class="client-card__row">
                        <span class="client-card__label">💰</span>
                        <span>Сумма: <?php echo format_currency($total); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($clients)): ?>
        <p class="empty-state">Клиенты не найдены.</p>
    <?php endif; ?>
</div>