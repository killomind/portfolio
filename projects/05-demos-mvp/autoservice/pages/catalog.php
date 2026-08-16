<?php
$u = current_user();
$services = db_all('services');
$cats = [];
foreach ($services as $s) $cats[$s['category']][] = $s;
ksort($cats);
?>
<div class="panel">
  <div class="panel-head"><h2>Работы и цены</h2></div>
  <?php foreach ($cats as $cat => $list): ?>
    <h3 class="cat-title"><?= esc($cat) ?></h3>
    <table>
      <tr><th>Наименование</th><th>Нормо-часы</th><th>Цена</th><?php if (in_array($u['role'], ['admin', 'manager'], true)): ?><th></th><?php endif; ?></tr>
      <?php foreach ($list as $s): ?>
        <tr>
          <td><?= esc($s['name']) ?></td>
          <td><?= $s['hours'] ?></td>
          <td><strong><?= money($s['price']) ?></strong></td>
          <?php if (in_array($u['role'], ['admin', 'manager'], true)): ?>
            <td>
              <form method="post" action="index.php" onsubmit="return confirm('Удалить работу из справочника?')">
                <input type="hidden" name="act" value="service_delete">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Удалить</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>
</div>

<?php if (in_array($u['role'], ['admin', 'manager'], true)): ?>
  <div class="panel">
    <h2>Добавить работу</h2>
    <form method="post" action="index.php" class="form-grid">
      <input type="hidden" name="act" value="service_save">
      <label>Наименование <input type="text" name="name" required></label>
      <label>Категория
        <select name="category">
          <option>ТО</option><option>Ремонт</option><option>Диагностика</option><option>Кузов</option><option>Электрика</option>
        </select>
      </label>
      <label>Нормо-часы <input type="number" name="hours" step="0.1" value="1"></label>
      <label>Цена, ₽ <input type="number" name="price" value="1000"></label>
      <button class="btn" type="submit">Добавить</button>
    </form>
  </div>
<?php endif; ?>
