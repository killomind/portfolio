<?php
$u = current_user();
$m = db_find('models', 1);
if (!$m) {
  $m = ['version' => 'v1.3', 'ap50' => 0.979, 'recall' => 0.961, 'precision' => 0.95, 'train_at' => '', 'samples' => 0, 'failures' => 0, 'classes' => []];
}
$classes = is_array($m['classes']) ? $m['classes'] : [];
?>
<div class="panel">
  <div class="panel-head"><h2>Модель распознавания дефектов</h2></div>
  <p class="muted" style="margin-bottom:14px">Детектор обучен на фотографиях флексоформ с просветного стола. Метрики на валидационном наборе.</p>
  <div class="cards">
    <div class="card"><div class="num"><?= esc($m['version']) ?></div><div class="lbl">Версия модели</div></div>
    <div class="card"><div class="num"><?= esc($m['ap50']) ?></div><div class="lbl">mAP@0.5</div></div>
    <div class="card"><div class="num"><?= esc($m['recall']) ?></div><div class="lbl">Recall</div></div>
    <div class="card"><div class="num"><?= esc($m['precision']) ?></div><div class="lbl">Precision</div></div>
    <div class="card alt"><div class="num"><?= (int)$m['samples'] ?></div><div class="lbl">Обучающих снимков</div></div>
    <div class="card alt"><div class="num"><?= (int)$m['failures'] ?></div><div class="lbl">Ложно-отриц. на валидации</div></div>
  </div>
  <?php if ($m['train_at']): ?>
    <p class="muted small" style="margin-top:12px">Последнее дообучение: <?= esc($m['train_at']) ?></p>
  <?php endif; ?>
  <?php if (in_array($u['role'], ['engineer', 'admin'], true)): ?>
    <form method="post" action="index.php" style="margin-top:16px">
      <input type="hidden" name="act" value="model_train">
      <button class="btn" type="submit">Переобучить модель (демо)</button>
    </form>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>Точность по классам</h2>
  <?php if (!$classes): ?>
    <p class="muted">Данные о точности по классам отсутствуют.</p>
  <?php else: ?>
    <div class="three-col">
      <?php foreach ($classes as $key => $val): ?>
        <?php $dt = defect_type($key); ?>
        <div class="metric-tile">
          <div class="m-num"><?= esc($val) ?></div>
          <div class="m-lbl"><?= $dt ? esc($dt['name']) : esc($key) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>