<?php
$routes = db_all('routes');
?>
<div class="page-hero">
  <h1>Игровые механики</h1>
  <p class="muted">Узнайте, какая карьера подходит вам, и пройдите тест на совместимость с ГРК «Горизонт». Это займёт 3–5 минут.</p>
</div>

<div class="games-grid">
  <a class="game-card big" href="index.php?page=game_route">
    <div class="game-ico">🧭</div>
    <div>
      <h2>Карьерный маршрут</h2>
      <p>Выберите трек — рабочий, инженер, IT, молодой специалист или руководитель — и посмотрите, как растут зарплата и должность по годам.</p>
    </div>
  </a>
  <a class="game-card big" href="index.php?page=game_test">
    <div class="game-ico">🎯</div>
    <div>
      <h2>Тест на совместимость</h2>
      <p>15 вопросов, 3–5 минут. Узнайте индекс совместимости с компанией, свой профиль и рекомендованные вакансии и предприятия.</p>
    </div>
  </a>
</div>

<div class="section-head"><h2>Все карьерные маршруты</h2><p class="muted">5 треков развития внутри холдинга</p></div>
<div class="route-cards">
  <?php foreach ($routes as $r): ?>
    <a class="route-card" style="--rc:<?= esc($r['color']) ?>" href="index.php?page=game_route&id=<?= (int)$r['id'] ?>">
      <div class="route-ico"><?= esc($r['icon']) ?></div>
      <h3><?= esc($r['title']) ?></h3>
      <p class="muted"><?= esc($r['desc']) ?></p>
      <span class="route-go">Открыть маршрут →</span>
    </a>
  <?php endforeach; ?>
</div>
