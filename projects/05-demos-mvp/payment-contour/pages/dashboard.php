<?php
$payments = db_all('payments');
$orders = db_all('orders');
$ledger = db_all('ledger');
$refunds = db_all('refunds');

$paid = array_filter($payments, function ($p) { return $p['status'] === 'succeeded'; });
$pending = array_filter($payments, function ($p) { return $p['status'] === 'pending'; });
$dup = array_filter(db_all('webhooks'), function ($w) { return $w['status'] === 'duplicate'; });

$paidSum = 0;
foreach ($paid as $p) $paidSum += (float)$p['amount'];
$pendingSum = 0;
foreach ($pending as $p) $pendingSum += (float)$p['amount'];
$refundSum = 0;
foreach ($refunds as $r) if ($r['status'] === 'done') $refundSum += (float)$r['amount'];

$bankBalance = 0;
foreach ($ledger as $e) {
  if ($e['debit'] === '51') $bankBalance += (float)$e['amount'];
  if ($e['credit'] === '51') $bankBalance -= (float)$e['amount'];
}
$openObligations = array_filter(db_all('obligations'), function ($o) { return $o['status'] === 'open'; });
?>
<div class="cards">
  <div class="metric-card">
    <div class="metric-label">Оплачено (всего)</div>
    <div class="metric-value"><?= money($paidSum) ?></div>
    <div class="metric-sub"><?= count($paid) ?> платежей · возвраты <?= money($refundSum) ?></div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Ожидает оплаты</div>
    <div class="metric-value orange"><?= money($pendingSum) ?></div>
    <div class="metric-sub"><?= count($pending) ?> платежей в ЮKassa</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Остаток на счёте 51</div>
    <div class="metric-value"><?= money($bankBalance) ?></div>
    <div class="metric-sub">сходится с регистром проводок</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Дублей webhook</div>
    <div class="metric-value gray"><?= count($dup) ?></div>
    <div class="metric-sub">перехвачено по идемпотентному ключу</div>
  </div>
</div>

<div class="two-col">
  <div class="panel">
    <h3>Контур оплаты (схема)</h3>
    <div class="flow">
      <div class="flow-item"><b>Заказ</b><span>фиксация состава и суммы</span></div>
      <div class="flow-arrow">→</div>
      <div class="flow-item"><b>ЮKassa</b><span>создание платежа, ожидание</span></div>
      <div class="flow-arrow">→</div>
      <div class="flow-item"><b>Webhook</b><span>payment.succeeded</span></div>
      <div class="flow-arrow">→</div>
      <div class="flow-item"><b>Проводки</b><span>Дт 51 / Кт 62 · 62 / 90 · 90 / 68</span></div>
      <div class="flow-arrow">→</div>
      <div class="flow-item"><b>ККТ</b><span>чек в облачную кассу</span></div>
    </div>
    <p class="muted small">Обработка webhook идемпотентна: уникальный ключ события + транзакционное «принято → проведено». Повторная доставка не дублирует проводки.</p>
  </div>

  <div class="panel">
    <h3>Открытые обязательства</h3>
    <table>
      <thead><tr><th>Заказ</th><th>Клиент</th><th>Сумма</th><th>Статус</th></tr></thead>
      <tbody>
        <?php if (!$openObligations): ?>
          <tr><td colspan="4" class="center muted">Нет открытых обязательств</td></tr>
        <?php else: foreach ($openObligations as $o): ?>
          <tr>
            <td><?= esc($o['order_number']) ?></td>
            <td><?= esc(client_name($o['client_id'])) ?></td>
            <td><?= money($o['amount']) ?></td>
            <td><span class="badge" style="background:#d97706">Открыто</span></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <h3>Последние проводки</h3>
  <table>
    <thead><tr><th>Дата</th><th>Дт</th><th>Кт</th><th>Сумма</th><th>Основание</th></tr></thead>
    <tbody>
      <?php
      $rows = array_slice(array_reverse($ledger), 0, 6);
      if (!$rows) echo '<tr><td colspan="5" class="center muted">Нет проводок</td></tr>';
      foreach ($rows as $e): ?>
        <tr>
          <td><?= esc($e['at']) ?></td>
          <td><?= esc($e['debit']) ?> · <?= esc(ACCOUNTS[$e['debit']]) ?></td>
          <td><?= esc($e['credit']) ?> · <?= esc(ACCOUNTS[$e['credit']]) ?></td>
          <td><?= money($e['amount']) ?></td>
          <td><?= esc($e['description']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>