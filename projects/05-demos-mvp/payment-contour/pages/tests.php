<?php
$tests = run_tests();
$allPass = true;
foreach ($tests as $t) if (!$t['pass']) $allPass = false;
$passCount = 0;
foreach ($tests as $t) if ($t['pass']) $passCount++;
?>
<div class="panel">
  <h3>Автоматические тесты (инварианты целостности)</h3>
  <p class="muted small">Проверки выполняются над текущими данными контура: расчёты, идемпотентность, откат при сбое, снимки заказов, закрытие обязательств.</p>
  <form method="post" action="index.php">
    <input type="hidden" name="act" value="run_tests">
    <button class="btn btn-primary" type="submit">Запустить проверки</button>
  </form>
  <div class="ok-bar">Пройдено: <?= $passCount ?> / <?= count($tests) ?><?php if ($allPass): ?> ✓<?php else: ?> — есть расхождения<?php endif; ?></div>
  <table>
    <thead><tr><th>Проверка</th><th>Результат</th></tr></thead>
    <tbody>
      <?php foreach ($tests as $t): ?>
        <tr>
          <td><?= esc($t['name']) ?></td>
          <td>
            <?php if ($t['pass']): ?>
              <span class="badge" style="background:#16a34a">Пройден</span>
            <?php else: ?>
              <span class="badge" style="background:#dc2626">Не пройден</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h3>Покрытие тестами (в проекте)</h3>
  <table>
    <thead><tr><th>Модуль</th><th>Сценарии</th></tr></thead>
    <tbody>
      <tr><td>Расчёты</td><td class="small">детерминированные суммы, округление, выделение НДС, частичные суммы</td></tr>
      <tr><td>Идемпотентность</td><td class="small">повторная доставка webhook, конфликт ключа, повтор после сбоя</td></tr>
      <tr><td>Сценарии сбоев</td><td class="small">откат транзакции посреди обработки, недоступность ККТ, невалидное событие</td></tr>
      <tr><td>Целостность</td><td class="small">баланс счёта 51, снимки заказов, закрытие обязательств, регистр проводок</td></tr>
    </tbody>
  </table>
</div>