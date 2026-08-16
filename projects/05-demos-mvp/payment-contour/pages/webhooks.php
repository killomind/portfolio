<?php
$webhooks = db_all('webhooks');
?>
<div class="panel">
  <h3>Журнал webhook-событий</h3>
  <p class="muted small">
    Обработка ведётся по идемпотентному ключу события (уникальный индекс). Повторная доставка при конфликте ключа
    завершается как успех без повторных начислений. Обработка — в двух транзакционных шагах («принято» → «проведено»):
    перезапуск сервиса посреди обработки откатывает незавершённую транзакцию, повторная доставка обрабатывается как уже принятая.
  </p>
  <table>
    <thead><tr><th>ID</th><th>Событие</th><th>Ключ платежа</th><th>Идемпотентный ключ</th><th>Статус</th><th>Попытки</th><th>Время</th><th>Заметка</th></tr></thead>
    <tbody>
      <?php if (!$webhooks): ?>
        <tr><td colspan="8" class="center muted">Событий нет — имитируйте webhook в разделе «Платежи ЮKassa»</td></tr>
      <?php endif; ?>
      <?php foreach (array_reverse($webhooks) as $w): ?>
        <tr>
          <td><?= (int)$w['id'] ?></td>
          <td class="mono"><?= esc($w['event']) ?></td>
          <td class="mono small"><?= esc($w['payment_key']) ?></td>
          <td class="mono small"><?= esc($w['idempotency_key']) ?></td>
          <td><?= status_badge($w['status'], WEBHOOK_STATUS, WEBHOOK_COLOR) ?></td>
          <td><?= (int)$w['attempts'] ?></td>
          <td><?= esc($w['at']) ?></td>
          <td class="small muted"><?= esc($w['note']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h3>Механика идемпотентности</h3>
  <div class="steps">
    <div class="step"><b>1. Принято</b><span>Запись о событии с уникальным ключом создаётся первой, в отдельной транзакции. Конфликт ключа → ответ «уже обработано».</span></div>
    <div class="step"><b>2. Проведено</b><span>Платёж → succeeded, формируются проводки (Дт 51/Кт 62, 62/90, 90/68), снимок заказа, закрытие обязательства, чек ККТ.</span></div>
    <div class="step"><b>3. Откат при сбое</b><span>Незавершённая транзакция откатывается целиком. Повторная доставка находит запись «принято» и повторяет «проведено» без дублей.</span></div>
    <div class="step"><b>4. Дубликаты</b><span>Один и тот же идемпотентный ключ обрабатывается один раз; остальные доставки помечаются как «Дубликат».</span></div>
  </div>
</div>