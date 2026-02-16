<?php
// ملف تسجيل الدخول والتحقق من بيانات المستخدم

session_start();
require_once 'config.php';

// استقبال البيانات من النموذج
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// التحقق من أن الحقول غير فارغة
if (empty($email) || empty($password)) {
    die("يرجى إدخال البريد الإلكتروني وكلمة المرور.");
}

// جلب بيانات المستخدم من قاعدة البيانات
$stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("البريد الإلكتروني غير مسجل.");
}

$user = $result->fetch_assoc();

// التحقق من كلمة المرور
if (!password_verify($password, $user['password'])) {
    die("كلمة المرور غير صحيحة.");
}

// حفظ بيانات الجلسة
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

// التوجيه حسب نوع المستخدم
if ($user['role'] === 'admin') {
    header("Location: admin_dashboard.php");
} else {
    header("Location: ride.php");
}
exit;
?>
