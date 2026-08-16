<?php
$u = current_user();
$role = $u['role'];

if ($role === 'client') {
  $cars = array_values(array_filter(db_all('cars'), function ($car) { return (int)$car['client_id'] === DEMO_CLIENT_ID; }));
} else {
  $cars = db_all('cars');
}
usort($cars, function ($a, $b) { return strcmp($a['brand'] . $a['model'], $b['brand'] . $b['model']); });
$orders = db_all('orders');
?>
<div class="panel">
  <div class="panel-head"><h2>Автомобили</h2></div>
  <table>
    <tr><th>Авто</th><th>Год</th><th>Госномер</th><th>VIN</th><th>Владелец</th><th></th></tr>
    <?php foreach ($cars as $car): ?>
      <?php $owner = db_find('clients', $car['client_id']); $hist = array_values(array_filter($orders, function ($o) use ($car) { return (int)$o['car_id'] === (int)$car['id']; })); ?>
      <tr>
        <td><?= esc($car['brand'] . ' ' . $car['model']) ?></td>
        <td><?= $car['year'] ?></td>
        <td><?= esc($car['plate']) ?></td>
        <td class="mono"><?= esc($car['vin']) ?></td>
        <td><?= $owner ? esc($owner['name']) : '—' ?></td>
        <td>
          <details class="hist"><summary>История обслуживания (<?= count($hist) ?>)</summary>
            <?php if (!$hist): ?><p class="muted small">Обслуживаний ещё не было.</p><?php else: ?>
              <table>
                <tr><th>Дата</th><th>Заказ</th><th>Тип работ</th><th>Сумма</th><th>Статус</th></tr>
                <?php foreach ($hist as $h): ?>
                  <tr>
                    <td><?= esc($h['date']) ?></td>
                    <td><?= esc($h['number']) ?></td>
                    <td><?= esc($h['work_type']) ?></td>
                    <td><?= money($h['total']) ?></td>
                    <td><?= status_badge($h['status']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel">
  <h2>Добавить автомобиль</h2>
  <form method="post" action="index.php" class="form-grid">
    <input type="hidden" name="act" value="car_save">
    <?php if ($role !== 'client'): ?>
      <label>Владелец
        <select name="client_id" required>
          <?php foreach (db_all('clients') as $c): ?>
            <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
    <label>Марка <input type="text" name="brand" required placeholder="Toyota"></label>
    <label>Модель <input type="text" name="model" required placeholder="Camry"></label>
    <label>Год <input type="number" name="year" min="1980" max="<?= date('Y') + 1 ?>"></label>
    <label>Госномер <input type="text" name="plate" placeholder="А 123 ВС 70"></label>
    <label>VIN <input type="text" name="vin" maxlength="17"></label>
    <button class="btn" type="submit">Добавить</button>
  </form>
</div>
