<?php
$u = current_user();
$queue = array_values(array_filter(db_all('forms'), function ($f) { return $f['status'] === 'queue'; }));
$selId = (int)($_GET['sel'] ?? 0);
$form = null;
if ($selId) $form = db_find('forms', $selId);
if (!$form && $queue) $form = $queue[0];
if (!$form) {
  $forms = db_all('forms');
  $form = $forms ? $forms[0] : null;
}
if (!$form) {
  echo '<p class="muted">Не найдено ни одной формы. Добавьте форму в разделе «Флексоформы» (доступно администратору, инженеру или руководителю ОТК).</p>';
  return;
}
?>
<div class="panel">
  <div class="panel-head">
    <h2>Шаг 1 — выберите форму из очереди</h2>
  </div>
  <form method="get" action="index.php" class="inline-form">
    <input type="hidden" name="page" value="scan">
    <select name="sel" onchange="this.form.submit()">
      <?php foreach ($queue as $f): ?>
        <option value="<?= $f['id'] ?>" <?= (int)$f['id'] === (int)$form['id'] ? 'selected' : '' ?>>
          <?= esc($f['custom_no']) ?> — <?= esc($f['client']) ?>, <?= esc($f['size_w']) ?>×<?= esc($f['size_h']) ?> мм
        </option>
      <?php endforeach; ?>
      <?php if (!$queue): ?><option value="">Очередь пуста</option><?php endif; ?>
    </select>
  </form>
</div>

<div class="two-col">
  <div class="panel">
    <div class="panel-head"><h2>Шаг 2 — просветный стол с формой</h2></div>
    <p class="muted small" style="margin-bottom:12px">Снимок камеры с верхней позиции: форма на стекле просветного стола, зоны печати, подсветка снизу. Сервоприводы ведут камеру по траектории.</p>
    <?php $preview = scan_render($form, $form['latent']); echo $preview; ?>
    <div class="legend">
      <span><i style="background:#16a34a"></i>форма на столе</span>
      <span><i style="background:#dc2626"></i>разметка дефектов (эталон для демо)</span>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Шаг 3 — запуск</h2></div>
    <form method="post" action="index.php">
      <input type="hidden" name="act" value="scan_run">
      <input type="hidden" name="form_id" value="<?= (int)$form['id'] ?>">
      <div class="info-grid" style="margin-bottom:16px">
        <div><span>Форма</span><?= esc($form['custom_no']) ?></div>
        <div><span>Клиент</span><?= esc($form['client']) ?></div>
        <div><span>Продукт</span><?= esc($form['product']) ?></div>
        <div><span>Размер</span><?= esc($form['size_w']) ?>×<?= esc($form['size_h']) ?> мм</div>
        <div><span>Растр</span><?= esc($form['raster']) ?></div>
        <div><span>Полимер</span><?= esc($form['polymer']) ?></div>
        <div><span>Толщина</span><?= esc($form['thickness']) ?></div>
      </div>
      <p class="muted small" style="margin-bottom:14px">Демонстрация запускает скан: камера проходит траекторию над формой, модель детектирует дефекты, строится разметка с координатами и уверенностью, выносится вердикт (годна / на доработку / брак).</p>
      <button class="btn btn-lg" type="submit">Запустить контроль формы</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Как устроено рабочее место</h2></div>
  <div class="three-col">
    <div class="metric-tile"><div class="m-lbl">1 · Просветный стол</div><p class="muted small" style="margin-top:6px">Подсветка снизу равномерно просвечивает фотополимер: воздушные пузыри, включения и просветы рельефа видны как тени на снимке камеры.</p></div>
    <div class="metric-tile"><div class="m-lbl">2 · Камера на сервоприводе</div><p class="muted small" style="margin-top:6px">Портальная система ведёт камеру по зонам формы, сшивает кадры в полное изображение, фиксирует координаты каждого повреждения в миллиметрах.</p></div>
    <div class="metric-tile"><div class="m-lbl">3 · ML-модель</div><p class="muted small" style="margin-top:6px">Нейросетевая модель обучена на реальных дефектах флексоформ: классифицирует дефект, оценивает уверенность и относит форму к годным, доработке или браку по регламенту ОТК.</p></div>
  </div>
</div>