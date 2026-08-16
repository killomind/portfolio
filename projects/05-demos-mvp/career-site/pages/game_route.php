<?php
$routes = db_all('routes');
$id = (int)($_GET['id'] ?? 0);
$route = null;
foreach ($routes as $r) if ((int)$r['id'] === $id) $route = $r;
if (!$route) $route = $routes[0];
?>
<div class="crumbs"><a href="index.php?page=games">Игровые механики</a> / Карьерный маршрут</div>

<div class="page-hero">
  <h1>Узнай свой карьерный маршрут</h1>
  <p class="muted">Выберите трек и посмотрите, как развивается карьера шаг за шагом.</p>
</div>

<div class="route-tabs">
  <?php foreach ($routes as $r): ?>
    <a href="index.php?page=game_route&id=<?= (int)$r['id'] ?>" style="--rc:<?= esc($r['color']) ?>" class="<?= (int)$r['id'] === (int)$route['id'] ? 'active' : '' ?>">
      <?= esc($r['icon']) ?> <?= esc($r['title']) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="route-view" style="--rc:<?= esc($route['color']) ?>">
  <div class="route-head">
    <div class="route-ico big"><?= esc($route['icon']) ?></div>
    <div>
      <h2><?= esc($route['title']) ?></h2>
      <p><?= esc($route['desc']) ?></p>
    </div>
  </div>
  <div class="route-road">
    <?php $n = count($route['steps']); foreach ($route['steps'] as $i => $st): ?>
      <div class="r-step">
        <div class="r-node"><span><?= $i + 1 ?></span></div>
        <div class="r-body">
          <h3><?= esc($st['title']) ?></h3>
          <div class="r-term"><?= esc($st['term']) ?></div>
          <p class="muted"><?= esc($st['text']) ?></p>
        </div>
        <?php if ($i < $n - 1): ?><div class="r-conn"></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="route-vac">
    <h3>Вакансии по этому треку</h3>
    <div class="vac-grid">
      <?php $shown = 0; foreach (published_vacancies() as $v): if (!in_array((int)$v['id'], $route['vacancies'], true)) continue; if ($shown >= 4) break; $shown++; $e = enterprise($v['enterprise_id']); ?>
        <a class="vac-card" href="index.php?page=vacancy&id=<?= (int)$v['id'] ?>">
          <div class="vac-card-top"><span class="badge" style="background:<?= esc($e['color']) ?>"><?= esc($e['short']) ?></span><span class="vac-salary"><?= esc(vacancy_salary($v)) ?></span></div>
          <h3><?= esc($v['title']) ?></h3>
          <p class="muted"><?= esc($v['city']) ?> · <?= esc($v['schedule']) ?></p>
        </a>
      <?php endforeach; ?>
      <?php if (!$shown): ?><p class="muted">Сейчас открытых вакансий по этому треку нет.</p><?php endif; ?>
    </div>
  </div>
</div>
