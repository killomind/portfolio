<?php
// actions.php — обработчики POST-запросов с PRG-паттерном
require_once __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'login':
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $user = find_user_by_id($user_id);
        if ($user) {
            login($user_id);
            set_flash('success', 'Добро пожаловать, ' . $user['name'] . '!');
            header('Location: index.php?page=dashboard');
        } else {
            set_flash('error', 'Пользователь не найден');
            header('Location: index.php');
        }
        exit;

    case 'logout':
        logout();
        header('Location: index.php');
        exit;

    case 'add_appointment':
        require_role('operator'); // только оператор и админ
        $client_id = (int)$_POST['client_id'];
        $service_id = (int)$_POST['service_id'];
        $date = $_POST['date'];
        $time = $_POST['time'];
        $note = isset($_POST['note']) ? trim($_POST['note']) : '';

        // Проверки
        if (!$client_id || !$service_id || !$date || !$time) {
            set_flash('error', 'Все поля обязательны');
            header('Location: index.php?page=calendar');
            exit;
        }

        $appointments = get_appointments();
        $new_id = count($appointments) ? max(array_column($appointments, 'id')) + 1 : 1;
        $appointments[] = [
            'id' => $new_id,
            'client_id' => $client_id,
            'service_id' => $service_id,
            'date' => $date,
            'time' => $time,
            'duration' => find_service_by_id($service_id)['duration'] ?? 60,
            'status' => 'confirmed',
            'note' => $note
        ];
        save_json('appointments.json', $appointments);
        set_flash('success', 'Запись добавлена');
        header('Location: index.php?page=calendar');
        exit;

    case 'move_appointment':
        require_role('operator');
        $appointment_id = (int)$_POST['appointment_id'];
        $new_date = $_POST['new_date'];
        $new_time = $_POST['new_time'];

        if (!$appointment_id || !$new_date || !$new_time) {
            set_flash('error', 'Укажите новое время');
            header('Location: index.php?page=calendar');
            exit;
        }

        $appointments = get_appointments();
        $found = false;
        foreach ($appointments as &$app) {
            if ($app['id'] == $appointment_id) {
                $app['date'] = $new_date;
                $app['time'] = $new_time;
                $found = true;
                break;
            }
        }
        if ($found) {
            save_json('appointments.json', $appointments);
            set_flash('success', 'Запись перенесена');
        } else {
            set_flash('error', 'Запись не найдена');
        }
        header('Location: index.php?page=calendar');
        exit;

    case 'block_time':
        require_role('operator');
        $date = $_POST['date'];
        $time = $_POST['time'];
        $duration = (int)$_POST['duration'];
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        if (!$date || !$time || !$duration) {
            set_flash('error', 'Укажите дату, время и длительность');
            header('Location: index.php?page=calendar');
            exit;
        }

        $blocks = get_blocks();
        $new_id = count($blocks) ? max(array_column($blocks, 'id')) + 1 : 1;
        $blocks[] = [
            'id' => $new_id,
            'date' => $date,
            'time' => $time,
            'duration' => $duration,
            'reason' => $reason
        ];
        save_json('blocks.json', $blocks);
        set_flash('success', 'Время заблокировано');
        header('Location: index.php?page=calendar');
        exit;

    case 'ai_query':
        require_login();
        $query = isset($_POST['query']) ? trim($_POST['query']) : '';
        if (!$query) {
            set_flash('error', 'Введите запрос');
            header('Location: index.php?page=ai');
            exit;
        }

        $user = current_user();
        $result = process_ai_query($query, $user);

        // Сохранение в лог
        $ai_log = get_ai_log();
        $new_id = count($ai_log) ? max(array_column($ai_log, 'id')) + 1 : 1;
        $ai_log[] = [
            'id' => $new_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user['id'],
            'query' => $query,
            'response' => $result['response'],
            'action_type' => $result['action_type'],
            'data' => $result['data'] ?? null
        ];
        save_json('ai_log.json', $ai_log);

        // Результат сохраняем в сессию для отображения
        $_SESSION['ai_result'] = $result;
        header('Location: index.php?page=ai');
        exit;

    default:
        header('Location: index.php');
        exit;
}

/**
 * Простая эмуляция AI-обработки естественного языка
 */
function process_ai_query($query, $user) {
    $query_lower = mb_strtolower($query, 'UTF-8');

    // Прибыль за неделю
    if (mb_strpos($query_lower, 'прибыль') !== false || mb_strpos($query_lower, 'выручк') !== false) {
        $days = 7;
        if (mb_strpos($query_lower, 'прошл') !== false) {
            // прошлая неделя
            $start = date('Y-m-d', strtotime('monday last week'));
            $end = date('Y-m-d', strtotime('sunday last week'));
        } else {
            // текущая неделя или последние 7 дней
            $start = date('Y-m-d', strtotime('-6 days'));
            $end = date('Y-m-d');
        }
        $payments = get_payments();
        $expenses = get_expenses();
        $daily = [];
        $period = new DatePeriod(
            new DateTime($start),
            new DateInterval('P1D'),
            new DateTime($end . ' +1 day')
        );
        foreach ($period as $date) {
            $d = $date->format('Y-m-d');
            $rev = 0;
            foreach ($payments as $p) {
                if ($p['date'] == $d && ($p['type'] == 'service' || $p['type'] == 'product')) {
                    $rev += $p['amount'];
                }
            }
            $exp = 0;
            foreach ($expenses as $e) {
                if ($e['date'] == $d) {
                    $exp += $e['amount'];
                }
            }
            $daily[$d] = ['revenue' => $rev, 'expense' => $exp, 'net' => $rev - $exp];
        }
        $total_net = array_sum(array_column($daily, 'net'));
        $total_rev = array_sum(array_column($daily, 'revenue'));
        $response = "Чистая прибыль за период с " . format_date($start) . " по " . format_date($end) . ": " . format_currency($total_net) . ". Выручка: " . format_currency($total_rev) . ".";
        return [
            'response' => $response,
            'action_type' => 'chart',
            'data' => [
                'type' => 'profit',
                'period' => ['start' => $start, 'end' => $end],
                'daily' => $daily
            ]
        ];
    }

    // Свободные окна на завтра
    if (mb_strpos($query_lower, 'свободные окна') !== false || mb_strpos($query_lower, 'свободн') !== false) {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $free_slots = get_free_slots($tomorrow);
        $response = "Свободные окна на завтра (" . format_date($tomorrow) . "): ";
        if (empty($free_slots)) {
            $response .= "нет свободных окон.";
        } else {
            $times = array_map(function($slot) { return $slot['time']; }, $free_slots);
            $response .= implode(', ', $times) . ".";
        }
        return [
            'response' => $response,
            'action_type' => 'list',
            'data' => [
                'type' => 'free_slots',
                'date' => $tomorrow,
                'slots' => $free_slots
            ]
        ];
    }

    // Перенос записи
    if (mb_strpos($query_lower, 'перенес') !== false || mb_strpos($query_lower, 'перенос') !== false) {
        $appointments = get_appointments();
        $upcoming = array_filter($appointments, function($a) {
            return $a['date'] >= date('Y-m-d') && $a['status'] != 'cancelled';
        });
        usort($upcoming, function($a, $b) {
            return strcmp($a['date'] . $a['time'], $b['date'] . $b['time']);
        });
        $response = "Выберите запись для переноса:";
        return [
            'response' => $response,
            'action_type' => 'move_appointment',
            'data' => [
                'appointments' => $upcoming
            ]
        ];
    }

    // Отправка базы на почту
    if (mb_strpos($query_lower, 'баз') !== false && (mb_strpos($query_lower, 'почт') !== false || mb_strpos($query_lower, 'отправ') !== false)) {
        $clients = get_clients();
        $email = $user['email'] ?? 'owner@salon.ru';
        $response = "База клиентов (" . count($clients) . " записей) отправлена на почту " . $email . ".";
        return [
            'response' => $response,
            'action_type' => 'send_email',
            'data' => [
                'email' => $email,
                'clients_count' => count($clients)
            ]
        ];
    }

    // Проблемы бизнеса
    if (mb_strpos($query_lower, 'проблем') !== false || mb_strpos($query_lower, 'анализ') !== false || mb_strpos($query_lower, 'что с бизнес') !== false) {
        $appointments = get_appointments();
        $payments = get_payments();
        $clients = get_clients();
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        $week_appointments = array_filter($appointments, function($a) use ($week_start, $week_end) {
            return $a['date'] >= $week_start && $a['date'] <= $week_end;
        });
        $week_revenue = 0;
        foreach ($payments as $p) {
            if ($p['date'] >= $week_start && $p['date'] <= $week_end && ($p['type'] == 'service' || $p['type'] == 'product')) {
                $week_revenue += $p['amount'];
            }
        }
        $total_appointments = count($week_appointments);
        $new_clients = count(array_filter($clients, function($c) {
            return !empty($c['visits']) && count($c['visits']) == 1; // упрощённо
        }));
        $occupancy = $total_appointments * 60 / (7 * 8 * 60) * 100; // 7 дней по 8 часов
        $occupancy = min(100, round($occupancy, 1));
        $response = "Анализ недели: записей — {$total_appointments}, выручка — " . format_currency($week_revenue) . ", загрузка — {$occupancy}%. Рекомендация: ";
        if ($occupancy < 50) {
            $response .= "низкая загрузка, стоит запустить акцию.";
        } elseif ($occupancy < 80) {
            $response .= "стабильная работа, можно увеличить рекламу.";
        } else {
            $response .= "высокая загрузка, подумайте о расширении.";
        }
        return [
            'response' => $response,
            'action_type' => 'summary',
            'data' => [
                'week_appointments' => $total_appointments,
                'week_revenue' => $week_revenue,
                'occupancy' => $occupancy
            ]
        ];
    }

    // Неизвестный запрос
    $response = "Я понимаю запросы о прибыли, свободных окнах, переносе записей, отправке базы на почту и анализе бизнеса. Попробуйте сформулировать иначе.";
    return [
        'response' => $response,
        'action_type' => 'unknown',
        'data' => null
    ];
}

/**
 * Получение свободных слотов на дату (упрощённо)
 */
function get_free_slots($date) {
    $working_hours = ['10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];
    $appointments = get_appointments();
    $blocks = get_blocks();
    $busy = [];
    foreach ($appointments as $a) {
        if ($a['date'] == $date && $a['status'] != 'cancelled') {
            $busy[] = $a['time'];
            // учитываем длительность: следующие слоты тоже заняты
            $duration = $a['duration'] ?? 60;
            $slots_occupied = (int)ceil($duration / 60);
            $idx = array_search($a['time'], $working_hours);
            if ($idx !== false) {
                for ($i = 1; $i < $slots_occupied; $i++) {
                    if (isset($working_hours[$idx + $i])) {
                        $busy[] = $working_hours[$idx + $i];
                    }
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
                    if (isset($working_hours[$idx + $i])) {
                        $busy[] = $working_hours[$idx + $i];
                    }
                }
            }
        }
    }
    $busy = array_unique($busy);
    return array_values(array_filter($working_hours, function($slot) use ($busy) {
        return !in_array($slot, $busy);
    }));
}