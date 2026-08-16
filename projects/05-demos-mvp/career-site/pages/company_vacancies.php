<?php
require_role(['admin', 'employer']);
$u = current_user();
$companyId = $u['role'] === 'employer' ? (int)$u['company_id'] : 0;
$my = array_values(array_filter(db_all('vacancies'), function ($v) use ($companyId, $u) {
  if ($u['role'] === 'admin') return true;
  return (int)$v['enterprise_id'] === $companyId;
}));
usort($my, function ($a, $b) { return strcmp($b['created'], $a['created']); });

$editId = (int)($_GET['edit'] ?? 0);
$editMode = false;
$o = null;
if ($editId) {
  $o = db_find('vacancies', $editId);
  if ($o && ($u['role'] === 'admin' || (int)$o['enterprise_id'] === $companyId)) $editMode = true;
}
?>
<div class="cards">
  <div class="card"><div class="num"><?= count($my) ?></div><div class="lbl">Всего вакансий</div></div>
  <div class="card"><div class="num"><?= count(array_filter($my, function ($v) { return $v['status'] === 'published'; })) ?></div><div class="lbl">Опубликовано</div></div>
  <div class="card"><div class="num"><?= count(array_filter($my, function ($v) { return $v['status'] === 'on_moderation'; })) ?></div><div class="lbl">На модерации</div></div>
</div>

<?php if ($editMode): ?>
  <div class="panel">
    <div class="panel-head"><h2><?= $o['id'] ? 'Редактирование вакансии' : 'Новая вакансия' ?></h2></div>
    <form method="post" action="index.php" class="form-grid">
      <input type="hidden" name="act" value="vacancy_save">
      <?php if ($o['id']): ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><?php endif; ?>
      <?php if ($u['role'] === 'admin'): $wells = db_all('enterprises'); ?>
        <label>Предприятие <select name="enterprise_id"><?php foreach ($wells as $w): ?><option value="<?= $w['id'] ?>" <?= (int)$o['enterprise_id'] === (int)$w['id'] ? 'selected' : '' ?>><?= esc($w['name']) ?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
      <label class="full">Название <input type="text" name="title" value="<?= esc($o['title']) ?>" required></label>
      <label>Направление <select name="direction"><?php foreach (DIRECTIONS as $d): ?><option <?= $o['direction'] === $d ? 'selected' : '' ?>><?= esc($d) ?></option><?php endforeach; ?></select></label>
      <label>Уровень <input type="text" name="level" value="<?= esc($o['level']) ?>"></label>
      <label>Город <input type="text" name="city" value="<?= esc($o['city']) ?>"></label>
      <label>Регион <input type="text" name="region" value="<?= esc($o['region']) ?>"></label>
      <label>Страна <input type="text" name="country" value="<?= esc($o['country']) ?>"></label>
      <label>График <input type="text" name="schedule" value="<?= esc($o['schedule']) ?>"></label>
      <label>Опыт <input type="text" name="experience" value="<?= esc($o['experience']) ?>"></label>
      <label>Образование <input type="text" name="education" value="<?= esc($o['education']) ?>"></label>
      <label>Зарплата от <input type="number" name="salary_min" value="<?= (int)$o['salary_min'] ?>"></label>
      <label>Зарплата до <input type="number" name="salary_max" value="<?= (int)$o['salary_max'] ?>"></label>
      <label>Источник <select name="source"><option <?= $o['source'] === 'Поток' ? 'selected' : '' ?>>Поток</option><option <?= $o['source'] === 'HeadHunter' ? 'selected' : '' ?>>HeadHunter</option><option <?= $o['source'] === 'На сайте' ? 'selected' : '' ?>>На сайте</option></select></label>
      <label>Ключевые навыки (через запятую) <input type="text" name="skills" value="<?= esc(implode(', ', $o['skills'])) ?>"></label>
      <label>Технологии/стек (через запятую) <input type="text" name="stack" value="<?= esc(implode(', ', $o['stack'])) ?>"></label>
      <label class="chk"><input type="checkbox" name="shift" <?= $o['shift'] ? 'checked' : '' ?>> Вахтовый метод</label>
      <label class="chk"><input type="checkbox" name="relocation" <?= $o['relocation'] ? 'checked' : '' ?>> Оплата переезда</label>
      <label class="full">Описание <textarea name="description" rows="3"><?= esc($o['description']) ?></textarea></label>
      <label class="full">Обязанности (по строке) <textarea name="duties" rows="4"><?= esc(implode("\n", $o['duties'])) ?></textarea></label>
      <label class="full">Требования (по строке) <textarea name="requirements" rows="4"><?= esc(implode("\n", $o['requirements'])) ?></textarea></label>
      <label class="full">Условия (по строке) <textarea name="conditions" rows="3"><?= esc(implode("\n", $o['conditions'])) ?></textarea></label>
      <label class="full">Преимущества (через запятую) <input type="text" name="advantages" value="<?= esc(implode(', ', $o['advantages'])) ?>"></label>
      <div class="full"><button class="btn" type="submit"><?= $o['id'] ? 'Сохранить изменения' : 'Отправить на модерацию' ?></button>
      <?php if ($o['id']): ?><a class="btn btn-outline" href="index.php?page=company_vacancies">Отмена</a><?php endif; ?></div>
    </form>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head"><h2>Мои вакансии</h2><a class="btn" href="index.php?page=company_vacancies&edit=0">+ Новая вакансия</a></div>
  <table>
    <tr><th>Вакансия</th><th>Город</th><th>Источник</th><th>Статус</th><th>Отклики</th><th></th></tr>
    <?php foreach ($my as $v): ?>
      <tr>
        <td><b><?= esc($v['title']) ?></b><div class="muted"><?= esc(vacancy_salary($v)) ?></div></td>
        <td><?= esc($v['city']) ?></td>
        <td><?= source_badge($v['source']) ?></td>
        <td><?= moderation_badge($v['status']) ?></td>
        <td><?= (int)$v['responses'] ?></td>
        <td class="nowrap">
          <a class="btn btn-sm" href="index.php?page=company_vacancies&edit=<?= (int)$v['id'] ?>">Ред.</a>
          <form method="post" action="index.php" class="inline"><input type="hidden" name="act" value="vacancy_delete"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Удалить</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
