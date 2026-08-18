<?php
// pages/calendar.php — расписание, свободные окна, перенос, блокировка
require_login();
$user = current_user();

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$view = isset($_GET['view']) ? $_GET['view'] : 'day'; // day | week

$appointments = get_appointments();
$blocks = get_blocks();
$services = get_services();
$clients = get_clients();

if ($view == 'week') {
    $start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
} else {
    $start = $date;
    $end = $date;
}

$period = new DatePeriod(
    new DateTime($start),
    new DateInterval('P1D'),
    new DateTime($end . ' +1 day')
);

// Фильтрация записей для отображения
$appointments_filtered = array_filter($appointments, function($a) use ($start, $end) {
    return $a['date'] >= $start && $a['date'] <= $end && $a['status'] != 'cancelled';
});
$blocks_filtered = array_filter($blocks, function($b) use ($start, $end) {
    return $b['date'] >= $start && $b['date'] <= $end;
});

// Сортировка
usort($appointments_filtered, function($a, $b) {
    return strcmp($a['date'] . $a['time'], $b['date'] . $b['time']);
});
usort($blocks_filtered, function($a, $b) {
    return strcmp($a['date'] . $a['time'], $b['date'] . $b['time']);
});

// Функция для получения свободных слотов на дату (используем из actions.php? Но она там определена после, мы можем включить actions.php? Нет, лучше продублировать или вынести в lib. Для простоты — продублируем.
function calendar_get_free_slots($date) {
    $working_hours = ['10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];
    $appointments = get_appointments();
    $blocks = get_blocks();
    $busy = [];
    foreach ($appointments as $a) {
        if ($a['date'] == $date && $a['status'] != 'cancelled') {
            $busy[] = $a['time'];
            $duration = $a['duration'] ?? 60;
            $slots_occupied = (int)ceil($duration / 60);
            $idx = array_search($a['time'], $working_hours);
            if ($idx !== false) {
                for ($i = 1; $i < $slots_occupied; $i++) {
                    if (isset($working_hours[$idx + $i])) $busy[] = $working_hours[$idx + $i];
                }
            }
        }
    }
    foreach ($blocks as $b) {
        if ($b['date'] == $date) {
            $busy[] = $b['time'];
            $duration = $b['duration'] ?? 60;
            $slots_occupied = (int)ceil($duration / 60);
            $idx = array_search($b['time'], $working_hours);
            if ($idx !== false) {
                for ($i = 1; $i < $slots_occupied; $i++) {
                    if (isset($working_hours[$idx + $i])) $busy[] = $working_hours[$idx + $i];
                }
            }
        }
    }
    $busy = array_unique($busy);
    return array_values(array_filter($working_hours, function($slot) use ($busy) {
        return !in_array($slot, $busy);
    }));
}
?>

<div class="page-header">
    <h1>Записи / Календарь</h1>
    <div class="view-switcher">
        <a href="index.php?page=calendar&view=day&date=<?php echo $date; ?>" class="btn btn--small <?php echo $view == 'day' ? 'active' : ''; ?>">День</a>
        <a href="index.php?page=calendar&view=week&date=<?php echo $date; ?>" class="btn btn--small <?php echo $view == 'week' ? 'active' : ''; ?>">Неделя</a>
    </div>
</div>

<div class="calendar-nav">
    <a href="index.php?page=calendar&view=<?php echo $view; ?>&date=<?php echo date('Y-m-d', strtotime($start . ' -1 ' . ($view == 'week' ? 'week' : 'day'))); ?>" class="btn btn--link">← Назад</a>
    <span class="calendar-period">
        <?php echo format_date($start); ?> — <?php echo format_date($end); ?>
    </span>
    <a href="index.php?page=calendar&view=<?php echo $view; ?>&date=<?php echo date('Y-m-d', strtotime($end . ' +1 ' . ($view == 'week' ? 'week' : 'day'))); ?>" class="btn btn--link">Вперёд →</a>
</div>

<?php if ($view == 'day'): ?>
    <div class="card">
        <h2>Расписание на <?php echo format_date($date); ?></h2>
        <div class="schedule">
            <?php
            $working_hours = ['10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
            foreach ($working_hours as $hour):
                $app_at_hour = array_filter($appointments_filtered, function($a) use ($hour) {
                    return $a['time'] == $hour;
                });
                $block_at_hour = array_filter($blocks_filtered, function($b) use ($hour) {
                    return $b['time'] == $hour;
                });
            ?>
                <div class="schedule-row">
                    <div class="schedule-time"><?php echo $hour; ?></div>
                    <div class="schedule-content">
                        <?php foreach ($app_at_hour as $app): 
                            $client = find_client_by_id($app['client_id']);
                            $service = find_service_by_id($app['service_id']);
                        ?>
                            <div class="appointment-card appointment-card--<?php echo $app['status']; ?>">
                                <div class="appointment-card__client">
                                    <?php echo htmlspecialchars($client ? $client['name'] : '—'); ?>
                                </div>
                                <div class="appointment-card__service">
                                    <?php echo htmlspecialchars($service ? $service['name'] : '—'); ?>
                                </div>
                                <div class="appointment-card__actions">
                                    <?php if ($user['role'] == 'operator' || $user['role'] == 'admin'): ?>
                                        <button class="btn btn--tiny" onclick="document.getElementById('move-<?php echo $app['id']; ?>').style.display='block'">Перенести</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Скрытая форма переноса -->
                            <div id="move-<?php echo $app['id']; ?>" class="modal" style="display:none;">
                                <div class="modal-content">
                                    <span class="modal-close" onclick="document.getElementById('move-<?php echo $app['id']; ?>').style.display='none'">&times;</span>
                                    <h3>Перенос записи</h3>
                                    <form action="actions.php" method="post">
                                        <input type="hidden" name="action" value="move_appointment">
                                        <input type="hidden" name="appointment_id" value="<?php echo $app['id']; ?>">
                                        <div class="form-group">
                                            <label>Новая дата</label>
                                            <input type="date" name="new_date" value="<?php echo $app['date']; ?>" class="input">
                                        </div>
                                        <div class="form-group">
                                            <label>Новое время</label>
                                            <select name="new_time" class="input">
                                                <?php foreach ($working_hours as $h): ?>
                                                    <option value="<?php echo $h; ?>" <?php echo $h == $app['time'] ? 'selected' : ''; ?>><?php echo $h; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn--primary">Перенести</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($block_at_hour as $block): ?>
                            <div class="appointment-card appointment-card--blocked">
                                <div class="appointment-card__client">⛔ Блокировка</div>
                                <div class="appointment-card__service"><?php echo htmlspecialchars($block['reason'] ?: 'Занято'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>Свободные окна на <?php echo format_date($date); ?></h2>
        <?php $free = calendar_get_free_slots($date); ?>
        <?php if (!empty($free)): ?>
            <div class="free-slots">
                <?php foreach ($free as $slot): ?>
                    <span class="badge badge--free"><?php echo $slot; ?></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">Свободных окон нет.</p>
        <?php endif; ?>
    </div>

    <?php if ($user['role'] == 'operator' || $user['role'] == 'admin'): ?>
        <div class="card">
            <h2>Добавить запись</h2>
            <form action="actions.php" method="post" class="form">
                <input type="hidden" name="action" value="add_appointment">
                <div class="form-row">
                    <div class="form-group">
                        <label>Клиент</label>
                        <select name="client_id" class="input" required>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Услуга</label>
                        <select name="service_id" class="input" required>
                            <?php foreach ($services as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo $s['duration']; ?> мин)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Дата</label>
                        <input type="date" name="date" value="<?php echo $date; ?>" class="input" required>
                    </div>
                    <div class="form-group">
                        <label>Время</label>
                        <select name="time" class="input" required>
                            <?php foreach ($working_hours as $h): ?>
                                <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Заметка</label>
                    <input type="text" name="note" class="input" placeholder="Необязательно">
                </div>
                <button type="submit" class="btn btn--primary">Добавить</button>
            </form>
        </div>

        <div class="card">
            <h2>Заблокировать время</h2>
            <form action="actions.php" method="post" class="form">
                <input type="hidden" name="action" value="block_time">
                <div class="form-row">
                    <div class="form-group">
                        <label>Дата</label>
                        <input type="date" name="date" value="<?php echo $date; ?>" class="input" required>
                    </div>
                    <div class="form-group">
                        <label>Время</label>
                        <select name="time" class="input" required>
                            <?php foreach ($working_hours as $h): ?>
                                <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Длительность (мин)</label>
                        <input type="number" name="duration" value="60" class="input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Причина</label>
                    <input type="text" name="reason" class="input" placeholder="Например, обед">
                </div>
                <button type="submit" class="btn btn--danger">Заблокировать</button>
            </form>
        </div>
    <?php endif; ?>

<?php else: // week view ?>
    <div class="week-grid">
        <?php foreach ($period as $date_obj): 
            $d = $date_obj->format('Y-m-d');
            $day_appointments = array_filter($appointments_filtered, function($a) use ($d) {
                return $a['date'] == $d;
            });
            $day_blocks = array_filter($blocks_filtered, function($b) use ($d) {
                return $b['date'] == $d;
            });
            $day_name = date('D', strtotime($d));
            $is_today = $d == date('Y-m-d');
        ?>
            <div class="week-day <?php echo $is_today ? 'week-day--today' : ''; ?>">
                <div class="week-day__header">
                    <span class="week-day__name"><?php echo $day_name; ?></span>
                    <span class="week-day__date"><?php echo date('d.m', strtotime($d)); ?></span>
                </div>
                <div class="week-day__body">
                    <?php foreach ($day_appointments as $app): 
                        $client = find_client_by_id($app['client_id']);
                        $service = find_service_by_id($app['service_id']);
                    ?>
                        <div class="mini-appointment">
                            <span class="mini-appointment__time"><?php echo $app['time']; ?></span>
                            <span class="mini-appointment__client"><?php echo htmlspecialchars($client ? $client['name'] : '—'); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($day_blocks as $block): ?>
                        <div class="mini-appointment mini-appointment--block">
                            <span class="mini-appointment__time"><?php echo $block['time']; ?></span>
                            <span class="mini-appointment__client">⛔ <?php echo htmlspecialchars($block['reason'] ?: 'Блок'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>