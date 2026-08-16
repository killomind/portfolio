<?php
$checks = db_all('checks');
$v = isset($_GET['v']) ? $_GET['v'] : '';
if ($v) $checks = array_values(array_filter($checks, function ($c) use ($v) { return $c['verdict'] === $v; }));
usort($checks, function ($a, $b) { return strcmp($b['at'], $a['at']); });
?>
<div class="filters">
  <form method="get" action="index.php" class="inline-form">
    <input type="hidden" name="page" value="checks">
    <select name="v" onchange="this.form.submit()">
      <option value="">Все вердикты</option>
      <option value="ok" <?= $v === 'ok' ? 'selected' : '' ?>>Годна</option>
      <option value="rework" <?= $v === 'rework' ? 'selected' : '' ?>>На доработку</option>
      <option value="reject" <?= $v === 'reject' ? 'selected' : '' ?>>Брак</option>
    </select>
    <noscript><button class="btn">Применить</button></noscript>
  </form>
</div>

<div class="panel">
  <table>
    <tr><th>ID</th><th>Время</th><th>Форма</th><th>Клиент</th><th>Оператор</th><th>Дефектов</th><th>Вердикт</th><th></th></tr>
    <?php if (!$checks): ?>
      <tr><td colspan="8" class="empty">Проверок нет</td></tr>
    <?php endif; ?>
    <?php foreach ($checks as $c): ?>
      <?php $form = db_find('forms', $c['form_id']); ?>
      <tr>
        <td class="mono">#<?= $c['id'] ?></td>
        <td class="mono"><?= esc($c['at']) ?></td>
        <td><?= $form ? esc($form['custom_no']) : '—' ?></td>
        <td><?= $form ? esc($form['client']) : '—' ?></td>
        <td><?= esc(user_name($c['operator_id'])) ?></td>
        <td><?= count($c['found']) ?></td>
        <td><?= verdict_badge($c['verdict']) ?></td>
        <td><a class="btn btn-sm" href="index.php?page=check_view&id=<?= $c['id'] ?>">Открыть</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>