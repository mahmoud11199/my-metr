<?php
// ملف: includes/db.php
// منطق الملف: إنشاء اتصال آمن بقاعدة البيانات باستخدام PDO
// يُستخدم في جميع ملفات النظام لتنفيذ العمليات على قاعدة البيانات

// بدء الجلسة لتتبع المستخدم الحالي
session_start();

// إعدادات الاتصال بقاعدة البيانات
$host = 'sql301.iceiy.com'; // عنوان السيرفر (عادة localhost)
$db   = 'icei_40193589_mahmoud'; // اسم قاعدة البيانات التي أنشأتها
$user = 'icei_40193589'; // اسم المستخدم في الاستضافة
$pass = '01206451010mM'; // كلمة المرور الخاصة بقاعدة البيانات
$charset = 'utf8mb4'; // ترميز يدعم اللغة العربية والرموز

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
