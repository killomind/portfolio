<?php
$obligations = db_all('obligations');
$open = array_filter($obligations, function ($o) { return $o['status'] === 'open'; });
$closed = array_filter($obligations, function ($o) { return $o['status'] === 'closed'; });
$openSum = 0; foreach ($open as $o) $openSum += (float)$o['amount'];
$closedSum = 0; foreach ($closed as $o) $closedSum += (float)$o['amount'];
?>
<div class="cards">
  <div class="metric-card">
    <div class="metric-label">Открытые обязательства</div>
    <div class="metric-value orange"><?= money($openSum) ?></div>
    <div class="metric-sub"><?= count($open) ?> заказов ожидают оплаты</div>
  </div>
  <div class="metric-card">
    <div class="metric-label">Закрытые обязательства</div>
    <div class="metric-value"><?= money($closedSum) ?></div>
    <div class="metric-sub"><?= count($closed) ?> заказов оплачено</div>
  </div>
</div>

<div class="panel">
  <h3>Реестр обязательств</h3>
  <p class="muted small">Обязательство создаётся при создании заказа, закрывается при успешной оплате (webhook). Сверка обязательств с регистром проводок — контроль целостности.</p>
  <table>
    <thead><tr><th>ID</th><th>Заказ</th><th>Клиент</th><th>Тип</th><th>Сумма</th><th>Статус</th><th>Закрыт платежом</th><th>Дата</th></tr></thead>
    <tbody>
      <?php if (!$obligations): ?>
        <tr><td colspan="8" class="center muted">Обязательств нет</td></tr>
      <?php endif; ?>
      <?php foreach (array_reverse($obligations) as $o): ?>
        <tr>
          <td><?= (int)$o['id'] ?></td>
          <td><?= esc($o['order_number']) ?></td>
          <td><?= esc(client_name($o['client_id'])) ?></td>
          <td><?= $o['type'] === 'debt' ? 'Дебиторская задолженность' : esc($o['type']) ?></td>
          <td><b><?= money($o['amount']) ?></b></td>
          <td>
            <?php if ($o['status'] === 'open'): ?>
              <span class="badge" style="background:#d97706">Открыто</span>
            <?php else: ?>
              <span class="badge" style="background:#16a34a">Закрыто</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (isset($o['closed_payment_id'])): ?>#<?= (int)$o['closed_payment_id'] ?><?php else: ?>—<?php endif; ?>
          </td>
          <td class="small"><?= esc($o['created_at']) ?><?php if (isset($o['closed_at'])): ?><br>→ <?= esc($o['closed_at']) ?><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>