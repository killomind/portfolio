<?php
require_role(['admin', 'manager', 'operator']);
$u = current_user();
$guests = db_all('guests');
$bookings = db_all('bookings');
usort($guests, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
?>
<div class="panel">
  <div class="panel-head"><h2>Гости</h2></div>
  <table>
    <tr><th>Имя</th><th>Телефон</th><th>E-mail</th><th>Броней</th><th>Дата добавления</th></tr>
    <?php if (!$guests): ?>
      <tr><td colspan="5" class="empty">Гостей пока нет</td></tr>
    <?php endif; ?>
    <?php foreach ($guests as $g): ?>
      <?php $cnt = count(array_filter($bookings, function ($b) use ($g) { return (int)$b['guest_id'] === (int)$g['id']; })); ?>
      <tr>
        <td><strong><?= esc($g['name']) ?></strong><?= $g['notes'] ? '<div class="muted small">' . esc($g['notes']) . '</div>' : '' ?></td>
        <td><?= esc($g['phone']) ?></td>
        <td><?= esc($g['email']) ?></td>
        <td><?= $cnt ?></td>
        <td><?= esc(date('d.m.Y', strtotime($g['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel">
  <h2>Добавить гостя</h2>
  <form method="post" action="index.php" class="form-grid">
    <input type="hidden" name="act" value="guest_save">
    <label>Имя <input type="text" name="name" required></label>
    <label>Телефон <input type="text" name="phone"></label>
    <label>E-mail <input type="email" name="email"></label>
    <label>Заметки <input type="text" name="notes" placeholder="Предпочтения, дни рождения..."></label>
    <button class="btn" type="submit">Добавить</button>
  </form>
</div>
