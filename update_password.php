<?php
session_start();
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// جلب البيانات من الفورم
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

// تحقق من إدخال جميع الحقول
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $errors[] = "يجب إدخال جميع الحقول.";
}

// تحقق من تطابق كلمة المرور الجديدة مع التأكيد
if ($new_password !== $confirm_password) {
    $errors[] = "كلمة المرور الجديدة لا تتطابق مع التأكيد.";
}

// تحقق من قوة كلمة المرور الجديدة (مثال: 6 أحرف على الأقل)
if (strlen($new_password) < 6) {
    $errors[] = "كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل.";
}

if ($errors) {
    echo "<h3>أخطاء:</h3><ul>";
    foreach ($errors as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul><p><a href='profile.php'>⬅️ رجوع</a></p>";
    exit;
}

// جلب كلمة المرور الحالية من قاعدة البيانات
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    die("المستخدم غير موجود.");
}

$db_password = $res['password'];

// تحقق من كلمة المرور الحالية
if (!password_verify($current_password, $db_password)) {
    die("كلمة المرور الحالية غير صحيحة. <p><a href='profile.php'>⬅️ رجوع</a></p>");
}

// تحديث كلمة المرور الجديدة
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update->bind_param("si", $new_hash, $user_id);
$update->execute();
$update->close();

echo "<p>✅ تم تحديث كلمة المرور بنجاح.</p>";
echo "<p><a href='profile.php'>⬅️ العودة إلى ملفي الشخصي</a></p>";
?>
