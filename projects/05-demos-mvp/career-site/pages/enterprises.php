<?php
$enterprises = db_all('enterprises');
$see = array_values(array_filter(db_all('enterprises'), function ($e) { return mb_stripos($e['name'], 'офис') === false; }));
?>
<div class="page-hero">
  <h1>Предприятия ГРК «Горизонт»</h1>
  <p class="muted">Интерактивная карта: 7 предприятий от Мурманска до Казахстана. Кликните по метке или карточке, чтобы открыть страницу предприятия.</p>
</div>

<div class="map-wrap">
  <?= map_svg() ?>
  <div class="map-stats">
    <?php foreach ($see as $e): ?>
      <div class="map-stat"><span class="dot" style="background:<?= esc($e['color']) ?>"></span><b><?= esc($e['short']) ?></b><span><?= esc($e['city']) ?>, <?= esc($e['region']) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="ent-grid">
  <?php foreach ($enterprises as $e): $open = count(array_filter(db_all('vacancies'), function ($v) use ($e) { return (int)$v['enterprise_id'] === (int)$e['id'] && $v['status'] === 'published'; })); ?>
    <a class="ent-card" href="index.php?page=enterprise&id=<?= (int)$e['id'] ?>">
      <div class="ent-head" style="border-color:<?= esc($e['color']) ?>">
        <span class="ent-logo"><?= esc(mb_substr($e['short'], 0, 1)) ?></span>
        <div>
          <h3><?= esc($e['name']) ?></h3>
          <div class="muted"><?= esc($e['city']) ?>, <?= esc($e['region']) ?> · <?= esc($e['tag']) ?></div>
        </div>
      </div>
      <p class="ent-desc"><?= esc(mb_substr($e['desc'], 0, 140)) ?>…</p>
      <div class="ent-meta">
        <span>👥 <?= number_format((int)$e['stats']['workers'], 0, '.', ' ') ?></span>
        <span>📍 <?= esc($e['city']) ?></span>
        <span class="btn btn-sm"><?= $open ?> вакансий</span>
      </div>
    </a>
  <?php endforeach; ?>
</div>
