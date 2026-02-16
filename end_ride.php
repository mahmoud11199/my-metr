<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// ✅ تحقق من صلاحية المستخدم
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    echo json_encode(['error' => 'صلاحية غير كافية.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ✅ تحقق من CSRF
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    echo json_encode(['error' => 'طلب غير موثق.']);
    exit;
}

// ✅ استقبال بيانات النهاية
$data = json_decode(file_get_contents("php://input"), true);
$ride_id = $data['ride_id'] ?? null;
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;
$duration = $data['duration'] ?? null;
$distance = $data['distance'] ?? null;
$tracking = $data['tracking'] ?? [];

if (!$ride_id || !$lat || !$lng || !$duration || !$distance) {
    echo json_encode(['error' => '⚠️ بيانات الرحلة غير مكتملة.']);
    exit;
}

// ✅ جلب الرحلة والتحقق من أنها نشطة وتخص المستخدم
$stmt = $conn->prepare("SELECT * FROM rides WHERE id = ? AND user_id = ? AND status = 'started' LIMIT 1");
$stmt->bind_param("ii", $ride_id, $user_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();

if (!$ride) {
    echo json_encode(['error' => '⚠️ لا توجد رحلة نشطة مطابقة.']);
    exit;
}

// ✅ جلب الأسعار من الرحلة نفسها (ثابتة وقت البدء)
$fare_per_km = $ride['fare_per_km'];
$fare_per_min = $ride['fare_per_min'];
$base_fare = $ride['base_fare'];

// ✅ حساب التكلفة النهائية
$total = round($base_fare + ($distance * $fare_per_km) + ($duration * $fare_per_min), 2);

// ✅ حساب السرعة النهائية
$speed = $duration > 0 ? round(($distance / ($duration / 60)), 2) : 0;

// ✅ حفظ نقاط التتبع بصيغة JSON
$tracking_json = json_encode($tracking, JSON_UNESCAPED_UNICODE);

// ✅ وقت الانتهاء
$ended_at = date('Y-m-d H:i:s');

// ✅ تحديث الرحلة
$stmt = $conn->prepare("UPDATE rides SET 
    end_lat = ?, end_lng = ?, distance_km = ?, duration_min = ?, total_fare = ?, 
    status = 'ended', ended_at = ?, speed_avg = ?, live_tracking = ?
    WHERE id = ? AND user_id = ? AND status = 'started'");
$stmt->bind_param("dddddsssii", $lat, $lng, $distance, $duration, $total, $ended_at, $speed, $tracking_json, $ride_id, $user_id);

if (!$stmt->execute()) {
    echo json_encode(['error' => '❌ فشل تحديث بيانات الرحلة: ' . $stmt->error]);
    exit;
}

echo json_encode([
    'success' => true,
    'ride_id' => $ride_id, // ضروري
    'distance' => number_format($distance, 2),
    'duration' => $duration,
    'total' => number_format($total, 2),
    'share_code' => $ride['share_code'],
    'status' => 'بانتظار الدفع'
]);




