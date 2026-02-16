<?php
// ملف الاتصال بقاعدة البيانات - يجب تضمينه في كل ملف يحتاج إلى اتصال بقاعدة البيانات

require_once __DIR__ . '/env.php';

// إعدادات الاتصال عبر متغيرات البيئة
$host = env_value('DB_HOST', '127.0.0.1');
$dbname = env_value('DB_NAME', 'my_metr');
$username = env_value('DB_USER', 'root');
$password = env_value('DB_PASS', '');

// إنشاء الاتصال باستخدام mysqli
$conn = new mysqli($host, $username, $password, $dbname);

// التحقق من نجاح الاتصال
if ($conn->connect_error) {
    // في حالة وجود خطأ في الاتصال، يتم إيقاف التنفيذ وعرض رسالة الخطأ
    die('فشل الاتصال بقاعدة البيانات: ' . $conn->connect_error);
}

// تعيين الترميز إلى UTF-8 لدعم اللغة العربية
$conn->set_charset('utf8mb4');
?>
