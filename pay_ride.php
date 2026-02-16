<?php
session_start();
require_once 'config.php';
include 'header.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول كمستخدم
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['error' => 'صلاحية غير كافية.']);
    exit;
}

$paying_user = $_SESSION['user_id'];

// استقبال رمز المشاركة
$data = json_decode(file_get_contents("php://input"), true);
$share_code = $data['share_code'] ?? null;

if (!$share_code) {
    echo json_encode(['error' => '⚠️ رمز المشاركة غير موجود.']);
    exit;
}

// جلب الرحلة
$stmt = $conn->prepare("SELECT * FROM rides WHERE share_code = ? AND status = 'ended'");
$stmt->bind_param("s", $share_code);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();

if (!$ride) {
    echo json_encode(['error' => '⚠️ لا توجد رحلة بهذا الرمز أو لم تنتهِ بعد.']);
    exit;
}

$owner_id = $ride['user_id'];
$total_fare = $ride['total_fare'];

// التحقق من العلاقة بين المستخدمين (جدول user_links)
$link_check = $conn->prepare("SELECT * FROM user_links WHERE user_id = ? AND linked_user_id = ?");
$link_check->bind_param("ii", $paying_user, $owner_id);
$link_check->execute();
if (!$link_check->get_result()->fetch_assoc()) {
    echo json_encode(['error' => '⚠️ لا توجد علاقة مشاركة بين المستخدمين.']);
    exit;
}

// التحقق من الرصيد
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $paying_user);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc()['balance'];

if ($balance < $total_fare) {
    echo json_encode(['error' => '⚠️ لا يوجد رصيد كافي لإتمام الدفع.']);
    exit;
}

// تنفيذ الدفع
$conn->query("UPDATE users SET balance = balance - $total_fare WHERE id = $paying_user");
$conn->query("UPDATE users SET balance = balance + $total_fare WHERE id = $owner_id");

// تحديث حالة الرحلة
$stmt = $conn->prepare("UPDATE rides SET status = 'paid', paid_by = ? WHERE id = ?");
$stmt->bind_param("ii", $paying_user, $ride['id']);
$stmt->execute();

// تسجيل العملية في جدول transactions
$stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'withdraw', ?, ?)");
$desc = "دفع رحلة برمز $share_code";
$stmt->bind_param("ids", $paying_user, $total_fare, $desc);
$stmt->execute();

$stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'add', ?, ?)");
$desc2 = "استلام مبلغ رحلة برمز $share_code";
$stmt->bind_param("ids", $owner_id, $total_fare, $desc2);
$stmt->execute();

echo json_encode(['success' => true, 'message' => '✅ تم الدفع بنجاح.']);


include 'footer.php';



