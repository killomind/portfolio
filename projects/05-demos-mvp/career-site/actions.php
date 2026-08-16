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
        redirect('home', 'Добро пожаловать, ' . $x['name']);
      }
    }
    show_login('Неверный логин или пароль');
    break;

  case 'vacancy_save':
    require_role(['admin', 'employer']);
    $id = (int)($_POST['id'] ?? 0);
    $enterpriseId = (int)($_POST['enterprise_id'] ?? 0);
    if ($u['role'] === 'employer') $enterpriseId = (int)$u['company_id'];
    $row = [
      'title' => trim($_POST['title'] ?? ''),
      'enterprise_id' => $enterpriseId,
      'direction' => trim($_POST['direction'] ?? ''),
      'level' => trim($_POST['level'] ?? 'Специалист'),
      'city' => trim($_POST['city'] ?? ''),
      'region' => trim($_POST['region'] ?? ''),
      'country' => trim($_POST['country'] ?? 'Россия'),
      'schedule' => trim($_POST['schedule'] ?? ''),
      'shift' => isset($_POST['shift']),
      'relocation' => isset($_POST['relocation']),
      'skills' => array_values(array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')))),
      'salary_min' => (int)($_POST['salary_min'] ?? 0),
      'salary_max' => (int)($_POST['salary_max'] ?? 0),
      'experience' => trim($_POST['experience'] ?? ''),
      'education' => trim($_POST['education'] ?? ''),
      'description' => trim($_POST['description'] ?? ''),
      'duties' => array_values(array_filter(array_map('trim', explode("\n", $_POST['duties'] ?? '')))),
      'requirements' => array_values(array_filter(array_map('trim', explode("\n", $_POST['requirements'] ?? '')))),
      'stack' => array_values(array_filter(array_map('trim', explode(',', $_POST['stack'] ?? '')))),
      'conditions' => array_values(array_filter(array_map('trim', explode("\n", $_POST['conditions'] ?? '')))),
      'advantages' => array_values(array_filter(array_map('trim', explode(',', $_POST['advantages'] ?? '')))),
      'source' => trim($_POST['source'] ?? 'Поток'),
    ];
    if ($id > 0) {
      $v = db_find('vacancies', $id);
      if (!$v) redirect('company_vacancies', 'Вакансия не найдена');
      if ($u['role'] === 'employer' && (int)$v['enterprise_id'] !== (int)$u['company_id']) redirect('company_vacancies', 'Нет доступа');
      db_update('vacancies', $id, $row);
      redirect('company_vacancies', 'Вакансия обновлена');
    }
    $row['status'] = 'on_moderation';
    $row['created'] = date('Y-m-d');
    $row['views'] = 0;
    $row['responses'] = 0;
    db_insert('vacancies', $row);
    redirect('company_vacancies', 'Вакансия отправлена на модерацию');
    break;

  case 'vacancy_status':
    require_role(['admin', 'hr']);
    $id = (int)($_POST['id'] ?? 0);
    $to = trim($_POST['to'] ?? '');
    $v = db_find('vacancies', $id);
    if ($v) db_update('vacancies', $id, ['status' => $to]);
    redirect('moderation', 'Статус обновлён');
    break;

  case 'vacancy_delete':
    require_role(['admin', 'hr', 'employer']);
    $id = (int)($_POST['id'] ?? 0);
    $v = db_find('vacancies', $id);
    if ($v) {
      if ($u['role'] === 'employer' && (int)$v['enterprise_id'] !== (int)$u['company_id']) redirect('company_vacancies', 'Нет доступа');
      db_delete('vacancies', $id);
    }
    redirect('company_vacancies', 'Вакансия удалена');
    break;

  case 'respond':
    require_role(['candidate', 'guest']);
    $v = db_find('vacancies', (int)($_POST['vacancy_id'] ?? 0));
    if (!$v) redirect('vacancies', 'Вакансия не найдена');
    db_insert('responses', [
      'vacancy_id' => (int)$v['id'],
      'user_id' => $u ? (int)$u['id'] : 0,
      'candidate_name' => trim($_POST['name'] ?? ($u ? $u['name'] : '')),
      'candidate_email' => trim($_POST['email'] ?? ($u ? $u['email'] : '')),
      'candidate_phone' => trim($_POST['phone'] ?? ($u ? $u['phone'] : '')),
      'resume' => trim($_POST['resume'] ?? ''),
      'cover' => trim($_POST['cover'] ?? ''),
      'source' => 'На сайте',
      'status' => 'new',
      'created' => date('Y-m-d'),
    ]);
    db_update('vacancies', $v['id'], ['responses' => (int)$v['responses'] + 1]);
    redirect('vacancy&id=' . $v['id'], 'Спасибо! Ваш отклик отправлен');
    break;

  case 'resp_status':
    require_role(['admin', 'hr', 'employer']);
    $id = (int)($_POST['id'] ?? 0);
    $to = trim($_POST['to'] ?? '');
    $r = db_find('responses', $id);
    if ($r) {
      if ($u['role'] === 'employer') {
        $v = db_find('vacancies', $r['vacancy_id']);
        if (!$v || (int)$v['enterprise_id'] !== (int)$u['company_id']) redirect('responses', 'Нет доступа');
      }
      db_update('responses', $id, ['status' => $to]);
    }
    redirect('responses', 'Статус отклика обновлён');
    break;

  case 'resp_delete':
    require_role(['admin', 'hr', 'employer']);
    $id = (int)($_POST['id'] ?? 0);
    $r = db_find('responses', $id);
    if ($r) {
      if ($u['role'] === 'employer') {
        $v = db_find('vacancies', $r['vacancy_id']);
        if (!$v || (int)$v['enterprise_id'] !== (int)$u['company_id']) redirect('responses', 'Нет доступа');
      }
      db_delete('responses', $id);
    }
    redirect('responses', 'Отклик удалён');
    break;

  case 'test_submit':
    $test = db_all('tests');
    $answers = $_POST['q'] ?? [];
    $scores = ['worker' => 0, 'engineer' => 0, 'it' => 0, 'young' => 0, 'manager' => 0];
    foreach ($test['questions'] as $q) {
      if (!isset($answers[$q['id']])) continue;
      $idx = (int)$answers[$q['id']];
      if (!isset($q['answers'][$idx])) continue;
      foreach ($q['answers'][$idx]['score'] as $track => $pts) {
        $scores[$track] = (int)$scores[$track] + (int)$pts;
      }
    }
    $max = 0;
    $best = 'worker';
    foreach ($scores as $track => $pts) {
      if ($pts > $max) { $max = $pts; $best = $track; }
    }
    $total = count($test['questions']) * 2;
    $index = $total > 0 ? (int)round($max / $total * 100) : 0;
    $profile = $test['profiles'][$best];
    $result = [
      'profile_key' => $best,
      'index' => $index,
      'scores' => $scores,
      'profile' => $profile,
    ];
    $_SESSION['test_result'] = $result;
    redirect('game_test&result=1', 'Результат готов!');
    break;
}
