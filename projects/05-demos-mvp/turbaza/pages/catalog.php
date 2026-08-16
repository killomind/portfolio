<?php
$u = current_user();
$role = $u['role'];

$in = isset($_GET['in']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['in']) ? $_GET['in'] : date('Y-m-d', strtotime('+1 day'));
$out = isset($_GET['out']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['out']) ? $_GET['out'] : date('Y-m-d', strtotime('+3 day'));
$guests = max(1, (int)($_GET['guests'] ?? 1));

$range = strcmp($out, $in) > 0;
$nights = $range ? nights_between($in, $out) : 0;

$cottages = db_all('cottages');
usort($cottages, function ($a, $b) { return (int)$a['price'] <=> (int)$b['price']; });

if ($range) {
  $free = array_values(array_filter($cottages, function ($c) use ($in, $out) { return is_available($c['id'], $in, $out); }));
  $busy = array_values(array_filter($cottages, function ($c) use ($in, $out) { return !is_available($c['id'], $in, $out); }));
  $cottages = array_merge($free, $busy);
}
?>
<div class="hero">
  <h2>Домики на берегу озера</h2>
  <p>Выберите даты и количество гостей — покажем свободные варианты и посчитаем стоимость за все ночи с учётом сезона.</p>
  <form class="filters" method="get" action="index.php">
    <input type="hidden" name="page" value="catalog">
    <label>Заезд <input type="date" name="in" value="<?= esc($in) ?>" min="<?= esc(date('Y-m-d')) ?>"></label>
    <label>Выезд <input type="date" name="out" value="<?= esc($out) ?>"></label>
    <label>Гостей
      <select name="guests">
        <?php for ($g = 1; $g <= 6; $g++): ?>
          <option value="<?= $g ?>" <?= $guests === $g ? 'selected' : '' ?>><?= $g ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <button class="btn" type="submit">Показать свободные</button>
  </form>
</div>

<?php if ($range): ?>
  <p class="muted">Заезд <?= esc(date('d.m.Y', strtotime($in))) ?>, выезд <?= esc(date('d.m.Y', strtotime($out))) ?> — <?= $nights ?> ночи, гостей: <?= $guests ?>. Сезон: <?= esc(season_label($in)) ?>.</p>
<?php endif; ?>

<div class="cottage-grid">
  <?php if (!$cottages): ?>
    <div class="panel"><p class="empty">Домиков пока нет.</p></div>
  <?php endif; ?>
  <?php foreach ($cottages as $c): ?>
    <?php
      $avail = $range ? is_available($c['id'], $in, $out) : null;
      $tooSmall = $guests > (int)$c['capacity'];
      $nTotal = $range ? night_total_for($c, $in, $out) : 0;
      $night = $range ? night_price($c, $in) : (int)$c['price'];
    ?>
    <div class="cottage-card">
      <?= photo_block($c) ?>
      <div class="cottage-body">
        <div class="cottage-top">
          <h3><a href="index.php?page=cottage&id=<?= $c['id'] ?>"><?= esc($c['name']) ?></a></h3>
          <span class="tag"><?= esc($c['type']) ?></span>
        </div>
        <div class="meta">
          <span>До <?= $c['capacity'] ?> гостей</span>
          <span><?= $c['area'] ?> м²</span>
          <span><?= count($c['amenities']) ?> удобств</span>
        </div>
        <p class="desc"><?= esc($c['description']) ?></p>
        <div class="price-row">
          <div>
            <?php if ($range): ?>
              <div class="price"><?= money($nTotal) ?></div>
              <div class="price-sub"><?= money($night) ?>/ночь</div>
            <?php else: ?>
              <div class="price"><?= money($night) ?></div>
              <div class="price-sub">от, за ночь</div>
            <?php endif; ?>
          </div>
          <?php if ($tooSmall): ?>
            <span class="badge" style="background:#f59e0b">маловат для <?= $guests ?> гостей</span>
          <?php elseif ($avail === false): ?>
            <span class="badge" style="background:#dc2626">занят</span>
          <?php elseif ($avail === true): ?>
            <span class="badge" style="background:#16a34a">свободен</span>
          <?php endif; ?>
        </div>
        <div class="card-actions">
          <a class="btn" href="index.php?page=cottage&id=<?= $c['id'] ?><?= $range ? '&in=' . $in . '&out=' . $out . '&guests=' . $guests : '' ?>">Подробнее</a>
          <?php if ($avail === true || $avail === null): ?>
            <a class="btn btn-dark" href="index.php?page=booking&cottage=<?= $c['id'] ?><?= $range ? '&in=' . $in . '&out=' . $out . '&guests=' . $guests : '' ?>">Забронировать</a>
          <?php endif; ?>
          <?php if (in_array($role, ['admin'], true)): ?>
            <a class="btn btn-outline btn-sm" href="index.php?page=catalog&edit=<?= $c['id'] ?>">Изменить</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (in_array($role, ['admin'], true)): ?>
  <div class="panel">
    <div class="panel-head"><h2>Добавить домик</h2></div>
    <?php
      $editId = (int)($_GET['edit'] ?? 0);
      $e = $editId ? db_find('cottages', $editId) : null;
      $isEdit = $e !== null;
    ?>
    <form method="post" action="index.php" class="form-grid">
      <input type="hidden" name="act" value="cottage_save">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $e['id'] ?>"><?php endif; ?>
      <label>Название <input type="text" name="name" value="<?= esc($e['name'] ?? '') ?>" required></label>
      <label>Тип <input type="text" name="type" value="<?= esc($e['type'] ?? '') ?>" placeholder="Эконом, Семейный..."></label>
      <label>Гостей <input type="number" name="capacity" value="<?= esc($e['capacity'] ?? 2) ?>" min="1"></label>
      <label>Площадь, м² <input type="number" name="area" value="<?= esc($e['area'] ?? 20) ?>" min="1"></label>
      <label>Цена, ₽/ночь <input type="number" name="price" value="<?= esc($e['price'] ?? 2500) ?>" min="1"></label>
      <label>Мин. ночей <input type="number" name="min_nights" value="<?= esc($e['min_nights'] ?? 2) ?>" min="1"></label>
      <label>Цвет фото <input type="text" name="color" value="<?= esc($e['color'] ?? '#3d6b4f') ?>" placeholder="#3d6b4f"></label>
      <label>Описание <textarea name="description" rows="3"><?= esc($e['description'] ?? '') ?></textarea></label>
      <label>Удобства (по одному в строке)
        <textarea name="amenities" rows="4"><?= esc(implode("\n", $e['amenities'] ?? [])) ?></textarea>
      </label>
      <button class="btn" type="submit"><?= $isEdit ? 'Сохранить' : 'Добавить' ?></button>
      <?php if ($isEdit): ?>
        <a class="btn btn-outline" href="index.php?page=catalog">Отмена</a>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>
