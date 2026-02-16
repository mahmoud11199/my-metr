<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// ✅ تحقق من صلاحية المستخدم
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    echo json_encode(['error' => 'صلاحية غير كافية']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ✅ تحقق من CSRF
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    echo json_encode(['error' => 'طلب غير موثق.']);
    exit;
}

// ✅ جلب الرحلة النشطة للمستخدم
$stmt = $conn->prepare("SELECT 
        id, 
        start_lat, 
        start_lng, 
        created_at AS started_at, 
        share_code, 
        fare_per_km, 
        fare_per_min, 
        base_fare
    FROM rides 
    WHERE user_id = ? AND status = 'started' 
    ORDER BY created_at DESC 
    LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();

if (!$ride) {
    echo json_encode(['active' => false]);
    exit;
}

// ✅ لو فيه رحلة نشطة، رجع بياناتها
echo json_encode([
    'active' => true,
    'id' => $ride['id'],
    'start_lat' => $ride['start_lat'],
    'start_lng' => $ride['start_lng'],
    'started_at' => $ride['started_at'],
    'share_code' => $ride['share_code'],
    'fare_per_km' => $ride['fare_per_km'],
    'fare_per_min' => $ride['fare_per_min'],
    'base_fare' => $ride['base_fare']
]);
