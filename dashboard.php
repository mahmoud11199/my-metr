
<?php
// ملف لوحة المستخدم بعد تسجيل الدخول

session_start();
require_once 'config.php';

$page_title = "عنوان الصفحة"; // اختياري لتغيير عنوان الصفحة
include 'header.php';

// التحقق من وجود جلسة مستخدم
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

// جلب بيانات المستخدم من قاعدة البيانات
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("المستخدم غير موجود.");
}

$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>لوحة المستخدم</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body>
  <h2>مرحبًا، <?php echo htmlspecialchars($user['name']); ?> 👋</h2>

  <p><strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
  <p><strong>الرصيد الحالي:</strong> <?php echo number_format($user['balance'], 2); ?> جنيه</p>

  <hr>

  <!-- روابط للوظائف القادمة -->
  <a href="recharge.php">🔄 طلب شحن رصيد</a><br>
  <a href="withdraw.php"> طلب سحب رصيد </a><br>
  <a href="transactions.php"> سجل المعاملات المالية </a><br>
  <a href="user_ride_settings.php"> ضبط سعر الكيلو متر  </a><br>
    <a href="ride.php"> ابدا رحله جديده </a><br>
  <a href="logout.php">🚪 تسجيل الخروج</a>

  <hr>

<?php
include 'footer.php';
?>
</body>
</html>
