<?php
session_start();
require_once '../config.php';

// التأكد من أن الطلب من نوع POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
    exit;
}

// تأكد من وجود بيانات JSON
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid JSON"]);
    exit;
}

// تأكد من وجود ride_id
if (!isset($data['ride_id']) || empty($data['ride_id'])) {
    echo json_encode(["success" => false, "error" => "Missing ride_id"]);
    exit;
}

$ride_id = intval($data['ride_id']);

// تأكد من وجود النقاط
if (!isset($data['points']) || !is_array($data['points']) || count($data['points']) === 0) {
    echo json_encode(["success" => false, "error" => "No points received"]);
    exit;
}

$points = $data['points'];

// تحضير جملة الإدخال
$stmt = $conn->prepare("
    INSERT INTO ride_points (ride_id, latitude, longitude, speed, direction, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");

if (!$stmt) {
    echo json_encode(["success" => false, "error" => "DB prepare error"]);
    exit;
}

$stmt->bind_param("iddid", $ride_id, $lat, $lng, $speed, $direction);

$successCount = 0;

foreach ($points as $p) {

    // التحقق من الحقول الأساسية
    if (!isset($p['lat']) || !isset($p['lng'])) {
        continue; // تجاهل النقاط الناقصة
    }

    $lat = floatval($p['lat']);
    $lng = floatval($p['lng']);
    $speed = isset($p['speed']) ? floatval($p['speed']) : 0;
    $direction = isset($p['direction']) ? intval($p['direction']) : 0;

    if ($stmt->execute()) {
        $successCount++;
    }
}

echo json_encode([
    "success" => true,
    "saved_points" => $successCount
]);
exit;
