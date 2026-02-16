<?php
// لوحة تحكم الأدمن - عرض إحصائيات وإدارة النظام


session_start();
require_once 'config.php';


$page_title = "عنوان الصفحة"; // اختياري لتغيير عنوان الصفحة
include 'header.php';





// التحقق من صلاحية الأدمن
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("صلاحية غير كافية.");
}

// جلب عدد المستخدمين
$users_count = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'user'")->fetch_assoc()['total'];

// جلب إجمالي الرصيد
$total_balance = $conn->query("SELECT SUM(balance) AS total FROM users WHERE role = 'user'")->fetch_assoc()['total'];
$total_balance = $total_balance ?: 0;

// جلب عدد طلبات الشحن المعلقة
$pending_recharges = $conn->query("SELECT COUNT(*) AS total FROM recharge_requests WHERE status = 'pending'")->fetch_assoc()['total'];

// جلب عدد طلبات السحب المعلقة
$pending_withdraws = $conn->query("SELECT COUNT(*) AS total FROM withdraw_requests WHERE status = 'pending'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    
  <meta charset="UTF-8">
  <title>لوحة تحكم الأدمن</title>
    

    
</head>
<body>
  <h2>🧑‍💼 لوحة تحكم الأدمن</h2>

  <p><strong>عدد المستخدمين:</strong> <?php echo $users_count; ?></p>
  <p><strong>إجمالي الرصيد:</strong> <?php echo number_format($total_balance, 2); ?> جنيه</p>
  <p><strong>طلبات شحن معلقة:</strong> <?php echo $pending_recharges; ?></p>
  <p><strong>طلبات سحب معلقة:</strong> <?php echo $pending_withdraws; ?></p>

  <hr>

  <!-- روابط الإدارة -->
  <a href="users.php">👥 إدارة المستخدمين</a><br>
    <a href="ride_settings.php"> اعدادات الاداه </a><br>
  <a href="recharges.php">📥 مراجعة طلبات الشحن</a><br>
  <a href="withdraws.php">📤 مراجعة طلبات السحب</a><br>
  <a href="all_transactions.php"> سجل للمعاملات الماليه </a><br>
  <a href="logout.php">🚪 تسجيل الخروج</a>
    
<?php
include 'footer.php';
?>

</body>
</html>
