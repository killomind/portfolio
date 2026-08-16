<?php
require_role(['admin', 'hr', 'employer']);
$u = current_user();
$all = db_all('responses');
usort($all, function ($a, $b) { return strcmp($b['created'], $a['created']); });

$my = array_values(array_filter($all, function ($r) use ($u) {
  if ($u['role'] === 'admin' || $u['role'] === 'hr') return true;
  $v = db_find('vacancies', $r['vacancy_id']);
  return $v && (int)$v['enterprise_id'] === (int)$u['company_id'];
}));

$cnt = ['new' => 0, 'interview' => 0, 'offer' => 0, 'hired' => 0, 'rejected' => 0];
foreach ($my as $r) $cnt[$r['status']] = isset($cnt[$r['status']]) ? $cnt[$r['status']] + 1 : 0;
?>
<div class="cards">
  <div class="card"><div class="num"><?= count($my) ?></div><div class="lbl">Всего откликов</div></div>
  <div class="card"><div class="num"><?= $cnt['new'] ?></div><div class="lbl">Новых</div></div>
  <div class="card"><div class="num"><?= $cnt['interview'] ?></div><div class="lbl">На собеседовании</div></div>
  <div class="card"><div class="num"><?= $cnt['offer'] + $cnt['hired'] ?></div><div class="lbl">Офферы / нанятые</div></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Отклики кандидатов</h2></div>
  <table>
    <tr><th>Кандидат</th><th>Вакансия</th><th>Источник</th><th>Дата</th><th>Статус</th><th></th></tr>
    <?php foreach ($my as $r): $v = db_find('vacancies', $r['vacancy_id']); $e = $v ? enterprise($v['enterprise_id']) : null; ?>
      <tr>
        <td>
          <b><?= esc($r['candidate_name']) ?></b>
          <div class="muted"><?= esc($r['candidate_phone']) ?><br><?= esc($r['candidate_email']) ?></div>
          <?php if ($r['cover']): ?><div class="small muted">«<?= esc($r['cover']) ?>»</div><?php endif; ?>
        </td>
        <td><?= $v ? '<a href="index.php?page=vacancy&id=' . (int)$v['id'] . '">' . esc($v['title']) . '</a>' : '—' ?><div class="muted"><?= $e ? esc($e['short']) : '' ?></div></td>
        <td><?= source_badge($r['source']) ?></td>
        <td><?= esc($r['created']) ?></td>
        <td><?= resp_badge($r['status']) ?></td>
        <td>
          <form method="post" action="index.php" class="inline">
            <input type="hidden" name="act" value="resp_status">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <select name="to" onchange="this.form.submit()">
              <?php foreach (RESP_STATUS as $k => $label): ?><option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
            </select>
          </form>
          <form method="post" action="index.php" class="inline"><input type="hidden" name="act" value="resp_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">×</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
