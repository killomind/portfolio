<?php
$u = current_user();
$users = db_all('users');
?>
<?php if ($u['role'] === 'admin'): ?>
  <div class="panel">
    <div class="panel-head"><h2>Добавить сотрудника</h2></div>
    <form method="post" action="index.php" class="form-grid">
      <input type="hidden" name="act" value="employee_save">
      <label>Имя<input type="text" name="name" required></label>
      <label>Логин<input type="text" name="login" required></label>
      <label>Пароль<input type="text" name="pass" required></label>
      <label>Роль
        <select name="role">
          <option value="operator">Контролёр ОТК</option>
          <option value="engineer">Инженер по качеству</option>
          <option value="manager">Руководитель ОТК</option>
          <option value="director">Директор производства</option>
          <option value="admin">Администратор</option>
        </select>
      </label>
      <label>Телефон<input type="text" name="phone"></label>
      <label>Смена
        <select name="shift">
          <option>День</option>
          <option>Ночь</option>
        </select>
      </label>
      <div style="align-self:end"><button class="btn" type="submit">Добавить</button></div>
    </form>
  </div>
<?php endif; ?>

<div class="panel">
  <table>
    <tr><th>Сотрудник</th><th>Роль</th><th>Телефон</th><th>Смена</th></tr>
    <?php foreach ($users as $x): ?>
      <tr>
        <td><strong><?= esc($x['name']) ?></strong></td>
        <td><?= esc(ROLES[$x['role']] ?? $x['role']) ?></td>
        <td><?= esc($x['phone'] ?? '—') ?></td>
        <td><?= esc($x['shift'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>