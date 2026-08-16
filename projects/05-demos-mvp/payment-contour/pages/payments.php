<?php
$payments = db_all('payments');
$webhooks = db_all('webhooks');
?>
<div class="panel">
  <h3>Платежи ЮKassa</h3>
  <p class="muted small">Ключ платежа провайдера — уникальный идентификатор операции. Действия: имитация webhook об успехе, повторная доставка (идемпотентность), сбой посреди обработки.</p>
  <table>
    <thead><tr><th>ID</th><th>Ключ платежа</th><th>Заказ</th><th>Сумма</th><th>Статус</th><th>Действия</th></tr></thead>
    <tbody>
      <?php if (!$payments): ?>
        <tr><td colspan="6" class="center muted">Платежей нет — создайте платёж по заказу</td></tr>
      <?php endif; ?>
      <?php foreach (array_reverse($payments) as $p):
        $hasError = false;
        foreach ($webhooks as $w) {
          if ($w['payment_key'] === $p['payment_key'] && $w['status'] === 'error') { $hasError = true; break; }
        }
      ?>
        <tr>
          <td><?= (int)$p['id'] ?></td>
          <td class="mono"><?= esc($p['payment_key']) ?></td>
          <td><?= esc(order_number($p['order_id'])) ?></td>
          <td><b><?= money($p['amount']) ?></b></td>
          <td><?= status_badge($p['status'], PAYMENT_STATUS, PAYMENT_COLOR) ?></td>
          <td>
            <?php if ($p['status'] === 'pending'): ?>
              <form method="post" action="index.php" style="display:inline">
                <input type="hidden" name="act" value="fire_success">
                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-primary btn-sm" type="submit">Webhook: успех</button>
              </form>
              <form method="post" action="index.php" style="display:inline">
                <input type="hidden" name="act" value="fire_error">
                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm" type="submit">Сбой + повтор</button>
              </form>
            <?php elseif ($p['status'] === 'succeeded'): ?>
              <form method="post" action="index.php" style="display:inline">
                <input type="hidden" name="act" value="fire_duplicate">
                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm" type="submit">Повторный webhook</button>
              </form>
              <form method="post" action="index.php" style="display:inline">
                <input type="hidden" name="act" value="refund">
                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="mode" value="partial">
                <button class="btn btn-sm" type="submit">Частичный возврат</button>
              </form>
              <form method="post" action="index.php" style="display:inline">
                <input type="hidden" name="act" value="refund">
                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="mode" value="full">
                <button class="btn btn-sm" type="submit">Полный возврат</button>
              </form>
            <?php endif; ?>
            <?php if ($hasError): ?>
              <span class="badge" style="background:#fee2e2;color:#991b1b">был сбой</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>