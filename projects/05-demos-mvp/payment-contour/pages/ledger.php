<?php
$ledger = db_all('ledger');
$bankIn = 0; $bankOut = 0;
foreach ($ledger as $e) {
  if ($e['debit'] === '51') $bankIn += (float)$e['amount'];
  if ($e['credit'] === '51') $bankOut += (float)$e['amount'];
}
$received = 0;
foreach (db_all('payments') as $p) if ($p['status'] === 'succeeded' || $p['status'] === 'refunded') $received += (float)$p['amount'];
foreach (db_all('refunds') as $r) if ($r['status'] === 'done') $received -= (float)$r['amount'];
$balanced = abs($bankIn - $bankOut - $received) < 0.01;
?>
<div class="panel">
  <h3>Регистр проводок (двойная запись)</h3>
  <p class="muted small">
    Счета: 51 — расчётный счёт, 62 — расчёты с покупателями, 90 — выручка, 68 — НДС.
    Каждый платеж порождает три проводки; возвраты — сторно. Контроль: поступления на 51 = оплаты − возвраты.
  </p>
  <?php if ($balanced): ?>
    <div class="ok-bar">Контроль сходится: остаток на счёте 51 = <?= money($bankIn - $bankOut) ?> ✓</div>
  <?php else: ?>
    <div class="err-bar">Контроль НЕ сходится — см. таблицу</div>
  <?php endif; ?>
  <table>
    <thead><tr><th>ID</th><th>Дата</th><th>Дебет</th><th>Кредит</th><th>Сумма</th><th>Основание</th></tr></thead>
    <tbody>
      <?php if (!$ledger): ?>
        <tr><td colspan="6" class="center muted">Нет проводок</td></tr>
      <?php endif; ?>
      <?php foreach (array_reverse($ledger) as $e): ?>
        <tr>
          <td><?= (int)$e['id'] ?></td>
          <td><?= esc($e['at']) ?></td>
          <td><b><?= esc($e['debit']) ?></b> <?= esc(ACCOUNTS[$e['debit']] ?? '') ?></td>
          <td><b><?= esc($e['credit']) ?></b> <?= esc(ACCOUNTS[$e['credit']] ?? '') ?></td>
          <td><?= money($e['amount']) ?></td>
          <td class="small"><?= esc($e['description']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="two-col">
  <div class="panel">
    <h3>Обороты по счетам</h3>
    <table>
      <thead><tr><th>Счёт</th><th>Дебет</th><th>Кредит</th></tr></thead>
      <tbody>
        <?php foreach (array_keys(ACCOUNTS) as $acc):
          $d = 0; $c = 0;
          foreach ($ledger as $e) {
            if ($e['debit'] === $acc) $d += (float)$e['amount'];
            if ($e['credit'] === $acc) $c += (float)$e['amount'];
          }
        ?>
          <tr>
            <td><b><?= esc($acc) ?></b> <?= esc(ACCOUNTS[$acc]) ?></td>
            <td><?= money($d) ?></td>
            <td><?= money($c) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <h3>Правила расчётов</h3>
    <ul class="plain">
      <li>Суммы хранятся в копейках (целочисленно), округление детерминировано: <code>round(amount × 100) / 100</code>.</li>
      <li>НДС выделяется из суммы по ставке: <code>amount × 20 / 120</code>.</li>
      <li>Частичный возврат пропорционален сумме платежа, сторно — зеркально проводкам.</li>
      <li>Любая операция — в одной транзакции: либо все проводки записаны, либо ни одной.</li>
    </ul>
  </div>
</div>