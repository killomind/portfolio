<?php
$u = current_user();
require_role(['admin', 'manager', 'advisor']);

$id = (int)($_GET['id'] ?? 0);
$o = $id ? db_find('orders', $id) : null;

$defClient = $o['client_id'] ?? (int)($_GET['client'] ?? 0);
$defCar = $o['car_id'] ?? (int)($_GET['car'] ?? 0);
$defBranch = $o['branch_id'] ?? (int)($_GET['branch'] ?? 0);
if (!$defBranch) $defBranch = (int)$u['branch_id'];
if (!$defBranch) $defBranch = 1;
$defDate = $o['date'] ?? ($_GET['date'] ?? date('Y-m-d'));
$defTime = $o['time'] ?? ($_GET['time'] ?? '10:00');

$branches = db_all('branches');
$clients = db_all('clients');
$cars = db_all('cars');
$services = db_all('services');
$parts = array_values(array_filter(db_all('parts'), function ($p) use ($defBranch) { return (int)$p['branch_id'] === $defBranch; }));

$selServices = $o ? array_map(function ($s) { return (int)$s['id']; }, $o['services']) : [];
$selParts = $o ? $o['parts'] : [];

$cats = [];
foreach ($services as $s) $cats[$s['category']][] = $s;
ksort($cats);
?>
<form method="post" action="index.php">
<input type="hidden" name="act" value="order_save">
<?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

<div class="panel">
  <div class="panel-head"><h2><?= $id ? 'Редактирование заказа ' . esc($o['number']) : 'Новый заказ' ?></h2></div>
  <div class="form-grid">
    <label>Клиент
      <select name="client_id" id="clientSel">
        <option value="0">— выберите клиента —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $defClient === (int)$c['id'] ? 'selected' : '' ?>><?= esc($c['name'] . ' — ' . $c['phone']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Автомобиль
      <select name="car_id" id="carSel">
        <option value="0">— сначала выберите клиента —</option>
        <?php foreach ($cars as $car): ?>
          <option value="<?= $car['id'] ?>" data-client="<?= $car['client_id'] ?>" <?= $defCar === (int)$car['id'] ? 'selected' : '' ?>><?= esc($car['brand'] . ' ' . $car['model'] . ' (' . $car['plate'] . ')') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Филиал
      <select name="branch_id" id="branchSel">
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $defBranch === (int)$b['id'] ? 'selected' : '' ?>><?= esc($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Дата <input type="date" name="date" value="<?= esc($defDate) ?>"></label>
    <label>Время
      <select name="time">
        <?php for ($h = 9; $h <= 19; $h++): $t = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; ?>
          <option value="<?= $t ?>" <?= $defTime === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <label>Тип работ <input type="text" name="work_type" value="<?= esc($o['work_type'] ?? '') ?>" placeholder="Например: ТО-60, диагностика"></label>
  </div>
  <label>Комментарий
    <textarea name="comment" rows="2"><?= esc($o['comment'] ?? '') ?></textarea>
  </label>
</div>

<div class="panel">
  <h2>Услуги</h2>
  <div class="svc-groups">
    <?php foreach ($cats as $cat => $list): ?>
      <div class="svc-group">
        <h3><?= esc($cat) ?></h3>
        <?php foreach ($list as $s): ?>
          <label class="svc-item"><input type="checkbox" name="services[]" class="svc" value="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>" <?= in_array((int)$s['id'], $selServices, true) ? 'checked' : '' ?>>
            <span><?= esc($s['name']) ?></span> <b><?= money($s['price']) ?></b>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h2>Запчасти (склад филиала)</h2>
  <table>
    <tr><th></th><th>Артикул</th><th>Наименование</th><th>Остаток</th><th>Цена</th><th>Кол-во</th></tr>
    <?php if (!$parts): ?>
      <tr><td colspan="6" class="empty">Выберите филиал, чтобы увидеть остатки склада</td></tr>
    <?php endif; ?>
    <?php foreach ($parts as $p): ?>
      <?php $was = 0; foreach ($selParts as $sp) if ((int)$sp['id'] === (int)$p['id']) $was = $sp['qty']; ?>
      <tr class="prt" data-price="<?= $p['price'] ?>">
        <td><input type="checkbox" class="prt-cb" name="part_ids[]" value="<?= $p['id'] ?>" <?= $was ? 'checked' : '' ?>></td>
        <td><?= esc($p['sku']) ?></td>
        <td><?= esc($p['name']) ?></td>
        <td><?= $p['qty'] ?></td>
        <td><?= money($p['price']) ?></td>
        <td><input type="number" class="prt-q" name="part_qty[<?= $p['id'] ?>]" value="<?= $was ?: 1 ?>" min="1" style="width:70px"></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel total-line">
  Итого: <span id="total">0 ₽</span>
</div>
<button class="btn btn-lg" type="submit">Сохранить заказ</button>
</form>

<script>
function setPartsForBranch() {
  var b = document.getElementById('branchSel').value;
  location.href = 'index.php?page=order_edit' + (document.querySelector('input[name=id]') ? '&id=' + document.querySelector('input[name=id]').value : '') + '&branch=' + b;
}

function filterCars() {
  var cid = document.getElementById('clientSel').value;
  document.querySelectorAll('#carSel option').forEach(function (opt) {
    if (opt.value === '0') return;
    opt.style.display = (opt.dataset.client === cid) ? '' : 'none';
  });
  var carSel = document.getElementById('carSel');
  if (carSel.value === '0') {
    carSel.querySelectorAll('option').forEach(function (opt) {
      if (opt.value !== '0' && opt.dataset.client === cid && opt.style.display !== 'none') { carSel.value = opt.value; }
    });
  }
  if (document.getElementById('clientSel').value === '0') carSel.value = '0';
}

function calc() {
  var t = 0;
  document.querySelectorAll('.svc:checked').forEach(function (c) { t += +c.dataset.price; });
  document.querySelectorAll('.prt').forEach(function (r) {
    if (r.querySelector('.prt-cb').checked) {
      t += +r.dataset.price * (+r.querySelector('.prt-q').value || 1);
    }
  });
  document.getElementById('total').textContent = t.toLocaleString('ru-RU') + ' ₽';
}

document.getElementById('clientSel').addEventListener('change', filterCars);
document.getElementById('branchSel').addEventListener('change', setPartsForBranch);
document.querySelectorAll('.svc').forEach(function (c) { c.addEventListener('change', calc); });
document.querySelectorAll('.prt-cb, .prt-q').forEach(function (c) { c.addEventListener('change', calc); });
filterCars();
calc();
</script>
