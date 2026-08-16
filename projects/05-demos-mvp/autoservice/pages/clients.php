<?php
$u = current_user();
$clients = db_all('clients');
$orders = db_all('orders');
$cars = db_all('cars');

$rows = [];
foreach ($clients as $c) {
  $cc = count(array_filter($cars, function ($x) use ($c) { return (int)$x['client_id'] === (int)$c['id']; }));
  $co = count(array_filter($orders, function ($x) use ($c) { return (int)$x['client_id'] === (int)$c['id']; }));
  $rows[] = ['client' => $c, 'cars' => $cc, 'orders' => $co];
}
?>
<div class="panel">
  <div class="panel-head"><h2>Клиенты</h2></div>
  <table>
    <tr><th>Имя</th><th>Телефон</th><th>Email</th><th>Авто</th><th>Заказов</th><th>Добавлен</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= esc($r['client']['name']) ?></td>
        <td><?= esc($r['client']['phone']) ?></td>
        <td><?= esc($r['client']['email']) ?></td>
        <td><?= $r['cars'] ?></td>
        <td><?= $r['orders'] ?></td>
        <td><?= esc($r['client']['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel">
  <h2>Добавить клиента</h2>
  <form method="post" action="index.php" class="form-grid">
    <input type="hidden" name="act" value="client_save">
    <label>Имя <input type="text" name="name" required></label>
    <label>Телефон <input type="text" name="phone" placeholder="+7 (900) 000-00-00"></label>
    <label>Email <input type="email" name="email"></label>
    <button class="btn" type="submit">Добавить</button>
  </form>
</div>
