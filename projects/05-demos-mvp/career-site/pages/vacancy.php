<?php
$u = current_user();
$id = (int)($_GET['id'] ?? 0);
$v = db_find('vacancies', $id);
if (!$v) redirect('vacancies', 'Вакансия не найдена');

$allowed = false;
if ($v['status'] === 'published') $allowed = true;
if ($u) {
  if ($u['role'] === 'admin' || $u['role'] === 'hr') $allowed = true;
  if ($u['role'] === 'employer' && (int)$v['enterprise_id'] === (int)$u['company_id']) $allowed = true;
}
if (!$allowed) redirect('vacancies', 'Вакансия недоступна');

$e = enterprise($v['enterprise_id']);
db_update('vacancies', $v['id'], ['views' => (int)$v['views'] + 1]);
$v = db_find('vacancies', $id);

$similar = array_values(array_filter(published_vacancies(), function ($x) use ($v) {
  return (int)$x['id'] !== (int)$v['id'] && ($x['direction'] === $v['direction'] || (int)$x['enterprise_id'] === (int)$v['enterprise_id']);
}));
$similar = array_slice($similar, 0, 3);

$canRespond = $u && in_array($u['role'], ['candidate', 'guest'], true);
?>
<div class="crumbs"><a href="index.php?page=vacancies">Вакансии</a> / <a href="index.php?page=enterprise&id=<?= (int)$e['id'] ?>"><?= esc($e['short']) ?></a> / <?= esc($v['title']) ?></div>

<div class="vac-head">
  <div class="vac-head-info">
    <div class="vac-card-top">
      <span class="badge" style="background:<?= esc($e['color']) ?>"><?= esc($e['name']) ?></span>
      <?= source_badge($v['source']) ?>
      <?= moderation_badge($v['status']) ?>
    </div>
    <h1><?= esc($v['title']) ?></h1>
    <p class="vac-salary"><?= esc(vacancy_salary($v)) ?> <span class="muted">до вычета налогов</span></p>
    <div class="vac-facts">
      <span>📍 <?= esc($v['city'] . ', ' . $v['region'] . ($v['country'] !== 'Россия' ? ', ' . $v['country'] : '')) ?></span>
      <span>🗓 <?= esc($v['schedule']) ?></span>
      <span>💼 <?= esc($v['experience']) ?></span>
      <span>🎓 <?= esc($v['education']) ?></span>
      <?php if ($v['shift']): ?><span>🔁 Вахтовый метод</span><?php endif; ?>
      <?php if ($v['relocation']): ?><span>🚚 Оплата переезда</span><?php endif; ?>
    </div>
  </div>
  <div class="vac-head-actions">
    <a class="btn btn-share" href="javascript:void(0)" onclick="navigator.share ? navigator.share({title: document.title, url: location.href}) : alert('Ссылка: ' + location.href)">Поделиться</a>
    <?php if ($canRespond): ?>
      <a class="btn btn-lg" href="#respond-form">Откликнуться</a>
    <?php endif; ?>
  </div>
</div>

<div class="vac-detail">
  <div class="vac-body">
    <nav class="vac-anchor" id="anchor-nav">
      <a href="#sec-desc">О вакансии</a>
      <a href="#sec-duties">Обязанности</a>
      <a href="#sec-req">Требования</a>
      <?php if ($v['stack']): ?><a href="#sec-stack">Технологии</a><?php endif; ?>
      <a href="#sec-cond">Условия</a>
      <a href="#sec-adv">Преимущества</a>
      <a href="#sec-team">Команда</a>
      <a href="#sec-loc">Локация</a>
    </nav>

    <section id="sec-desc" class="vac-sec"><h2>О вакансии</h2><p><?= esc($v['description']) ?></p></section>

    <section id="sec-duties" class="vac-sec">
      <h2>Обязанности</h2>
      <ul><?php foreach ($v['duties'] as $d): ?><li><?= esc($d) ?></li><?php endforeach; ?></ul>
    </section>

    <section id="sec-req" class="vac-sec">
      <h2>Требования</h2>
      <ul><?php foreach ($v['requirements'] as $d): ?><li><?= esc($d) ?></li><?php endforeach; ?></ul>
    </section>

    <?php if ($v['stack']): ?>
      <section id="sec-stack" class="vac-sec">
        <h2>Технологии и стек</h2>
        <div class="tags"><?php foreach ($v['stack'] as $s): ?><span><?= esc($s) ?></span><?php endforeach; ?></div>
      </section>
    <?php endif; ?>

    <section id="sec-cond" class="vac-sec">
      <h2>Условия</h2>
      <ul><?php foreach ($v['conditions'] as $d): ?><li><?= esc($d) ?></li><?php endforeach; ?></ul>
    </section>

    <section id="sec-adv" class="vac-sec">
      <h2>Что мы предлагаем</h2>
      <div class="adv-grid"><?php foreach ($v['advantages'] as $a): ?><div class="adv-chip">✓ <?= esc($a) ?></div><?php endforeach; ?></div>
    </section>

    <section id="sec-team" class="vac-sec"><h2>Команда</h2><p><?= esc($v['team']) ?></p></section>

    <section id="sec-loc" class="vac-sec">
      <h2>Локация</h2>
      <div class="loc-card">
        <div class="loc-ico">🏢</div>
        <div>
          <b><?= esc($e['name']) ?></b>
          <p class="muted"><?= esc($e['contacts']['address']) ?></p>
          <a href="index.php?page=enterprise&id=<?= (int)$e['id'] ?>">Страница предприятия и карта →</a>
        </div>
      </div>
    </section>
  </div>

  <aside class="vac-side">
    <?php if ($canRespond): ?>
      <div class="panel" id="respond-form">
        <h3>Откликнуться</h3>
        <form method="post" action="index.php">
          <input type="hidden" name="act" value="respond">
          <input type="hidden" name="vacancy_id" value="<?= (int)$v['id'] ?>">
          <label>Имя <input type="text" name="name" value="<?= esc($u['name'] === 'Гость' ? '' : $u['name']) ?>" required></label>
          <label>Email <input type="email" name="email" value="<?= esc($u['email']) ?>" required></label>
          <label>Телефон <input type="text" name="phone" value="<?= esc($u['phone']) ?>" required></label>
          <label>Резюме
            <select name="resume">
              <option>Загрузить файл (PDF/DOC)</option>
              <option>Отправить моё резюме из профиля</option>
              <option>Оформить резюме по ссылке</option>
            </select>
          </label>
          <label>Сопроводительное
            <textarea name="cover" rows="3" placeholder="Почему вы хотите работать у нас?"><?= esc($u['role'] === 'candidate' ? 'Опыт работы по специальности, готов пройти собеседование в удобное время.' : '') ?></textarea>
          </label>
          <button class="btn" type="submit">Отправить отклик</button>
        </form>
      </div>
    <?php else: ?>
      <div class="panel">
        <h3>Отклик</h3>
        <p class="muted">Чтобы откликнуться на вакансию, войдите как соискатель или гость.</p>
        <a class="btn" href="index.php?login=1">Войти</a>
      </div>
    <?php endif; ?>

    <div class="panel">
      <h3>Похожие вакансии</h3>
      <?php foreach ($similar as $s): $se = enterprise($s['enterprise_id']); ?>
        <a class="mini-vac" href="index.php?page=vacancy&id=<?= (int)$s['id'] ?>">
          <b><?= esc($s['title']) ?></b>
          <span class="muted"><?= esc($se['short']) ?> · <?= esc(vacancy_salary($s)) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$similar): ?><p class="muted">Похожих вакансий пока нет.</p><?php endif; ?>
    </div>
  </aside>
</div>

<script>
var anchors = document.querySelectorAll('#anchor-nav a');
window.addEventListener('scroll', function () {
  var pos = window.scrollY + 120, cur = '';
  document.querySelectorAll('.vac-sec').forEach(function (sec) {
    if (sec.offsetTop <= pos) cur = '#' + sec.id;
  });
  anchors.forEach(function (a) {
    a.classList.toggle('active', a.getAttribute('href') === cur);
  });
});
</script>
