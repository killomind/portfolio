<?php
$id = (int)($_GET['id'] ?? 0);
$e = enterprise($id);
if (!$e) redirect('enterprises', 'Предприятие не найдено');
$vac = array_values(array_filter(published_vacancies(), function ($v) use ($e) { return (int)$v['enterprise_id'] === (int)$e['id']; }));
$stories = array_values(array_filter(db_all('stories'), function ($s) use ($e) { return (int)$s['enterprise_id'] === (int)$e['id']; }));
?>
<div class="crumbs"><a href="index.php?page=enterprises">Предприятия</a> / <?= esc($e['name']) ?></div>

<div class="ent-hero" style="--accent:<?= esc($e['color']) ?>">
  <div class="ent-hero-logo"><?= esc(mb_substr($e['short'], 0, 1)) ?></div>
  <div class="ent-hero-info">
    <div class="badge" style="background:<?= esc($e['color']) ?>"><?= esc($e['tag']) ?></div>
    <h1><?= esc($e['name']) ?></h1>
    <p class="muted"><?= esc($e['activity']) ?> · <?= esc($e['city']) ?>, <?= esc($e['region']) ?>, <?= esc($e['country']) ?></p>
    <div class="ent-facts">
      <span><b><?= number_format((int)$e['stats']['workers'], 0, '.', ' ') ?></b> сотрудников</span>
      <span><b><?= money($e['stats']['min_salary']) ?></b> зарплата от</span>
      <span><b><?= count($vac) ?></b> открытых вакансий</span>
    </div>
  </div>
</div>

<nav class="ent-tabs">
  <a href="#ent-about">О предприятии</a>
  <a href="#ent-geo">География</a>
  <a href="#ent-job">Виды найма</a>
  <a href="#ent-vac">Вакансии</a>
  <a href="#ent-ben">Льготы и преимущества</a>
  <a href="#ent-faq">FAQ</a>
  <a href="#ent-contact">Контакты</a>
  <a href="#ent-map">На карте</a>
</nav>

<section id="ent-about" class="ent-sec">
  <h2>О предприятии</h2>
  <p><?= esc($e['desc']) ?></p>
  <div class="history">
    <?php foreach ($e['history'] as $i => $h): ?>
      <div class="h-item"><i><?= $i + 1 ?></i><span><?= esc($h) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>

<section id="ent-geo" class="ent-sec">
  <h2>География</h2>
  <div class="geo-card">
    <div class="loc-ico">📍</div>
    <div><p><?= esc($e['geography']) ?></p>
    <a class="btn btn-outline" href="https://yandex.ru/maps/?text=<?= urlencode($e['contacts']['address']) ?>" target="_blank" rel="noopener">Открыть на геолокационной карте</a></div>
  </div>
</section>

<section id="ent-job" class="ent-sec">
  <h2>Виды найма и условия работы</h2>
  <div class="adv-grid"><?php foreach ($e['employment'] as $x): ?><div class="adv-chip">✓ <?= esc($x) ?></div><?php endforeach; ?></div>
</section>

<section id="ent-vac" class="ent-sec">
  <h2>Вакансии предприятия</h2>
  <?php if (!$vac): ?><p class="muted">Открытых вакансий на этом предприятии сейчас нет.</p><?php endif; ?>
  <div class="vac-grid">
    <?php foreach ($vac as $v): ?>
      <a class="vac-card" href="index.php?page=vacancy&id=<?= (int)$v['id'] ?>">
        <div class="vac-card-top"><span class="badge" style="background:<?= esc($e['color']) ?>"><?= esc($e['short']) ?></span><span class="vac-salary"><?= esc(vacancy_salary($v)) ?></span></div>
        <h3><?= esc($v['title']) ?></h3>
        <p class="muted"><?= esc($v['city']) ?> · <?= esc($v['schedule']) ?></p>
        <div class="tags"><?php foreach (array_slice($v['skills'], 0, 3) as $s): ?><span><?= esc($s) ?></span><?php endforeach; ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($stories): ?>
  <section class="ent-sec">
    <h2>Истории с этого предприятия</h2>
    <div class="stories-row">
      <?php foreach ($stories as $s): ?>
        <div class="story-card">
          <div class="story-ava"><?= esc($s['photo']) ?></div>
          <div class="story-body">
            <div class="story-tag"><?= esc($s['tag']) ?></div>
            <h3><?= esc($s['name']) ?></h3>
            <div class="muted"><?= esc($s['role']) ?></div>
            <blockquote>«<?= esc($s['quote']) ?>»</blockquote>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section id="ent-ben" class="ent-sec">
  <h2>Льготы и преимущества</h2>
  <div class="ben-grid"><?php foreach ($e['benefits'] as $b): ?><div class="ben-item">🎁 <?= esc($b) ?></div><?php endforeach; ?></div>
</section>

<section id="ent-faq" class="ent-sec">
  <h2>Частые вопросы</h2>
  <div class="faq">
    <?php foreach ($e['faq'] as $f): ?>
      <details><summary><?= esc($f['q']) ?></summary><p><?= esc($f['a']) ?></p></details>
    <?php endforeach; ?>
  </div>
</section>

<section id="ent-contact" class="ent-sec">
  <h2>Контакты</h2>
  <div class="contact-card">
    <p>Адрес: <?= esc($e['contacts']['address']) ?></p>
    <p>Телефон: <?= esc($e['contacts']['phone']) ?></p>
    <p>Email: <?= esc($e['contacts']['email']) ?></p>
  </div>
</section>

<section id="ent-map" class="ent-sec">
  <h2>На карте</h2>
  <div class="map-wrap small"><?= map_svg($e['id']) ?></div>
</section>
