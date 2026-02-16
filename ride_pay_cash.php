<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'غير مسجل']); exit; }
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) { echo json_encode(['error'=>'CSRF']); exit; }

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$rideId = (int)($input['ride_id'] ?? 0);

if ($rideId <= 0) { echo json_encode(['error'=>'رقم رحلة غير صالح']); exit; }

// التأكد من الرحلة
$stmt = $conn->prepare("SELECT id, user_id, total_fare, status FROM rides WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $rideId, $userId);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();

if (!$ride) { echo json_encode(['error'=>'الرحلة غير موجودة']); exit; }
if ($ride['status'] !== 'ended') { echo json_encode(['error'=>'الرحلة ليست بانتظار الدفع']); exit; }

// هنا سياسة الدفع كاش: لا خصم من رصيد، فقط تأكيد الدفع
$conn->begin_transaction();

try {
    // تحديث حالة الرحلة إلى مدفوعة
    $stmt = $conn->prepare("UPDATE rides SET status='paid', paid_method='cash', paid_at=NOW() WHERE id = ? AND user_id = ? AND status='ended'");
    $stmt->bind_param("ii", $rideId, $userId);
    $stmt->execute();

    // سجل عملية دفع (اختياري في payments)
    // أو سجل في transactions لو تريد تتبع، غالبًا لا حاجة لخصم من الرصيد هنا
    $desc = "دفع كاش لرحلة #$rideId";
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'ride_deduction', ?, ?, NOW())");
    $stmt->bind_param("ids", $userId, $ride['total_fare'], $desc);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success'=>true, 'message'=>'تم تأكيد الدفع كاش بنجاح']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error'=>'فشل تأكيد الدفع: '.$e->getMessage()]);
}
