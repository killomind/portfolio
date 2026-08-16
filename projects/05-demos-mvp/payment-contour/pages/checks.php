<?php
$checks = db_all('checks');
?>
<div class="panel">
  <h3>Кассовые чеки (облачная ККТ)</h3>
  <p class="muted small">Backend-часть кассовой интеграции: чек отправляется в облачную кассу после успешной оплаты и после возврата. Фискальную экспертизу по ФФД обеспечивает заказчик.</p>
  <table>
    <thead><tr><th>ID</th><th>Платёж</th><th>Заказ</th><th>Сумма</th><th>Тип</th><th>ФН</th><th>Статус ФФД</th><th>Дата</th></tr></thead>
    <tbody>
      <?php if (!$checks): ?>
        <tr><td colspan="8" class="center muted">Чеков нет</td></tr>
      <?php endif; ?>
      <?php foreach (array_reverse($checks) as $c): ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td>#<?= (int)$c['payment_id'] ?></td>
          <td><?= esc(order_number($c['order_id'])) ?></td>
          <td><b><?= money($c['amount']) ?></b></td>
          <td><?= $c['type'] === 'sale' ? 'Продажа' : 'Возврат' ?></td>
          <td class="mono"><?= esc($c['fn']) ?></td>
          <td><span class="badge" style="background:#16a34a"><?= esc($c['fiscal']) ?></span></td>
          <td><?= esc($c['at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>