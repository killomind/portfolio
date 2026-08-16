<?php
$orders = db_all('orders');
?>
<div class="panel">
  <h3>Заказы торговой платформы</h3>
  <p class="muted small">Состав и сумма заказа фиксируются на момент создания; при оплате сохраняется снимок заказа.</p>
  <table>
    <thead><tr><th>Заказ</th><th>Клиент</th><th>Состав</th><th>Сумма</th><th>Статус</th><th>Оплата</th></tr></thead>
    <tbody>
      <?php foreach (array_reverse($orders) as $o):
        $items = [];
        foreach ($o['items'] as $it) $items[] = $it['name'] . ' ×' . $it['qty'];
        $pay = null;
        foreach (db_all('payments') as $p) if ((int)$p['order_id'] === (int)$o['id']) $pay = $p;
      ?>
        <tr>
          <td><?= esc(order_number($o['id'])) ?></td>
          <td><?= esc(client_name($o['client_id'])) ?></td>
          <td class="small muted"><?= esc(implode('; ', $items)) ?></td>
          <td><b><?= money(order_total($o)) ?></b></td>
          <td>
            <?php if ($o['status'] === 'paid'): ?>
              <span class="badge" style="background:#16a34a">Оплачен</span>
            <?php else: ?>
              <span class="badge" style="background:#6b7280">Ожидает оплаты</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($pay && $pay['status'] === 'succeeded'): ?>
              <span class="badge" style="background:#d1fae5;color:#065f46"><?= esc($pay['payment_key']) ?></span>
            <?php elseif ($pay): ?>
              <form method="post" action="index.php" style="display:inline">
                <input type="hidden" name="act" value="fire_success">
                <input type="hidden" name="payment_id" value="<?= (int)$pay['id'] ?>">
                <button class="btn btn-primary btn-sm" type="submit">Webhook: оплачен</button>
              </form>
            <?php else: ?>
              <form method="post" action="index.php" style="display:inline">
                <input type="hidden" name="act" value="create_payment">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button class="btn btn-sm" type="submit">Создать платёж</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>