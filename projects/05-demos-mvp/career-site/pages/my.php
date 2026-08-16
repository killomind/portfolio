<?php
require_role(['candidate']);
$u = current_user();
$responses = array_values(array_filter(db_all('responses'), function ($r) use ($u) { return (int)$r['user_id'] === (int)$u['id']; }));
usort($responses, function ($a, $b) { return strcmp($b['created'], $a['created']); });
$result = isset($_SESSION['test_result']) ? $_SESSION['test_result'] : null;
?>
<div class="page-hero">
  <h1>Личный кабинет соискателя</h1>
  <p class="muted"><?= esc($u['name']) ?> · <?= esc($u['email']) ?> · <?= esc($u['phone']) ?></p>
</div>

<div class="cards">
  <div class="card"><div class="num"><?= count($responses) ?></div><div class="lbl">Откликов</div></div>
  <div class="card"><div class="num"><?= count(array_filter($responses, function ($r) { return $r['status'] === 'interview' || $r['status'] === 'offer'; })) ?></div><div class="lbl">Активные</div></div>
  <div class="card"><div class="num"><?= count(array_filter($responses, function ($r) { return $r['status'] === 'new'; })) ?></div><div class="lbl">Новых</div></div>
</div>

<?php if ($result): ?>
  <div class="panel">
    <div class="panel-head"><h2>Результат теста на совместимость</h2><a class="btn btn-outline" href="index.php?page=game_test&result=1">Смотреть</a></div>
    <p><b><?= esc($result['profile']['title']) ?></b> · индекс совместимости <b><?= $result['index'] ?>%</b></p>
  </div>
<?php else: ?>
  <div class="panel">
    <div class="panel-head"><h2>Пройдите тест и выберите маршрут</h2><a class="btn" href="index.php?page=games">К игровым механикам</a></div>
    <p class="muted">Узнайте свой профиль и рекомендации по вакансиям и предприятиям.</p>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head"><h2>Мои отклики</h2></div>
  <?php if (!$responses): ?><p class="muted">Вы ещё не откликались на вакансии. <a href="index.php?page=vacancies">Перейти к поиску</a>.</p><?php endif; ?>
  <table>
    <tr><th>Вакансия</th><th>Предприятие</th><th>Дата</th><th>Статус</th></tr>
    <?php foreach ($responses as $r): $v = db_find('vacancies', $r['vacancy_id']); $e = $v ? enterprise($v['enterprise_id']) : null; ?>
      <tr>
        <td><?= $v ? '<a href="index.php?page=vacancy&id=' . (int)$v['id'] . '"><b>' . esc($v['title']) . '</b></a>' : '—' ?></td>
        <td><?= $e ? esc($e['short']) : '—' ?></td>
        <td><?= esc($r['created']) ?></td>
        <td><?= resp_badge($r['status']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
