<?php
require_role(['admin', 'manager']);
$u = current_user();
$services = db_all('services');
$perLabel = ['once' => 'за бронь', 'night' => 'за ночь', 'person_night' => 'за чел./ночь'];
?>
<div class="panel">
  <div class="panel-head"><h2>Дополнительные услуги</h2></div>
  <table>
    <tr><th>Название</th><th>Расчёт</th><th>Цена</th><th>Описание</th>
      <?php if ($u['role'] === 'admin'): ?><th></th><?php endif; ?></tr>
    <?php foreach ($services as $s): ?>
      <tr>
        <td><?= esc($s['name']) ?></td>
        <td><?= esc($perLabel[$s['per']]) ?></td>
        <td><strong><?= money($s['price']) ?></strong></td>
        <td class="muted"><?= esc($s['unit']) ?></td>
        <?php if ($u['role'] === 'admin'): ?>
          <td>
            <form method="post" action="index.php" onsubmit="return confirm('Удалить услугу?')">
              <input type="hidden" name="act" value="service_delete">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">Удалить</button>
            </form>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($u['role'] === 'admin'): ?>
<div class="panel">
  <h2>Добавить услугу</h2>
  <form method="post" action="index.php" class="form-grid">
    <input type="hidden" name="act" value="service_save">
    <label>Название <input type="text" name="name" required></label>
    <label>Цена, ₽ <input type="number" name="price" min="0" value="500"></label>
    <label>Расчёт
      <select name="per">
        <option value="once">за бронь</option>
        <option value="night">за ночь</option>
        <option value="person_night">за чел./ночь</option>
      </select>
    </label>
    <label>Пояснение <input type="text" name="unit" placeholder="за сеанс, за сутки..."></label>
    <button class="btn" type="submit">Добавить</button>
  </form>
</div>
<?php endif; ?>
