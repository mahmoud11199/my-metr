<?php
// ملف: includes/db.php
// منطق الملف: إنشاء اتصال آمن بقاعدة البيانات باستخدام PDO
// يُستخدم في جميع ملفات النظام لتنفيذ العمليات على قاعدة البيانات

require_once __DIR__ . '/../env.php';

// بدء الجلسة لتتبع المستخدم الحالي
session_start();

// إعدادات الاتصال بقاعدة البيانات عبر متغيرات البيئة
$host = env_value('DB_HOST', '127.0.0.1');
$db   = env_value('DB_NAME', 'my_metr');
$user = env_value('DB_USER', 'root');
$pass = env_value('DB_PASS', '');
$charset = env_value('DB_CHARSET', 'utf8mb4');

// إنشاء سلسلة الاتصال باستخدام PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// خيارات إضافية لتحسين الأمان والأداء
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // تفعيل رسائل الخطأ
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // جلب النتائج كمصفوفة مرتبطة
    PDO::ATTR_EMULATE_PREPARES   => false,                  // تعطيل المحاكاة لتفعيل الحماية من SQL Injection
];

// محاولة الاتصال بقاعدة البيانات
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // في حالة فشل الاتصال، يتم عرض رسالة خطأ واضحة
    exit('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
}
?>
