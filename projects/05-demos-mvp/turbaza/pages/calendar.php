<?php
$u = current_user();
$role = $u['role'];
$m = isset($_GET['m']) && preg_match('/^\d{4}-\d{2}$/', $_GET['m']) ? $_GET['m'] : date('Y-m');
$year = (int)substr($m, 0, 4);
$mon = (int)substr($m, 5, 2);
$first = mktime(0, 0, 0, $mon, 1, $year);
$daysInMonth = (int)date('t', $first);
$wd0 = (int)date('w', $first);
$wd0 = $wd0 === 0 ? 6 : $wd0 - 1;
$prev = date('Y-m', strtotime($m . '-01 -1 month'));
$next = date('Y-m', strtotime($m . '-01 +1 month'));
$today = date('Y-m-d');

$cottages = db_all('cottages');
?>
<div class="panel">
  <div class="panel-head">
    <h2>Календарь доступности</h2>
    <div class="cal-nav">
      <a class="btn btn-sm btn-ghost" href="index.php?page=calendar&m=<?= $prev ?>">←</a>
      <a class="btn btn-sm btn-ghost" href="index.php?page=calendar&m=<?= $next ?>">→</a>
    </div>
  </div>
  <p class="muted">Свободные дни — зелёные, занятые — красные. Дата выезда гостей считается свободной.</p>
  <div class="cal-wrap">
    <table class="cal-table">
      <tr>
        <th class="cal-cottage">Домик</th>
        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
          <?php $wd = (int)date('w', mktime(0, 0, 0, $mon, $d, $year)); $weekend = $wd === 0 || $wd === 6; ?>
          <th class="cal-day <?= $weekend ? 'wd' : '' ?>"><?= $d ?></th>
        <?php endfor; ?>
      </tr>
      <?php foreach ($cottages as $c): ?>
        <?php $occ = occupied_dates($c['id']); ?>
        <tr>
          <td class="cal-cottage"><a href="index.php?page=cottage&id=<?= $c['id'] ?>"><?= esc($c['name']) ?></a></td>
          <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
            <?php $day = sprintf('%04d-%02d-%02d', $year, $mon, $d); $wd = (int)date('w', mktime(0, 0, 0, $mon, $d, $year)); $weekend = $wd === 0 || $wd === 6; ?>
            <?php if (isset($occ[$day])): ?>
              <td class="cal-cell cal-busy" title="Занято: <?= esc($occ[$day]['guest_name']) ?>">&#8226;</td>
            <?php else: ?>
              <td class="cal-cell cal-free <?= $weekend ? 'wd' : '' ?> <?= $day === $today ? 'today' : '' ?>" title="Свободно">
                <?php if ($role === 'client' && strcmp($day, $today) >= 0): ?>
                  <a href="index.php?page=booking&cottage=<?= $c['id'] ?>&in=<?= $day ?>&out=<?= date('Y-m-d', strtotime($day . ' +1 day')) ?>">●</a>
                <?php else: ?>
                  ●
                <?php endif; ?>
              </td>
            <?php endif; ?>
          <?php endfor; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
