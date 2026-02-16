<?php
session_start();
require_once 'config.php';

include 'header.php'; 




// التحقق من تسجيل الدخول كمستخدم
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    die("صلاحية غير كافية.");
}

$user_id = $_SESSION['user_id'];
$message = "";

// جلب حدود الأسعار من إعدادات الأدمن
$settings = $conn->query("SELECT * FROM ride_settings ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
if (!$settings) {
    die("⚠️ لم يتم ضبط إعدادات الأداة من قبل الأدمن.");
}

$min_km = $settings['min_km_price'];
$max_km = $settings['max_km_price'];

// عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $km_price = $_POST['km_price'];

    // التحقق من أن السعر داخل الحدود
    if ($km_price < $min_km || $km_price > $max_km) {
        $message = "❌ السعر خارج الحدود المسموح بها.";
    } else {
        // حفظ أو تحديث السعر
        $stmt = $conn->prepare("INSERT INTO user_preferences (user_id, km_price) 
                                VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE km_price = VALUES(km_price), updated_at = NOW()");
        $stmt->bind_param("id", $user_id, $km_price);
        $stmt->execute();
        $message = "✅ تم حفظ السعر بنجاح.";
    }
}

// جلب السعر الحالي للمستخدم
$current = $conn->prepare("SELECT km_price FROM user_preferences WHERE user_id = ?");
$current->bind_param("i", $user_id);
$current->execute();
$current_price = $current->get_result()->fetch_assoc()['km_price'] ?? '';
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>إعدادات الأجرة</title>
</head>
<body>
  <h2>🚗 إعدادات الأجرة الخاصة بك</h2>

  <p>الحد الأدنى لسعر الكيلو: <strong><?php echo $min_km; ?> جنيه</strong></p>
  <p>الحد الأقصى لسعر الكيلو: <strong><?php echo $max_km; ?> جنيه</strong></p>

  <?php if ($message): ?>
    <p style="color: <?php echo strpos($message, '✅') !== false ? 'green' : 'red'; ?>;"><?php echo $message; ?></p>
  <?php endif; ?>

  <form method="POST">
    <label>💰 سعر الكيلو (جنيه):</label>
    <input type="number" step="0.01" name="km_price" required value="<?php echo $current_price; ?>"><br><br>

    <button type="submit">💾 حفظ السعر</button>
  </form>

  <br>
  <a href="dashboard.php">⬅️ العودة للوحة التحكم</a>
    
    
    
<?php include 'footer.php'; ?>

</body>
</html>
