<?php
session_start();
require_once 'config.php';

include 'header.php'; 

// التحقق من صلاحية الأدمن
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("صلاحية غير كافية.");
}

$admin_id = $_SESSION['user_id'];
$message = "";

// حفظ الإعدادات عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $min_km = $_POST['min_km_price'];
    $max_km = $_POST['max_km_price'];
    $min_min = $_POST['min_minute_price'];
    $max_min = $_POST['max_minute_price'];
    $min_base = $_POST['min_base_fare'];
    $max_base = $_POST['max_base_fare'];

    // حذف الإعدادات القديمة (اختياري)
    $conn->query("DELETE FROM ride_settings");

    // حفظ الإعدادات الجديدة
    $stmt = $conn->prepare("INSERT INTO ride_settings 
        (min_km_price, max_km_price, min_minute_price, max_minute_price, min_base_fare, max_base_fare, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ddddddi", $min_km, $max_km, $min_min, $max_min, $min_base, $max_base, $admin_id);
    $stmt->execute();

    $message = "✅ تم حفظ الإعدادات بنجاح.";
}

// جلب الإعدادات الحالية
$settings = $conn->query("SELECT * FROM ride_settings ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>إعدادات الأداة</title>
</head>
<body>
  <h2>⚙️ إعدادات أداة حساب الأجرة</h2>

  <?php if ($message): ?>
    <p style="color: green;"><?php echo $message; ?></p>
  <?php endif; ?>

  <form method="POST">
    <label>🔢 الحد الأدنى لسعر الكيلو (جنيه):</label>
    <input type="number" step="0.01" name="min_km_price" required value="<?php echo $settings['min_km_price'] ?? ''; ?>"><br><br>

    <label>🔢 الحد الأقصى لسعر الكيلو (جنيه):</label>
    <input type="number" step="0.01" name="max_km_price" required value="<?php echo $settings['max_km_price'] ?? ''; ?>"><br><br>

    <label>⏱️ الحد الأدنى لسعر الدقيقة (جنيه):</label>
    <input type="number" step="0.01" name="min_minute_price" required value="<?php echo $settings['min_minute_price'] ?? ''; ?>"><br><br>

    <label>⏱️ الحد الأقصى لسعر الدقيقة (جنيه):</label>
    <input type="number" step="0.01" name="max_minute_price" required value="<?php echo $settings['max_minute_price'] ?? ''; ?>"><br><br>

    <label>🚗 الحد الأدنى لسعر فتح العداد (جنيه):</label>
    <input type="number" step="0.01" name="min_base_fare" required value="<?php echo $settings['min_base_fare'] ?? ''; ?>"><br><br>

    <label>🚗 الحد الأقصى لسعر فتح العداد (جنيه):</label>
    <input type="number" step="0.01" name="max_base_fare" required value="<?php echo $settings['max_base_fare'] ?? ''; ?>"><br><br>

    <button type="submit">💾 حفظ الإعدادات</button>
  </form>

  <br>
  <a href="admin_dashboard.php">⬅️ العودة للوحة الأدمن</a>
    
    
<?php include 'footer.php'; ?>

</body>
</html>
