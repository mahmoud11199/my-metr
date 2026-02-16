<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'غير مسجل']); exit; }
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) { echo json_encode(['error'=>'CSRF']); exit; }

$fromUser = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$rideId = (int)($input['ride_id'] ?? 0);
$toUserId = (int)($input['to_user_id'] ?? 0);
$toEmail = trim($input['to_user_email'] ?? '');

if ($rideId <= 0 || $toUserId <= 0 || $toEmail === '') { echo json_encode(['error'=>'بيانات ناقصة']); exit; }

// التحقق من الرحلة
$stmt = $conn->prepare("SELECT id, user_id, total_fare, status FROM rides WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $rideId, $fromUser);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();

if (!$ride) { echo json_encode(['error'=>'الرحلة غير موجودة']); exit; }
if ($ride['status'] !== 'ended') { echo json_encode(['error'=>'الرحلة ليست بانتظار الدفع']); exit; }

// التحقق من المستقبل (ID يطابق البريد)
$stmt = $conn->prepare("SELECT id, name, email, balance FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $toUserId);
$stmt->execute();
$toUser = $stmt->get_result()->fetch_assoc();

if (!$toUser) { echo json_encode(['error'=>'المستخدم المستقبل غير موجود']); exit; }
if (strcasecmp($toUser['email'], $toEmail) !== 0) { echo json_encode(['error'=>'البريد لا يطابق هذا الـID']); exit; }

// احضار رصيد المرسل
$stmt = $conn->prepare("SELECT id, name, email, balance FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $fromUser);
$stmt->execute();
$fromUserRow = $stmt->get_result()->fetch_assoc();

if (!$fromUserRow) { echo json_encode(['error'=>'المستخدم الحالي غير موجود']); exit; }

$total = (float)$ride['total_fare'];
if ($total <= 0) { echo json_encode(['error'=>'قيمة الرحلة غير صالحة']); exit; }
if ((float)$fromUserRow['balance'] < $total) { echo json_encode(['error'=>'الرصيد غير كافي للتحويل']); exit; }

$conn->begin_transaction();

try {
    // خصم من المرسل
    $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
    $stmt->bind_param("dii", $total, $fromUser, $total);
    $stmt->execute();
    if ($stmt->affected_rows === 0) { throw new Exception('فشل خصم الرصيد'); }

    // إضافة للمستقبل
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->bind_param("di", $total, $toUserId);
    $stmt->execute();

    // سجل عمليات
    $descOut = "تحويل تكلفة رحلة #$rideId إلى المستخدم {$toUser['name']} (ID {$toUser['id']})";
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'transfer_out', ?, ?, NOW())");
    $stmt->bind_param("ids", $fromUser, $total, $descOut);
    $stmt->execute();

    $descIn = "استلام تكلفة رحلة #$rideId من المستخدم {$fromUserRow['name']} (ID {$fromUserRow['id']})";
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'transfer_in', ?, ?, NOW())");
    $stmt->bind_param("ids", $toUserId, $total, $descIn);
    $stmt->execute();

    // تحديث حالة الرحلة إلى مدفوعة بطريقة التحويل
    $stmt = $conn->prepare("UPDATE rides SET status='paid', paid_method='transfer', paid_at=NOW(), pay_to_user_id=? WHERE id = ? AND user_id = ? AND status='ended'");
    $stmt->bind_param("iii", $toUserId, $rideId, $fromUser);
    $stmt->execute();

    $conn->commit();
    echo json_encode([
        'success'=>true, 
        'message'=>"تم التحويل إلى {$toUser['name']} بنجاح",
        'to_user'=> ['id'=>$toUser['id'], 'name'=>$toUser['name'], 'email'=>$toUser['email']]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error'=>'فشل عملية التحويل: '.$e->getMessage()]);
}
