<?php
$defects = db_all('defects');
?>
<div class="panel">
  <div class="panel-head"><h2>Справочник типов дефектов флексоформ</h2></div>
  <p class="muted" style="margin-bottom:12px">Каталог используется моделью и регламентом ОТК для классификации и назначения вердикта.</p>
  <table>
    <tr><th>Тип</th><th>Критичность</th><th>Описание</th><th>Вероятная причина</th></tr>
    <?php foreach ($defects as $d): ?>
      <tr>
        <td>
          <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:<?= esc($d['color']) ?>;margin-right:8px"></span>
          <strong><?= esc($d['name']) ?></strong>
        </td>
        <td><span class="sev-<?= esc($d['severity']) ?>"><?= esc($d['severity'] === 'critical' ? 'Критический' : ($d['severity'] === 'major' ? 'Существенный' : 'Незначительный')) ?></span></td>
        <td><?= esc($d['desc']) ?></td>
        <td class="muted small"><?= esc($d['cause']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>