<?php
$id = (int)($_GET['id'] ?? 0);
$c = db_find('checks', $id);
if (!$c) {
  echo '<p class="muted">Проверка не найдена.</p>';
  return;
}
$form = db_find('forms', $c['form_id']);
if (!$form) {
  echo '<p class="muted">Форма не найдена.</p>';
  return;
}
$found = $c['found'];
?>
<div class="cards">
  <div class="card"><div class="num"><?= verdict_badge($c['verdict']) ?></div><div class="lbl">Вердикт</div></div>
  <div class="card"><div class="num"><?= count($found) ?></div><div class="lbl">Дефектов обнаружено</div></div>
  <div class="card"><div class="num"><?= esc($c['duration_sec']) ?> с</div><div class="lbl">Время скана</div></div>
  <div class="card"><div class="num" style="font-size:20px"><?= esc(substr($c['at'], 0, 16)) ?></div><div class="lbl">Проверено в</div></div>
</div>

<?php if ($c['reason']): ?>
  <div class="panel" style="border-color:#fca5a5;background:#fff7f7">
    <strong>Решение: <?= esc(VERDICTS[$c['verdict']]['label']) ?>.</strong>
    <span class="muted"><?= esc($c['reason']) ?></span>
  </div>
<?php endif; ?>

<div class="two-col">
  <div class="panel">
    <div class="panel-head"><h2>Разметка дефектов на снимке</h2></div>
    <div class="table-wrap"><?= scan_render($form, $found) ?></div>
    <div class="legend">
      <span><i style="background:#dc2626"></i>критический</span>
      <span><i style="background:#f59e0b"></i>существенный</span>
      <span><i style="background:#2563eb"></i>незначительный</span>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Обнаруженные дефекты</h2></div>
    <?php if (!$found): ?>
      <p class="muted">Дефектов не обнаружено — форма годна.</p>
    <?php else: ?>
      <table>
        <tr><th>#</th><th>Тип</th><th>Уверен.</th><th>Координаты, мм</th><th>Крит.</th></tr>
        <?php foreach ($found as $i => $d): ?>
          <?php $dt = defect_type($d['type']); ?>
          <tr>
            <td><b>D<?= $i + 1 ?></b></td>
            <td>
              <?= $dt ? esc($dt['name']) : esc($d['type']) ?>
              <?php if (isset($d['note'])): ?><br><span class="muted small"><?= esc($d['note']) ?></span><?php endif; ?>
            </td>
            <td><span class="conf <?= $d['confidence'] >= 0.97 ? 'hi' : 'mid' ?>"><?= esc($d['confidence']) ?></span></td>
            <td class="mono">(<?= esc($d['x']) ?>; <?= esc($d['y']) ?>)</td>
            <td>
              <?php if ($dt): ?>
                <span class="sev-<?= esc($dt['severity']) ?>"><?= esc($dt['severity'] === 'critical' ? 'критич.' : ($dt['severity'] === 'major' ? 'сущест.' : 'незнач.')) ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <p class="muted small" style="margin-top:10px">Регламент ОТК: критический дефект — брак; существенный — доработка; только незначительные — форма годна.</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Информация о форме</h2></div>
  <div class="info-grid">
    <div><span>№ формы</span><?= esc($form['custom_no']) ?></div>
    <div><span>Клиент</span><?= esc($form['client']) ?></div>
    <div><span>Продукт</span><?= esc($form['product']) ?></div>
    <div><span>Размер</span><?= esc($form['size_w']) ?>×<?= esc($form['size_h']) ?> мм</div>
    <div><span>Полимер</span><?= esc($form['polymer']) ?></div>
    <div><span>Толщина</span><?= esc($form['thickness']) ?></div>
    <div><span>Оператор</span><?= esc(user_name($c['operator_id'])) ?></div>
    <div><span>Статус формы</span><?= form_status_badge($form['status']) ?></div>
  </div>
  <div style="margin-top:16px">
    <a class="btn btn-ghost" href="index.php?page=checks">К журналу</a>
  </div>
</div>