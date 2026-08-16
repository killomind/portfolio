<?php
$refunds = db_all('refunds');
?>
<div class="panel">
  <h3>Возвраты (полные и частичные)</h3>
  <p class="muted small">Возврат инициируется в разделе «Платежи ЮKassa» по оплаченному платежу. Сторно-проводки и чек возврата формируются автоматически.</p>
  <table>
    <thead><tr><th>ID</th><th>Платёж</th><th>Заказ</th><th>Сумма</th><th>Тип</th><th>Статус</th><th>Дата</th></tr></thead>
    <tbody>
      <?php if (!$refunds): ?>
        <tr><td colspan="7" class="center muted">Возвратов нет — выполните частичный или полный возврат в разделе «Платежи ЮKassa»</td></tr>
      <?php endif; ?>
      <?php foreach (array_reverse($refunds) as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td class="mono small">#<?= (int)$r['payment_id'] ?></td>
          <td><?= esc(order_number($r['order_id'])) ?></td>
          <td><b><?= money($r['amount']) ?></b></td>
          <td><?= esc($r['type']) ?></td>
          <td>
            <?php if ($r['status'] === 'done'): ?>
              <span class="badge" style="background:#16a34a">Проведён</span>
            <?php else: ?>
              <span class="badge" style="background:#d97706">В обработке</span>
            <?php endif; ?>
          </td>
          <td><?= esc($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>