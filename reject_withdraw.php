<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("صلاحية غير كافية.");
}

$admin_id = $_SESSION['user_id'];
$request_id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT id FROM withdraw_requests WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("الطلب غير موجود أو تم مراجعته.");
}

$conn->query("UPDATE withdraw_requests 
              SET status = 'rejected', reviewed_at = NOW(), reviewed_by = $admin_id 
              WHERE id = $request_id");

header("Location: withdraws.php");
exit;
?>
