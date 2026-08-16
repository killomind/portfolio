<?php
$test = db_all('tests');
$result = isset($_SESSION['test_result']) ? $_SESSION['test_result'] : null;
$showResult = isset($_GET['result']);
?>
<div class="crumbs"><a href="index.php?page=games">Игровые механики</a> / Тест на совместимость</div>

<?php if ($result && $showResult): ?>
  <?php $p = $result['profile']; ?>
  <div class="page-hero">
    <h1>Результат теста</h1>
  </div>
  <div class="result-card">
    <div class="result-index">
      <div class="ring" style="background:conic-gradient(#16a34a <?= $result['index'] * 3.6 ?>deg, #e2e8f0 0)"><div class="ring-in"><b><?= $result['index'] ?>%</b><span>совместимость</span></div></div>
      <div>
        <div class="badge" style="background:#16a34a">Мы подходим друг другу</div>
        <h2><?= esc($p['title']) ?></h2>
        <p class="muted"><?= esc($p['subtitle']) ?></p>
      </div>
    </div>
    <p class="result-desc"><?= esc($p['desc']) ?></p>

    <div class="scores">
      <?php foreach ($result['scores'] as $tr => $pts): ?>
        <div class="score"><span><?= esc(ROUTE_LABELS[$tr]) ?></span><div class="bar"><i style="width:<?= (int)round($pts / 24 * 100) ?>%"></i></div><b><?= $pts ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section-head"><h2>Рекомендованные предприятия</h2></div>
  <div class="ent-grid">
    <?php foreach ($p['enterprise_ids'] as $eid): $e = enterprise($eid); if (!$e) continue; ?>
      <a class="ent-card" href="index.php?page=enterprise&id=<?= (int)$e['id'] ?>">
        <div class="ent-head" style="border-color:<?= esc($e['color']) ?>"><span class="ent-logo"><?= esc(mb_substr($e['short'], 0, 1)) ?></span><div><h3><?= esc($e['name']) ?></h3><div class="muted"><?= esc($e['city']) ?>, <?= esc($e['region']) ?></div></div></div>
        <p class="ent-desc"><?= esc(mb_substr($e['desc'], 0, 110)) ?>…</p>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="section-head"><h2>Рекомендованные вакансии</h2><p class="muted">Вы можете откликнуться сразу</p></div>
  <div class="vac-grid">
    <?php $shown = 0; foreach (published_vacancies() as $v): if (!in_array((int)$v['id'], $p['vacancy_ids'], true)) continue; if ($shown >= 4) break; $shown++; $e = enterprise($v['enterprise_id']); ?>
      <a class="vac-card" href="index.php?page=vacancy&id=<?= (int)$v['id'] ?>">
        <div class="vac-card-top"><span class="badge" style="background:<?= esc($e['color']) ?>"><?= esc($e['short']) ?></span><span class="vac-salary"><?= esc(vacancy_salary($v)) ?></span></div>
        <h3><?= esc($v['title']) ?></h3>
        <p class="muted"><?= esc($v['city']) ?> · <?= esc(vacancy_salary($v)) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
  <p><a class="btn" href="index.php?page=game_test">Пройти ещё раз</a></p>

<?php else: ?>
  <div class="page-hero">
    <h1>Тест на совместимость</h1>
    <p class="muted"><?= esc($test['intro']) ?> Длительность: <?= esc($test['duration']) ?>. Вопросов: <?= count($test['questions']) ?>.</p>
  </div>

  <form method="post" action="index.php" class="test-form">
    <input type="hidden" name="act" value="test_submit">
    <div class="test-progress-wrap"><div class="test-progress" id="prog"></div></div>
    <?php foreach ($test['questions'] as $qi => $q): ?>
      <div class="test-q" data-q="<?= $qi + 1 ?>">
        <div class="q-num">Вопрос <?= $qi + 1 ?> из <?= count($test['questions']) ?></div>
        <h3><?= esc($q['text']) ?></h3>
        <div class="q-answers">
          <?php foreach ($q['answers'] as $ai => $a): ?>
            <label class="q-ans"><input type="radio" name="q[<?= $q['id'] ?>]" value="<?= $ai ?>" required><span><?= esc($a['text']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="test-actions"><button class="btn btn-lg" type="submit">Получить результат</button></div>
  </form>

  <script>
  (function () {
    var qs = document.querySelectorAll('.test-q');
    var prog = document.getElementById('prog');
    qs.forEach(function (q) { q.style.display = 'none'; });
    if (qs.length) qs[0].style.display = '';
    var idx = 0;
    function runProg() {
      var filled = 0;
      qs.forEach(function (q) {
        var sel = q.querySelector('input[type=radio]:checked');
        if (sel) filled++;
      });
      prog.style.width = (filled / qs.length * 100) + '%';
    }
    qs.forEach(function (q, i) {
      var next = function () {
        var sel = q.querySelector('input[type=radio]:checked');
        if (!sel) { alert('Выберите ответ'); return; }
        if (i + 1 < qs.length) {
          q.style.display = 'none';
          qs[i + 1].style.display = '';
          window.scrollTo({top: 0, behavior: 'smooth'});
        } else {
          q.closest('form').submit();
        }
      };
      q.querySelectorAll('.q-ans').forEach(function (a) {
        a.addEventListener('click', function () { runProg(); setTimeout(next, 250); });
      });
    });
    runProg();
  })();
  </script>
<?php endif; ?>
