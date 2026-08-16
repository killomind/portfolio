<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$act = isset($_POST['act']) ? $_POST['act'] : '';
$u = current_user();

switch ($act) {

  case 'login':
    $login = trim($_POST['login'] ?? '');
    $pass = trim($_POST['pass'] ?? '');
    $role = trim($_POST['role'] ?? '');
    if ($role) {
      $login = $role;
      $pass = $role;
    }
    foreach (db_all('users') as $x) {
      if ($x['login'] === $login && $x['pass'] === $pass) {
        $_SESSION['uid'] = (int)$x['id'];
        redirect('dashboard', 'Добро пожаловать, ' . $x['name']);
      }
    }
    show_login('Неверный логин или пароль');
    break;

  case 'scan_run':
    require_role(['operator', 'engineer', 'manager', 'admin']);
    $formId = (int)($_POST['form_id'] ?? 0);
    $f = db_find('forms', $formId);
    if (!$f) redirect('scan', 'Форма не найдена');
    $latent = $f['latent'] ?? [];
    if (!$latent) redirect('scan', 'Для формы не задан эталонный набор дефектов');
    $found = [];
    foreach ($latent as $d) {
      $t = defect_type($d['type']);
      if (!$t) continue;
      $miss = isset($d['miss']) ? (int)$d['miss'] : 0;
      $skip = false;
      $rv = mt_rand(1, 100);
      if ($miss === 1 && $rv <= 22) $skip = true;
      if ($miss === 0 && $rv <= 3) $skip = true;
      if ($skip) continue;
      $j = [
        'type' => $d['type'],
        'confidence' => round(0.93 + mt_rand(-7, 5) / 100, 2),
        'x' => (float)$d['x'],
        'y' => (float)$d['y'],
        'size' => isset($d['size']) ? (float)$d['size'] : 1.0,
      ];
      if (isset($d['note'])) $j['note'] = $d['note'];
      $found[] = $j;
    }
    $severityScore = ['critical' => 3, 'major' => 2, 'minor' => 1];
    $worst = 0;
    $worstKey = null;
    foreach ($found as $d) {
      $t = defect_type($d['type']);
      if (!$t) continue;
      $s = isset($severityScore[$t['severity']]) ? $severityScore[$t['severity']] : 1;
      if ($s > $worst) { $worst = $s; $worstKey = $t['key']; }
    }
    $verdict = 'ok';
    $rejectReason = '';
    if ($worst === 3) { $verdict = 'reject'; $rejectReason = 'Критический дефект: ' . ($worstKey ? defect_type($worstKey)['name'] : ''); }
    elseif ($worst === 2) { $verdict = 'rework'; $rejectReason = 'Существенные дефекты требуют доработки формы'; }

    $checkId = db_insert('checks', [
      'form_id' => $formId,
      'operator_id' => (int)$u['id'],
      'at' => now_ts(),
      'duration_sec' => mt_rand(38, 96),
      'verdict' => $verdict,
      'reason' => $rejectReason,
      'found' => $found,
    ]);

    $status = $verdict === 'ok' ? 'ok' : ($verdict === 'rework' ? 'rework' : 'reject');
    db_update('forms', $formId, ['status' => $status, 'last_check_id' => $checkId]);

    if ($verdict === 'ok') redirect('check_view&id=' . $checkId, 'Проверка завершена: форма годна');
    if ($verdict === 'rework') redirect('check_view&id=' . $checkId, 'Проверка завершена: форма отправлена на доработку');
    redirect('check_view&id=' . $checkId, 'Проверка завершена: форма признана браком');
    break;

  case 'employee_save':
    require_role(['admin']);
    db_insert('users', [
      'login' => trim($_POST['login'] ?? ''),
      'pass' => trim($_POST['pass'] ?? ''),
      'name' => trim($_POST['name'] ?? ''),
      'role' => trim($_POST['role'] ?? 'operator'),
      'phone' => trim($_POST['phone'] ?? ''),
      'shift' => trim($_POST['shift'] ?? 'День'),
    ]);
    redirect('employees', 'Сотрудник добавлен');
    break;

  case 'form_save':
    require_role(['admin', 'engineer', 'manager']);
    $f = [
      'custom_no' => trim($_POST['custom_no'] ?? ''),
      'client' => trim($_POST['client'] ?? ''),
      'product' => trim($_POST['product'] ?? ''),
      'shape' => trim($_POST['shape'] ?? 'этикетка'),
      'size_w' => (float)($_POST['size_w'] ?? 0),
      'size_h' => (float)($_POST['size_h'] ?? 0),
      'raster' => trim($_POST['raster'] ?? ''),
      'polymer' => trim($_POST['polymer'] ?? ''),
      'thickness' => trim($_POST['thickness'] ?? ''),
      'status' => 'queue',
      'latent' => [],
    ];
    if ($f['size_w'] < 100 || $f['size_h'] < 50) redirect('forms', 'Укажите корректные габариты формы');
    db_insert('forms', $f);
    redirect('forms', 'Форма добавлена в очередь контроля');
    break;

  case 'model_train':
    require_role(['admin', 'engineer']);
    $m = db_find('models', 1);
    if (!$m) {
      db_insert('models', ['version' => 'v1.4 beta', 'ap50' => 0.981, 'recall' => 0.966, 'precision' => 0.954, 'train_at' => date('Y-m-d H:i'), 'samples' => 1840, 'failures' => 62]);
      redirect('models', 'Модель переобучена (демо: метрики обновлены)');
    }
    db_update('models', 1, [
      'version' => 'v1.4 beta',
      'ap50' => 0.981,
      'recall' => 0.966,
      'precision' => 0.954,
      'train_at' => now_ts(),
      'samples' => 1840,
      'failures' => 62,
    ]);
    redirect('models', 'Модель переобучена (демо: метрики обновлены)');
    break;
}