<?php
session_start();
require_once 'config.php';

$page_title = "عنوان الصفحة"; // اختياري لتغيير عنوان الصفحة
include 'header.php';




if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("صلاحية غير كافية.");
}

$admin_id = $_SESSION['user_id'];
$request_id = $_GET['id'] ?? 0;

// جلب الطلب
$stmt = $conn->prepare("SELECT user_id, amount FROM withdraw_requests WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("الطلب غير موجود أو تم مراجعته.");
}

$request = $result->fetch_assoc();
$user_id = $request['user_id'];
$amount = $request['amount'];

// خصم الرصيد
$conn->query("UPDATE users SET balance = balance - $amount WHERE id = $user_id");

// تحديث الطلب
$conn->query("UPDATE withdraw_requests 
              SET status = 'approved', reviewed_at = NOW(), reviewed_by = $admin_id 
              WHERE id = $request_id");

// تسجيل العملية
$conn->query("INSERT INTO transactions (user_id, type, amount, description, created_at) 
              VALUES ($user_id, 'withdraw', $amount, 'سحب عن طريق الأدمن', NOW())");

header("Location: withdraws.php");
exit;

include 'footer.php';

?>
