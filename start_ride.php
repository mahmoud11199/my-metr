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

// ✅ استقبال الإحداثيات
$data = json_decode(file_get_contents("php://input"), true);
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

if (!$lat || !$lng) {
    echo json_encode(['error' => '⚠️ إحداثيات الموقع غير مكتملة.']);
    exit;
}

// ✅ التحقق من وجود رحلة نشطة مسبقًا
$check = $conn->prepare("SELECT id, share_code FROM rides WHERE user_id = ? AND status = 'started'");
$check->bind_param("i", $user_id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows > 0) {
    $ride = $res->fetch_assoc();
    echo json_encode(['error' => '⚠️ لديك رحلة نشطة بالفعل.', 'ride_id' => $ride['id'], 'share_code' => $ride['share_code']]);
    exit;
}

// ✅ جلب إعدادات المستخدم
$stmt = $conn->prepare("SELECT km_price FROM user_preferences WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$km_price = $stmt->get_result()->fetch_assoc()['km_price'] ?? null;

if (!$km_price) {
    echo json_encode(['error' => '⚠️ لم يتم ضبط سعر الكيلو الخاص بك.']);
    exit;
}

// ✅ جلب إعدادات الأدمن
$settings = $conn->query("SELECT * FROM ride_settings ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$minute_price = $settings['min_minute_price'] ?? 1;
$base_fare = $settings['min_base_fare'] ?? 5;

// ✅ جلب رصيد المستخدم
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc()['balance'] ?? 0;

if ($balance < 1) {
    echo json_encode(['error' => '⚠️ رصيدك غير كافي لبدء الرحلة.']);
    exit;
}

// ✅ ابدأ معاملة لضمان التكامل
$conn->begin_transaction();

try {
    // توليد رمز مشاركة فريد
    $share_code = substr(md5(uniqid(mt_rand(), true)), 0, 8);

    // إنشاء الرحلة
    $stmt = $conn->prepare("INSERT INTO rides 
        (user_id, start_lat, start_lng, fare_per_km, fare_per_min, base_fare, share_code, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'started', NOW())");
    $stmt->bind_param("iddddds", $user_id, $lat, $lng, $km_price, $minute_price, $base_fare, $share_code);
    if (!$stmt->execute()) { throw new Exception('فشل إنشاء الرحلة: ' . $stmt->error); }

    $ride_id = $conn->insert_id;

    // خصم 1 جنيه من الرصيد
    $new_balance = $balance - 1;
    $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->bind_param("di", $new_balance, $user_id);
    if (!$stmt->execute()) { throw new Exception('فشل خصم الرصيد: ' . $stmt->error); }

    // تسجيل العملية في جدول المعاملات
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
    $desc = "خصم 1 جنيه عند بدء الرحلة (ride_id=$ride_id)";
    $amount = 1.00;
    $stmt->bind_param("ids", $user_id, $amount, $desc);
    if (!$stmt->execute()) { throw new Exception('فشل تسجيل المعاملة: ' . $stmt->error); }

    // نجاح المعاملة
    $conn->commit();
    echo json_encode(['success' => true, 'ride_id' => $ride_id, 'share_code' => $share_code]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => '❌ التراجع عن العملية: ' . $e->getMessage()]);
}
