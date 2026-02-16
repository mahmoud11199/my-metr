<?php
// ملف تسجيل الخروج

session_start();

// حذف كل بيانات الجلسة
session_unset();
session_destroy();

// إعادة التوجيه إلى الصفحة الرئيسية
header("Location: index.php");
exit;
?>
