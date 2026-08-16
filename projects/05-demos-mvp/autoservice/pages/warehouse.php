<?php
$u = current_user();
$branch = (int)($_GET['branch'] ?? 0);
if (!$branch) $branch = (int)$u['branch_id'];
if (!$branch) $branch = 1;

$branches = db_all('branches');
$parts = array_values(array_filter(db_all('parts'), function ($p) use ($branch) { return (int)$p['branch_id'] === $branch; }));
$movements = db_all('movements');
$recentMov = array_slice(array_reverse($movements), 0, 8);
?>
<div class="panel">
  <div class="panel-head">
    <h2>Склад: <?= esc(branch_name($branch)) ?></h2>
  </div>
  <form class="filters" method="get" action="index.php">
    <input type="hidden" name="page" value="warehouse">
    <select name="branch">
      <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $branch === (int)$b['id'] ? 'selected' : '' ?>><?= esc($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm" type="submit">Показать</button>
  </form>
  <table>
    <tr><th>Артикул</th><th>Наименование</th><th>Остаток</th><th>Минимум</th><th>Цена</th></tr>
    <?php foreach ($parts as $p): ?>
      <?php $low = (int)$p['qty'] < (int)$p['min_qty']; ?>
      <tr class="<?= $low ? 'row-low' : '' ?>">
        <td class="mono"><?= esc($p['sku']) ?></td>
        <td><?= esc($p['name']) ?></td>
        <td><span class="<?= $low ? 'low-stock' : '' ?>"><?= $p['qty'] ?> <?= esc($p['unit']) ?><?= $low ? ' — мало!' : '' ?></span></td>
        <td><?= $p['min_qty'] ?></td>
        <td><?= money($p['price']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="three-col">
  <div class="panel">
    <h2>Приход</h2>
    <form method="post" action="index.php" class="stack">
      <input type="hidden" name="act" value="part_in">
      <select name="part_id">
        <?php foreach ($parts as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="number" name="qty" value="1" min="1">
      <input type="text" name="note" placeholder="Примечание">
      <button class="btn" type="submit">Оприходовать</button>
    </form>
  </div>
  <div class="panel">
    <h2>Списание</h2>
    <form method="post" action="index.php" class="stack">
      <input type="hidden" name="act" value="part_out">
      <select name="part_id">
        <?php foreach ($parts as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="number" name="qty" value="1" min="1">
      <input type="text" name="note" placeholder="Причина">
      <button class="btn btn-danger" type="submit">Списать</button>
    </form>
  </div>
  <div class="panel">
    <h2>Перемещение</h2>
    <form method="post" action="index.php" class="stack">
      <input type="hidden" name="act" value="part_transfer">
      <select name="part_id">
        <?php foreach ($parts as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="number" name="qty" value="1" min="1">
      <select name="to_branch">
        <?php foreach ($branches as $b): if ((int)$b['id'] === $branch) continue; ?>
          <option value="<?= $b['id'] ?>">→ <?= esc($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Переместить</button>
    </form>
  </div>
</div>

<div class="panel">
  <h2>Последние движения</h2>
  <table>
    <tr><th>Дата</th><th>Операция</th><th>Деталь</th><th>Филиал</th><th>Кол-во</th><th>Кто</th></tr>
    <?php if (!$recentMov): ?><tr><td colspan="6" class="empty">Движений пока нет</td></tr><?php endif; ?>
    <?php foreach ($recentMov as $m): ?>
      <tr>
        <td><?= esc($m['at']) ?></td>
        <td>
          <?php
            $labels = ['in' => 'Приход', 'out' => 'Расход', 'transfer' => 'Перемещение'];
            echo '<span class="badge ' . ($m['type'] === 'in' ? 'b-ok' : ($m['type'] === 'transfer' ? 'b-warn' : 'b-bad')) . '">' . esc($labels[$m['type']] ?? $m['type']) . '</span>';
          ?>
        </td>
        <td><?= esc($m['part_name']) ?></td>
        <td><?= esc(branch_name($m['branch_id'])) ?></td>
        <td><?= $m['qty'] ?></td>
        <td><?= esc($m['user']) ?><?= $m['note'] ? '<br><small class="muted">' . esc($m['note']) . '</small>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
