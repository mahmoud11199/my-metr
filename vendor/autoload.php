<?php
// vendor/autoload.php
// Autoloader بسيط لمكتبة firebase/php-jwt عند عدم استخدام Composer.
// هذا الملف يحمل كل ملفات .php داخل vendor/firebase/php-jwt/src/

$base = __DIR__ . '/firebase/php-jwt/src';

if (!is_dir($base)) {
    throw new Exception("المسار $base غير موجود. تأكد من رفع firebase/php-jwt إلى vendor/firebase/php-jwt/src/");
}

$files = scandir($base);
foreach ($files as $f) {
    if ($f === '.' || $f === '..') continue;
    $path = $base . DIRECTORY_SEPARATOR . $f;
    if (is_file($path) && substr($f, -4) === '.php') {
        require_once $path;
    }
}

// (اختياري) لو في مجلدات فرعية داخل src (نادر)، تحملهم أيضاً:
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    if (substr($file->getFilename(), -4) === '.php') {
        require_once $file->getPathname();
    }
}
