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

$stmt = $conn->prepare("UPDATE withdraw_requests SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND status = 'pending'");
$stmt->bind_param("ii", $admin_id, $request_id);
$stmt->execute();

header("Location: withdraws.php");
exit;
?>
