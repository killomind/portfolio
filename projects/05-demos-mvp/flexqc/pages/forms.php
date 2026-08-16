<?php
$u = current_user();
$forms = db_all('forms');
$canEdit = in_array($u['role'], ['admin', 'engineer', 'manager'], true);
?>
<?php if ($canEdit): ?>
  <div class="panel">
    <div class="panel-head"><h2>Добавить форму в очередь контроля</h2></div>
    <form method="post" action="index.php" class="form-grid">
      <input type="hidden" name="act" value="form_save">
      <label>№ заказа / формы<input type="text" name="custom_no" placeholder="ФФ-2607-132" required></label>
      <label>Клиент<input type="text" name="client" placeholder="ТД «Пример»" required></label>
      <label>Продукт<input type="text" name="product" required></label>
      <label>Тип
        <select name="shape">
          <option value="этикетка">Этикетка</option>
          <option value="гибкая упаковка">Гибкая упаковка</option>
          <option value="плёнка">Плёнка</option>
          <option value="блок-пакет">Блок-пакет</option>
        </select>
      </label>
      <label>Ширина, мм<input type="number" name="size_w" min="50" required></label>
      <label>Высота, мм<input type="number" name="size_h" min="50" required></label>
      <label>Растр<input type="text" name="raster" placeholder="175 lpi, FMT 45"></label>
      <label>Полимер<input type="text" name="polymer" placeholder="Asahi AFP-Top"></label>
      <label>Толщина<input type="text" name="thickness" placeholder="1,14 мм"></label>
      <div style="align-self:end"><button class="btn" type="submit">Добавить</button></div>
    </form>
  </div>
<?php endif; ?>

<div class="panel">
  <table>
    <tr><th>№ формы</th><th>Клиент</th><th>Продукт</th><th>Размер</th><th>Растр</th><th>Полимер</th><th>Статус</th><th></th></tr>
    <?php foreach ($forms as $f): ?>
      <tr>
        <td class="mono"><?= esc($f['custom_no']) ?></td>
        <td><?= esc($f['client']) ?></td>
        <td><?= esc($f['product']) ?></td>
        <td><?= esc($f['size_w']) ?>×<?= esc($f['size_h']) ?> мм</td>
        <td><?= esc($f['raster']) ?></td>
        <td><?= esc($f['polymer']) ?></td>
        <td><?= form_status_badge($f['status']) ?></td>
        <td>
          <?php if ($f['status'] === 'queue' && in_array($u['role'], ['operator', 'engineer', 'manager', 'admin'], true)): ?>
            <a class="btn btn-sm" href="index.php?page=scan&sel=<?= $f['id'] ?>">Контроль</a>
          <?php elseif (!empty($f['last_check_id'])): ?>
            <a class="btn btn-ghost btn-sm" href="index.php?page=check_view&id=<?= (int)$f['last_check_id'] ?>">Результат</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>