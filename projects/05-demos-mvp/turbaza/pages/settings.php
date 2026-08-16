<?php
require_role(['admin']);
$seasons = db_all('seasons');
?>
<div class="panel">
  <div class="panel-head"><h2>Сезоны и коэффициенты</h2></div>
  <p class="muted">Цена ночи = базовая цена домика × коэффициент сезона. Дата выезда считается по последней ночи проживания.</p>
  <table>
    <tr><th>Сезон</th><th>С</th><th>По</th><th>Коэффициент</th><th></th></tr>
    <?php foreach ($seasons as $s): ?>
      <tr>
        <td><strong><?= esc($s['name']) ?></strong></td>
        <td><?= esc(date('d.m', strtotime($s['from'] . '-2000'))) ?></td>
        <td><?= esc(date('d.m', strtotime($s['to'] . '-2000'))) ?></td>
        <td>×<?= (float)$s['mult'] ?></td>
        <td>
          <form method="post" action="index.php" onsubmit="return confirm('Удалить сезон?')">
            <input type="hidden" name="act" value="season_delete">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="panel">
  <h2>Добавить сезон</h2>
  <form method="post" action="index.php" class="form-grid">
    <input type="hidden" name="act" value="season_save">
    <label>Название <input type="text" name="name" required></label>
    <label>С (ММ-ДД) <input type="text" name="from" placeholder="06-01"></label>
    <label>По (ММ-ДД) <input type="text" name="to" placeholder="08-31"></label>
    <label>Коэффициент <input type="number" name="mult" step="0.1" min="0.5" value="1.0"></label>
    <button class="btn" type="submit">Добавить</button>
  </form>
</div>
