<?php
// pages/ai.php — AI-панель
require_login();
$user = current_user();

$ai_result = isset($_SESSION['ai_result']) ? $_SESSION['ai_result'] : null;
unset($_SESSION['ai_result']);

$ai_log = get_ai_log();
// Показываем последние 10 запросов текущего пользователя
$user_log = array_filter($ai_log, function($entry) use ($user) {
    return $entry['user_id'] == $user['id'];
});
usort($user_log, function($a, $b) {
    return strcmp($b['timestamp'], $a['timestamp']);
});
$user_log = array_slice($user_log, 0, 10);
?>

<div class="page-header">
    <h1>🤖 AI-панель</h1>
    <p class="page-subtitle">Управляйте CRM естественным языком</p>
</div>

<div class="ai-container">
    <div class="card ai-query">
        <form action="actions.php" method="post" class="ai-form">
            <input type="hidden" name="action" value="ai_query">
            <textarea name="query" class="input ai-textarea" placeholder="Например: «Какая чистая прибыль была на прошлой неделе?» или «Покажи свободные окна на завтра»"></textarea>
            <button type="submit" class="btn btn--primary ai-submit">Выполнить</button>
        </form>
    </div>

    <?php if ($ai_result): ?>
        <div class="card ai-result">
            <div class="ai-result__header">
                <span class="ai-result__icon">✅</span>
                <h2>Результат</h2>
            </div>
            <p class="ai-result__text"><?php echo nl2br(htmlspecialchars($ai_result['response'])); ?></p>
            <?php if ($ai_result['action_type'] == 'chart' && isset($ai_result['data']['daily'])): ?>
                <div class="chart-container">
                    <?php
                    $daily = $ai_result['data']['daily'];
                    $max_val = 0;
                    foreach ($daily as $d) {
                        $max_val = max($max_val, abs($d['net']), $d['revenue'], $d['expense']);
                    }
                    $max_val = $max_val ?: 1;
                    ?>
                    <div class="profit-chart">
                        <?php foreach ($daily as $date => $values): 
                            $net_height = abs($values['net']) / $max_val * 100;
                            $rev_height = $values['revenue'] / $max_val * 100;
                        ?>
                            <div class="profit-chart__col">
                                <div class="profit-chart__bar profit-chart__bar--net" style="height: <?php echo $net_height; ?>%; background-color: <?php echo $values['net'] >= 0 ? '#4caf50' : '#f44336'; ?>;"></div>
                                <div class="profit-chart__label"><?php echo date('d.m', strtotime($date)); ?></div>
                                <div class="profit-chart__value"><?php echo format_currency($values['net']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($ai_result['action_type'] == 'move_appointment' && isset($ai_result['data']['appointments'])): ?>
                <div class="appointment-list">
                    <?php foreach ($ai_result['data']['appointments'] as $app): 
                        $client = find_client_by_id($app['client_id']);
                        $service = find_service_by_id($app['service_id']);
                    ?>
                        <div class="appointment-item">
                            <span><?php echo htmlspecialchars($client ? $client['name'] : '—'); ?></span>
                            <span><?php echo format_date($app['date']); ?> в <?php echo $app['time']; ?></span>
                            <span><?php echo htmlspecialchars($service ? $service['name'] : '—'); ?></span>
                            <a href="index.php?page=calendar&view=day&date=<?php echo $app['date']; ?>" class="btn btn--small">Перейти к переносу</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($ai_result['action_type'] == 'list' && isset($ai_result['data']['slots'])): ?>
                <div class="free-slots">
                    <?php foreach ($ai_result['data']['slots'] as $slot): ?>
                        <span class="badge badge--free"><?php echo $slot['time']; ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card ai-log">
        <h2>История запросов</h2>
        <?php if (!empty($user_log)): ?>
            <ul class="list">
                <?php foreach ($user_log as $entry): ?>
                    <li class="list-item">
                        <div class="list-item__main">
                            <strong><?php echo htmlspecialchars($entry['query']); ?></strong>
                            <span class="list-item__meta"><?php echo $entry['timestamp']; ?></span>
                        </div>
                        <span class="badge badge--info"><?php echo htmlspecialchars($entry['action_type']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-state">Пока нет запросов.</p>
        <?php endif; ?>
    </div>
</div>