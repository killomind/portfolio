<?php
$snapshots = db_all('snapshots');
?>
<div class="panel">
  <h3>Снимки заказов</h3>
  <p class="muted small">На момент оплаты фиксируется неизменяемая копия заказа (состав, цены, статус) — основа детерминированных финансовых расчётов и разрешения спорных ситуаций.</p>
  <table>
    <thead><tr><th>ID</th><th>Заказ</th><th>Клиент</th><th>Сумма</th><th>Состав на момент оплаты</th><th>Платёж</th><th>Дата</th></tr></thead>
    <tbody>
      <?php if (!$snapshots): ?>
        <tr><td colspan="7" class="center muted">Снимков нет — снимок создаётся при обработке webhook об оплате</td></tr>
      <?php endif; ?>
      <?php foreach (array_reverse($snapshots) as $s):
        $items = [];
        foreach ($s['items'] as $it) $items[] = $it['name'] . ' ×' . $it['qty'] . ' — ' . money($it['price']);
      ?>
        <tr>
          <td><?= (int)$s['id'] ?></td>
          <td><?= esc($s['order_number']) ?></td>
          <td><?= esc(client_name($s['client_id'])) ?></td>
          <td><b><?= money($s['amount']) ?></b></td>
          <td class="small muted"><?= esc(implode('; ', $items)) ?></td>
          <td>#<?= (int)$s['payment_id'] ?></td>
          <td><?= esc($s['at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>