<?php
session_start();
require_once __DIR__ . '/lib.php';

if (isset($_GET['act']) && $_GET['act'] === 'logout') {
  unset($_SESSION['uid']);
  unset($_SESSION['test_result']);
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/actions.php';

$user = current_user();

if (isset($_GET['login']) && !$user) show_login();

$page = isset($_GET['page']) && is_file(__DIR__ . '/pages/' . $_GET['page'] . '.php') ? $_GET['page'] : 'home';

if (!in_array($page, PUBLIC_PAGES, true)) {
  if (!$user) show_login('Войдите, чтобы открыть этот раздел');
  if (!in_array($user['role'], PAGE_ROLES[$page], true)) {
    redirect('home', 'Нет доступа к разделу');
  }
}

layout_header($page, $user);
require __DIR__ . '/pages/' . $page . '.php';
layout_footer();
