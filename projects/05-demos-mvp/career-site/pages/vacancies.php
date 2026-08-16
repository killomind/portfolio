<?php
$u = current_user();
$enterprises = db_all('enterprises');
$vac = visible_vacancies($u);

$q = trim($_GET['q'] ?? '');
$fEnterprise = (int)($_GET['enterprise'] ?? 0);
$fCity = trim($_GET['city'] ?? '');
$fRegion = trim($_GET['region'] ?? '');
$fDirection = trim($_GET['direction'] ?? '');
$fSchedule = trim($_GET['schedule'] ?? '');
$fShift = isset($_GET['shift']);
$fReloc = isset($_GET['reloc']);
$fSkill = trim($_GET['skill'] ?? '');
$fSal = (int)($_GET['sal_min'] ?? 0);
$fExp = trim($_GET['exp'] ?? '');
$fEdu = trim($_GET['edu'] ?? '');

$list = array_values(array_filter($vac, function ($v) use ($q, $fEnterprise, $fCity, $fRegion, $fDirection, $fSchedule, $fShift, $fReloc, $fSkill, $fSal, $fExp, $fEdu) {
  $e = enterprise($v['enterprise_id']);
  if ($fEnterprise && (int)$v['enterprise_id'] !== $fEnterprise) return false;
  if ($fCity && mb_stripos($v['city'], $fCity) === false && mb_stripos($e['city'], $fCity) === false) return false;
  if ($fRegion && mb_stripos($v['region'], $fRegion) === false) return false;
  if ($fDirection && $v['direction'] !== $fDirection) return false;
  if ($fSchedule && $v['schedule'] !== $fSchedule) return false;
  if ($fShift && !$v['shift']) return false;
  if ($fReloc && !$v['relocation']) return false;
  if ($fSkill && !in_array($fSkill, $v['skills'], true)) return false;
  if ($fSal && (int)$v['salary_max'] > 0 && (int)$v['salary_max'] < $fSal) return false;
  if ($fExp && $v['experience'] !== $fExp) return false;
  if ($fEdu && $v['education'] !== $fEdu) return false;
  if ($q !== '') {
    $hay = mb_strtolower($v['title'] . ' ' . $v['city'] . ' ' . $v['region'] . ' ' . $v['direction'] . ' ' . implode(' ', $v['skills']) . ' ' . $e['name']);
    foreach (preg_split('/\s+/u', mb_strtolower($q)) as $tok) {
      if ($tok !== '' && mb_stripos($hay, $tok) === false) return false;
    }
  }
  return true;
}));

usort($list, function ($a, $b) { return strcmp($b['created'], $a['created']); });

$suggestions = [];
foreach ($vac as $v) {
  $e = enterprise($v['enterprise_id']);
  $suggestions[] = $v['title'];
  $suggestions[] = $v['city'];
  $suggestions[] = $v['direction'];
  $suggestions[] = $e['short'];
  foreach ($v['skills'] as $s) $suggestions[] = $s;
}
$suggestions = array_values(array_unique($suggestions));
sort($suggestions);

$cities = array_values(array_unique(array_map(function ($v) { return $v['city']; }, $vac)));
$regions = array_values(array_unique(array_map(function ($v) { return $v['region']; }, $vac)));
$schedules = array_values(array_unique(array_map(function ($v) { return $v['schedule']; }, $vac)));
$skills = [];
foreach ($vac as $v) foreach ($v['skills'] as $s) $skills[] = $s;
$skills = array_values(array_unique($skills));
sort($skills);
$exps = array_values(array_unique(array_map(function ($v) { return $v['experience']; }, $vac)));
$edus = array_values(array_unique(array_map(function ($v) { return $v['education']; }, $vac)));

$stories = db_all('stories');
$stIdx = 0;

$topDirections = [];
foreach ($vac as $v) $topDirections[$v['direction']] = isset($topDirections[$v['direction']]) ? $topDirections[$v['direction']] + 1 : 1;
arsort($topDirections);
?>
<div class="page-hero">
  <h1>Поиск вакансий</h1>
  <p class="muted">Интеллектуальный поиск: подсказки при вводе, фильтры по всем параметрам, умные рекомендации.</p>
</div>

<form method="get" action="index.php" class="search-bar">
  <input type="hidden" name="page" value="vacancies">
  <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Напишите, что ищете: должность / навык / предприятие / город…" list="vac-suggest" autocomplete="off">
  <datalist id="vac-suggest">
    <?php foreach ($suggestions as $s): ?><option value="<?= esc($s) ?>"><?php endforeach; ?>
  </datalist>
  <button class="btn" type="submit">Поиск</button>
  <a class="btn btn-outline" href="index.php?page=vacancies">Сбросить</a>
</form>

<div class="vac-layout">
  <aside class="filters">
    <h3>Фильтры</h3>
    <form method="get" action="index.php">
      <input type="hidden" name="page" value="vacancies">
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= esc($q) ?>"><?php endif; ?>
      <label>Предприятие
        <select name="enterprise">
          <option value="0">Все</option>
          <?php foreach ($enterprises as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $fEnterprise === (int)$e['id'] ? 'selected' : '' ?>><?= esc($e['short']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Город <input type="text" name="city" list="f-city" value="<?= esc($fCity) ?>"><datalist id="f-city"><?php foreach ($cities as $c): ?><option value="<?= esc($c) ?>"><?php endforeach; ?></datalist></label>
      <label>Регион
        <select name="region"><option value="">Все</option><?php foreach ($regions as $r): ?><option <?= $fRegion === $r ? 'selected' : '' ?>><?= esc($r) ?></option><?php endforeach; ?></select>
      </label>
      <label>Направление
        <select name="direction"><option value="">Все</option><?php foreach (DIRECTIONS as $d): ?><option <?= $fDirection === $d ? 'selected' : '' ?>><?= esc($d) ?></option><?php endforeach; ?></select>
      </label>
      <label>График
        <select name="schedule"><option value="">Любой</option><?php foreach ($schedules as $s): ?><option <?= $fSchedule === $s ? 'selected' : '' ?>><?= esc($s) ?></option><?php endforeach; ?></select>
      </label>
      <label class="chk"><input type="checkbox" name="shift" <?= $fShift ? 'checked' : '' ?>> Вахтовый метод</label>
      <label class="chk"><input type="checkbox" name="reloc" <?= $fReloc ? 'checked' : '' ?>> Оплата переезда</label>
      <label>Навык
        <select name="skill"><option value="">Любой</option><?php foreach ($skills as $s): ?><option <?= $fSkill === $s ? 'selected' : '' ?>><?= esc($s) ?></option><?php endforeach; ?></select>
      </label>
      <label>Зарплата от <input type="number" name="sal_min" min="0" step="5000" value="<?= $fSal ?: '' ?>" placeholder="напр. 100000"></label>
      <label>Опыт
        <select name="exp"><option value="">Любой</option><?php foreach ($exps as $x): ?><option <?= $fExp === $x ? 'selected' : '' ?>><?= esc($x) ?></option><?php endforeach; ?></select>
      </label>
      <label>Образование
        <select name="edu"><option value="">Любое</option><?php foreach ($edus as $e): ?><option <?= $fEdu === $e ? 'selected' : '' ?>><?= esc($e) ?></option><?php endforeach; ?></select>
      </label>
      <button class="btn" type="submit">Применить</button>
      <a class="btn btn-outline" href="index.php?page=vacancies">Сбросить</a>
    </form>
  </aside>

  <div class="vac-list-col">
    <div class="vac-count">Найдено вакансий: <b><?= count($list) ?></b></div>

    <?php if (!$list): ?>
      <div class="panel">
        <h2>По вашему запросу вакансий не найдено</h2>
        <p class="muted">Вот умные рекомендации — похожие позиции, которые могут вам подойти:</p>
        <div class="vac-grid">
          <?php $rec = array_values(array_filter($vac, function ($v) use ($fDirection, $q) {
            return ($fDirection && $v['direction'] === $fDirection) || ($q !== '' && (mb_stripos($v['title'], $q) !== false || mb_stripos($v['city'], $q) !== false));
          })); ?>
          <?php if (!$rec) $rec = array_slice($vac, 0, 4); ?>
          <?php foreach (array_slice($rec, 0, 4) as $v): $e = enterprise($v['enterprise_id']); ?>
            <a class="vac-card" href="index.php?page=vacancy&id=<?= (int)$v['id'] ?>">
              <div class="vac-card-top"><span class="badge" style="background:<?= esc($e['color']) ?>"><?= esc($e['short']) ?></span><span class="vac-salary"><?= esc(vacancy_salary($v)) ?></span></div>
              <h3><?= esc($v['title']) ?></h3>
              <p class="muted"><?= esc($v['city'] . ', ' . $v['region']) ?></p>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($list as $i => $v): $e = enterprise($v['enterprise_id']); ?>
      <?php if ($i > 0 && $i % 3 === 0 && $stories): $s = $stories[$stIdx % count($stories)]; $stIdx++; $se = enterprise($s['enterprise_id']); ?>
        <div class="story-inline">
          <div class="story-ava big"><?= esc($s['photo']) ?></div>
          <div>
            <div class="story-tag"><?= esc($s['tag']) ?></div>
            <h3>История: <?= esc($s['name']) ?>, <?= esc($s['role']) ?></h3>
            <blockquote>«<?= esc($s['quote']) ?>»</blockquote>
            <div class="story-path"><?= esc($s['path']) ?> · <?= esc($se['short']) ?></div>
          </div>
        </div>
      <?php endif; ?>
      <a class="vac-row" href="index.php?page=vacancy&id=<?= (int)$v['id'] ?>">
        <div class="vac-row-main">
          <div class="vac-card-top">
            <span class="badge" style="background:<?= esc($e['color']) ?>"><?= esc($e['short']) ?></span>
            <span class="vac-salary"><?= esc(vacancy_salary($v)) ?></span>
          </div>
          <h3><?= esc($v['title']) ?></h3>
          <p class="muted">📍 <?= esc($v['city'] . ', ' . $v['region']) ?> · <?= esc($v['schedule']) ?><?= $v['shift'] ? ' · Вахта' : '' ?><?= $v['relocation'] ? ' · Переезд' : '' ?></p>
          <div class="tags">
            <?php foreach (array_slice($v['skills'], 0, 4) as $s): ?><span><?= esc($s) ?></span><?php endforeach; ?>
          </div>
        </div>
        <div class="vac-row-meta">
          <span class="muted"><?= esc($v['experience']) ?></span>
          <span class="muted"><?= (int)$v['responses'] ?> откликов</span>
          <?= source_badge($v['source']) ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel top10-panel">
  <div class="section-head"><h2>Наши самые востребованные профессии</h2><span class="muted">топ-10 направлений по числу вакансий</span></div>
  <div class="top10">
    <?php $i = 0; foreach ($topDirections as $dir => $cnt): if ($i >= 10) break; $i++; ?>
      <a href="index.php?page=vacancies&direction=<?= urlencode($dir) ?>"><span class="top10-n"><?= $i ?></span> <?= esc($dir) ?> <b><?= $cnt ?></b></a>
    <?php endforeach; ?>
  </div>
</div>
