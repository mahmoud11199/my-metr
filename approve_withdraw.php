<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("صلاحية غير كافية.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("طريقة الطلب غير مسموحة.");
}

$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die("طلب غير موثق.");
}

$admin_id = (int)$_SESSION['user_id'];
$request_id = (int)($_POST['id'] ?? 0);

if ($request_id <= 0) {
    die("طلب غير صالح.");
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT id, user_id, amount, status FROM withdraw_requests WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if (!$request || $request['status'] !== 'pending') {
        throw new Exception("الطلب غير موجود أو تم مراجعته.");
    }

    $user_id = (int)$request['user_id'];
    $amount = (float)$request['amount'];

    $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
    $stmt->bind_param("did", $amount, $user_id, $amount);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("الرصيد غير كافٍ لتنفيذ السحب وقت الموافقة.");
    }

    $stmt = $conn->prepare("UPDATE withdraw_requests SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $admin_id, $request_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("تعذر تحديث حالة طلب السحب.");
    }

    $desc = "سحب معتمد بواسطة الأدمن (طلب #{$request_id})";
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'withdraw', ?, ?, NOW())");
    $stmt->bind_param("ids", $user_id, $amount, $desc);
    $stmt->execute();

    $conn->commit();
    header("Location: withdraws.php");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die("❌ " . $e->getMessage());
}
?>
