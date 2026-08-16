<?php
require_role(['admin', 'hr']);
$u = current_user();
$all = db_all('vacancies');
usort($all, function ($a, $b) { return strcmp($b['created'], $a['created']); });

$statuses = ['draft', 'on_moderation', 'published', 'rejected'];
$statCount = [];
foreach ($statuses as $s) $statCount[$s] = count(array_filter($all, function ($v) use ($s) { return $v['status'] === $s; }));
?>
<div class="cards">
  <div class="card"><div class="num"><?= $statCount['on_moderation'] ?></div><div class="lbl">На модерации</div></div>
  <div class="card"><div class="num"><?= $statCount['published'] ?></div><div class="lbl">Опубликовано</div></div>
  <div class="card"><div class="num"><?= $statCount['draft'] ?></div><div class="lbl">Черновиков</div></div>
  <div class="card"><div class="num"><?= $statCount['rejected'] ?></div><div class="lbl">Отклонено</div></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Все вакансии</h2></div>
  <table>
    <tr><th>Вакансия</th><th>Предприятие</th><th>Источник</th><th>Отклики</th><th>Статус</th><th></th></tr>
    <?php foreach ($all as $v): $e = enterprise($v['enterprise_id']); ?>
      <tr>
        <td><a href="index.php?page=vacancy&id=<?= (int)$v['id'] ?>"><b><?= esc($v['title']) ?></b></a><div class="muted"><?= esc($v['city']) ?> · <?= esc($v['direction']) ?></div></td>
        <td><?= esc($e['short']) ?></td>
        <td><?= source_badge($v['source']) ?></td>
        <td><?= (int)$v['responses'] ?></td>
        <td><?= moderation_badge($v['status']) ?></td>
        <td>
          <form method="post" action="index.php" class="inline">
            <input type="hidden" name="act" value="vacancy_status">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <select name="to" onchange="this.form.submit()">
              <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $v['status'] === $s ? 'selected' : '' ?>><?= esc(MODERATION[$s]) ?></option><?php endforeach; ?>
            </select>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
