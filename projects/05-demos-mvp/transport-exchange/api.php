<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
init_db($pdo);

function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function point_to_segment_distance_km(float $lat, float $lng, float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $x = $lng * cos(deg2rad($lat));
    $y = $lat;
    $x1 = $lng1 * cos(deg2rad($lat1));
    $y1 = $lat1;
    $x2 = $lng2 * cos(deg2rad($lat2));
    $y2 = $lat2;
    $dx = $x2 - $x1;
    $dy = $y2 - $y1;
    if ($dx == 0 && $dy == 0) {
        return haversine_km($lat, $lng, $lat1, $lng1);
    }
    $t = (($x - $x1) * $dx + ($y - $y1) * $dy) / ($dx * $dx + $dy * $dy);
    $t = max(0, min(1, $t));
    $projX = $x1 + $t * $dx;
    $projY = $y1 + $t * $dy;
    $kmX = ($x - $projX) * 111.320 * cos(deg2rad($lat));
    $kmY = ($y - $projY) * 110.574;
    return sqrt($kmX * $kmX + $kmY * $kmY);
}

function distance_to_polyline(float $lat, float $lng, ?string $routeJson): float
{
    if (!$routeJson) {
        return INF;
    }
    $points = json_decode($routeJson, true);
    if (!$points || count($points) < 2) {
        return INF;
    }
    $min = INF;
    for ($i = 0; $i < count($points) - 1; $i++) {
        $d = point_to_segment_distance_km($lat, $lng, (float)$points[$i]['lat'], (float)$points[$i]['lng'], (float)$points[$i + 1]['lat'], (float)$points[$i + 1]['lng']);
        if ($d < $min) {
            $min = $d;
        }
    }
    return $min;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'search_cargoes') {
    $vehicleId = (int)($_GET['vehicle_id'] ?? 1);
    $radius = (float)($_GET['radius'] ?? 500);
    $sort = $_GET['sort'] ?? 'profit';
    $useRoute = (int)($_GET['use_route'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
    $stmt->execute([$vehicleId]);
    $vehicle = $stmt->fetch();
    if (!$vehicle) {
        echo json_encode(['error' => 'Vehicle not found']);
        exit;
    }

    $bodyType = $vehicle['body_type'];
    $lat = (float)$vehicle['base_lat'];
    $lng = (float)$vehicle['base_lng'];
    $route = $vehicle['route_json'];
    $corridor = (float)$vehicle['corridor_km'];

    $sql = "SELECT c.*, u.name AS owner_name, u.rating AS owner_rating, u.verified AS owner_verified
            FROM cargoes c
            JOIN users u ON u.id = c.user_id
            WHERE c.status = 'active'
              AND instr(',' || c.body_types || ',', ',' || ? || ',') > 0
              AND c.weight_t <= ?
              AND c.volume_m3 <= ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bodyType, $vehicle['capacity_t'], $vehicle['volume_m3']]);
    $cargoes = $stmt->fetchAll();

    $results = [];
    foreach ($cargoes as $c) {
        if ($useRoute && $route && $corridor > 0) {
            $dist = distance_to_polyline((float)$c['load_lat'], (float)$c['load_lng'], $route);
            if ($dist > $corridor) {
                continue;
            }
        } else {
            $dist = haversine_km($lat, $lng, (float)$c['load_lat'], (float)$c['load_lng']);
            if ($dist > $radius) {
                continue;
            }
        }
        $cost = $dist * (float)$vehicle['cost_per_km'];
        $profit = (float)$c['rate'] - $cost;
        $margin = (float)$c['rate'] > 0 ? round($profit / (float)$c['rate'] * 100, 1) : 0;
        $results[] = array_merge($c, [
            'distance_km' => round($dist, 1),
            'cost' => round($cost, 0),
            'profit' => round($profit, 0),
            'margin' => $margin,
            'vehicle_body_type' => $bodyType,
        ]);
    }

    usort($results, function ($a, $b) use ($sort) {
        if ($sort === 'profit') return $b['profit'] <=> $a['profit'];
        if ($sort === 'distance') return $a['distance_km'] <=> $b['distance_km'];
        if ($sort === 'rate') return $b['rate'] <=> $a['rate'];
        return 0;
    });

    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'search_vehicles') {
    $cargoId = (int)($_GET['cargo_id'] ?? 1);
    $radius = (float)($_GET['radius'] ?? 500);
    $sort = $_GET['sort'] ?? 'profit';

    $stmt = $pdo->prepare('SELECT * FROM cargoes WHERE id = ?');
    $stmt->execute([$cargoId]);
    $cargo = $stmt->fetch();
    if (!$cargo) {
        echo json_encode(['error' => 'Cargo not found']);
        exit;
    }

    $bodyTypes = array_map('trim', explode(',', $cargo['body_types']));
    $placeholders = implode(',', array_fill(0, count($bodyTypes), '?'));
    $sql = "SELECT v.*, u.name AS owner_name, u.rating AS owner_rating, u.verified AS owner_verified
            FROM vehicles v
            JOIN users u ON u.id = v.user_id
            WHERE v.body_type IN ($placeholders)
              AND v.capacity_t >= ?
              AND v.volume_m3 >= ?";
    $stmt = $pdo->prepare($sql);
    $params = array_merge($bodyTypes, [$cargo['weight_t'], $cargo['volume_m3']]);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll();

    $lat = (float)$cargo['load_lat'];
    $lng = (float)$cargo['load_lng'];
    $results = [];
    foreach ($vehicles as $v) {
        $dist = haversine_km($lat, $lng, (float)$v['base_lat'], (float)$v['base_lng']);
        if ($dist > $radius) {
            continue;
        }
        $cost = $dist * (float)$v['cost_per_km'];
        $profit = (float)$cargo['rate'] - $cost;
        $margin = (float)$cargo['rate'] > 0 ? round($profit / (float)$cargo['rate'] * 100, 1) : 0;
        $results[] = array_merge($v, [
            'distance_km' => round($dist, 1),
            'cost' => round($cost, 0),
            'profit' => round($profit, 0),
            'margin' => $margin,
            'cargo_body_type' => $cargo['body_types'],
        ]);
    }

    usort($results, function ($a, $b) use ($sort) {
        if ($sort === 'profit') return $b['profit'] <=> $a['profit'];
        if ($sort === 'distance') return $a['distance_km'] <=> $b['distance_km'];
        return 0;
    });

    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'respond') {
    $entityType = $_POST['entity_type'] ?? '';
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $cargoId = (int)($_POST['cargo_id'] ?? 0);
    $fromUserId = (int)($_POST['from_user_id'] ?? 0);

    if ($entityType === 'vehicle_to_cargo') {
        $stmt = $pdo->prepare('SELECT user_id FROM cargoes WHERE id = ?');
        $stmt->execute([$cargoId]);
        $row = $stmt->fetch();
        $toUserId = $row ? (int)$row['user_id'] : 0;
    } else {
        $stmt = $pdo->prepare('SELECT user_id FROM vehicles WHERE id = ?');
        $stmt->execute([$vehicleId]);
        $row = $stmt->fetch();
        $toUserId = $row ? (int)$row['user_id'] : 0;
    }

    if ($toUserId > 0) {
        $stmt = $pdo->prepare('INSERT INTO responses (cargo_id, vehicle_id, from_user_id, to_user_id, type, status, created_at) VALUES (?,?,?,?,?,\'pending\',datetime(\'now\',\'localtime\'))');
        $stmt->execute([$cargoId, $vehicleId, $fromUserId, $toUserId, $entityType]);
        $pdo->prepare('INSERT INTO audit_log (user_id, event, details) VALUES (?,?,?)')->execute([$fromUserId, 'respond', "Отклик на $entityType #$cargoId/$vehicleId"]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'to_user not found']);
    }
    exit;
}

if ($action === 'verify') {
    $inn = $_POST['inn'] ?? '';
    $ok = preg_match('/^\d{10,12}$/', $inn) && $inn[0] !== '0';
    echo json_encode(['ok' => $ok, 'status' => $ok ? 'verified' : 'not_verified']);
    exit;
}

echo json_encode(['error' => 'Unknown action']);