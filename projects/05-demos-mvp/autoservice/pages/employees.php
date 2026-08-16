<?php
$u = current_user();
$users = db_all('users');
if ($u['role'] === 'manager') $users = array_values(array_filter($users, function ($x) use ($u) { return (int)$x['branch_id'] === (int)$u['branch_id']; }));
?>
<div class="panel">
  <div class="panel-head"><h2>Сотрудники</h2></div>
  <table>
    <tr><th>Имя</th><th>Логин</th><th>Роль</th><th>Филиал</th><th>Телефон</th><th>Email</th></tr>
    <?php foreach ($users as $x): ?>
      <tr>
        <td><?= esc($x['name']) ?></td>
        <td class="mono"><?= esc($x['login']) ?></td>
        <td><span class="badge b-roles"><?= esc(ROLES[$x['role']] ?? $x['role']) ?></span></td>
        <td><?= $x['branch_id'] ? esc(branch_name($x['branch_id'])) : '—' ?></td>
        <td><?= esc($x['phone']) ?></td>
        <td><?= esc($x['email']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($u['role'] === 'admin'): ?>
  <div class="panel">
    <h2>Добавить сотрудника</h2>
    <form method="post" action="index.php" class="form-grid">
      <input type="hidden" name="act" value="employee_save">
      <label>Имя <input type="text" name="name" required></label>
      <label>Логин <input type="text" name="login" required></label>
      <label>Пароль <input type="text" name="pass" required></label>
      <label>Роль
        <select name="role">
          <option value="advisor">Мастер-приёмщик</option>
          <option value="mechanic">Механик</option>
          <option value="manager">Руководитель филиала</option>
          <option value="admin">Администратор</option>
        </select>
      </label>
      <label>Филиал
        <select name="branch_id">
          <option value="0">— сеть —</option>
          <?php foreach (db_all('branches') as $b): ?>
            <option value="<?= $b['id'] ?>"><?= esc($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Телефон <input type="text" name="phone"></label>
      <label>Email <input type="email" name="email"></label>
      <button class="btn" type="submit">Добавить</button>
    </form>
  </div>
<?php endif; ?>
