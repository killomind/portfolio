<?php
$u = current_user();
$vac = published_vacancies();
usort($vac, function ($a, $b) { return (int)$b['views'] <=> (int)$a['views']; });
$top = array_slice($vac, 0, 6);
$stories = db_all('stories');
$enterprises = db_all('enterprises');
$totalSalary = 0;
foreach ($vac as $v) $totalSalary += (int)$v['salary_min'];
$avgSalary = $vac ? (int)round($totalSalary / count($vac)) : 0;
?>
<section class="hero">
  <div class="hero-in">
    <div class="hero-tag">Работа в горнодобывающей отрасли по всей России и СНГ</div>
    <h1>Построй карьеру,<br>которую видно</h1>
    <p>ГРК «Горизонт» — 7 предприятий от Мурманска до Казахстана. Мы ищем тех, кто не боится масштаба: рабочие, инженеры, IT-специалисты и руководители.</p>
    <form class="hero-search" method="get" action="index.php">
      <input type="hidden" name="page" value="vacancies">
      <input type="text" name="q" placeholder="Например: инженер, PHP, вахта, Березники" autocomplete="off">
      <button class="btn btn-lg" type="submit">Найти вакансии</button>
    </form>
    <div class="hero-cta">
      <a class="btn btn-ghost" href="index.php?page=games">Пройти тест на совместимость</a>
      <a class="btn btn-ghost" href="index.php?page=enterprises">Предприятия на карте</a>
    </div>
  </div>
</section>

<div class="stats-row">
  <div class="stat"><b><?= count($enterprises) ?></b><span>предприятий</span></div>
  <div class="stat"><b><?= count($vac) ?></b><span>открытых вакансий</span></div>
  <div class="stat"><b><?= count($enterprises) + 2 ?></b><span>регионов и стран</span></div>
  <div class="stat"><b><?= money($avgSalary) ?></b><span>средняя зарплата</span></div>
  <div class="stat"><b>41 000+</b><span>сотрудников</span></div>
</div>

<?php if ($u && $u['role'] !== 'guest'): ?>
  <div class="panel role-panel">
    <h2>Быстрые действия для роли «<?= esc(ROLES[$u['role']]) ?>»</h2>
    <?php if ($u['role'] === 'hr' || $u['role'] === 'admin'): ?>
      <p>Вакансий на модерации: <b><?= count(array_filter(db_all('vacancies'), function ($v) { return $v['status'] === 'on_moderation'; })) ?></b>. <a href="index.php?page=moderation">Перейти в модерацию</a>.</p>
    <?php elseif ($u['role'] === 'employer'): ?>
      <p>Ваших вакансий: <b><?= count(array_filter(db_all('vacancies'), function ($v) use ($u) { return (int)$v['enterprise_id'] === (int)$u['company_id']; })) ?></b>. <a href="index.php?page=company_vacancies">Управлять вакансиями</a> · <a href="index.php?page=responses">Отклики</a>.</p>
    <?php elseif ($u['role'] === 'candidate'): ?>
      <p>Откликов: <b><?= count(array_filter(db_all('responses'), function ($r) use ($u) { return (int)$r['user_id'] === (int)$u['id']; })) ?></b>. <a href="index.php?page=my">Мои отклики и результаты</a> · <a href="index.php?page=games">Пройти тест</a>.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="section-head">
  <h2>Вакансии, которые уже ждут</h2>
  <a class="btn btn-outline" href="index.php?page=vacancies">Все вакансии →</a>
</div>
<div class="vac-grid">
  <?php foreach ($top as $v): $e = enterprise($v['enterprise_id']); ?>
    <a class="vac-card" href="index.php?page=vacancy&id=<?= (int)$v['id'] ?>">
      <div class="vac-card-top">
        <span class="badge" style="background:<?= esc($e['color']) ?>"><?= esc($e['short']) ?></span>
        <span class="vac-salary"><?= esc(vacancy_salary($v)) ?></span>
      </div>
      <h3><?= esc($v['title']) ?></h3>
      <p class="muted"><?= esc($v['city'] . ', ' . $v['region']) ?> · <?= esc($v['schedule']) ?></p>
      <div class="tags">
        <?php foreach (array_slice($v['skills'], 0, 3) as $s): ?><span><?= esc($s) ?></span><?php endforeach; ?>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<div class="stories-wrap">
  <div class="section-head">
    <h2>Истории сотрудников</h2>
    <p class="muted">Сторителлинг прямо в ленте вакансий — так будущий коллега рассказывает о пути</p>
  </div>
  <div class="stories-row">
    <?php foreach (array_slice($stories, 0, 4) as $s): $e = enterprise($s['enterprise_id']); ?>
      <div class="story-card">
        <div class="story-ava"><?= esc($s['photo']) ?></div>
        <div class="story-body">
          <div class="story-tag"><?= esc($s['tag']) ?></div>
          <h3><?= esc($s['name']) ?></h3>
          <div class="muted"><?= esc($s['role']) ?> · <?= esc($e['short']) ?></div>
          <blockquote>«<?= esc($s['quote']) ?>»</blockquote>
          <div class="story-path"><?= esc($s['path']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel timeline-panel">
  <h2>Как мы трудоустраиваем: интерактивный timeline</h2>
  <div class="timeline">
    <div class="t-step"><i>1</i><b>Отклик</b><span>Нажимаете «Откликнуться» или отправляете резюме</span></div>
    <div class="t-step"><i>2</i><b>Интервью</b><span>HR-созвон, техническая встреча с руководителем</span></div>
    <div class="t-step"><i>3</i><b>Оффер</b><span>Предложение с зарплатой, бонусами и переездом</span></div>
    <div class="t-step"><i>4</i><b>Адаптация</b><span>Наставник и план входа в должность</span></div>
    <div class="t-step"><i>5</i><b>Рост</b><span>Обучение, резерв, карьерный маршрут</span></div>
  </div>
</div>

<div class="reminder-block">
  <div>
    <h2>Ты у нас всегда можешь перезапустить карьеру</h2>
    <p>Другой регион, другая профессия, другое направление — с нами это нормально. Начните с теста и маршрута.</p>
  </div>
  <a class="btn" href="index.php?page=games">Выбрать направление</a>
</div>
