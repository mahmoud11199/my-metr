<?php
// ملف تسجيل مستخدم جديد ومعالجة البيانات

// بدء الجلسة لتخزين بيانات المستخدم لاحقًا
session_start();

// تضمين ملف الاتصال بقاعدة البيانات
require_once 'config.php';

// استقبال البيانات من النموذج
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// التحقق من أن كل الحقول ممتلئة
if (empty($name) || empty($email) || empty($password)) {
    die("يرجى ملء جميع الحقول.");
}

// تشفير كلمة المرور قبل التخزين
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// التحقق من عدم وجود مستخدم بنفس البريد الإلكتروني
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    die("هذا البريد الإلكتروني مستخدم بالفعل.");
}

// إدخال المستخدم الجديد في قاعدة البيانات
$stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $hashed_password);
$stmt->execute();

// حفظ معرف المستخدم في الجلسة
$_SESSION['user_id'] = $stmt->insert_id;

// إعادة توجيه المستخدم إلى لوحة التحكم أو صفحة ترحيب
header("Location: dashboard.php");
exit;
?>
