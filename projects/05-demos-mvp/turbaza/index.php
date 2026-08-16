<?php
session_start();
require_once __DIR__ . '/lib.php';

if (isset($_GET['act']) && $_GET['act'] === 'logout') {
  unset($_SESSION['uid']);
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/actions.php';

$user = current_user();
if (!$user) show_login();

$page = isset($_GET['page']) && is_file(__DIR__ . '/pages/' . $_GET['page'] . '.php') ? $_GET['page'] : 'dashboard';

if (!in_array($page, array_keys(PAGE_ROLES), true) || !in_array($user['role'], PAGE_ROLES[$page], true)) {
  redirect('dashboard', 'Нет доступа');
}

layout_header($page, $user);
require __DIR__ . '/pages/' . $page . '.php';
layout_footer();
