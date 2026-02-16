<?php
// صفحة عرض وإدارة المستخدمين للأدمن

session_start();
require_once 'config.php';


$page_title = "عنوان الصفحة"; // اختياري لتغيير عنوان الصفحة
include 'header.php';


// التحقق من صلاحية الأدمن
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("صلاحية غير كافية.");
}

// جلب كل المستخدمين من قاعدة البيانات
$result = $conn->query("SELECT id, name, email, balance, role, created_at FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>إدارة المستخدمين</title>
</head>
<body>
  <h2>👥 إدارة المستخدمين</h2>
    
    <div class="table-wrapper">

  <table border="1" cellpadding="5">
    <tr>
      <th>الاسم</th>
      <th>البريد الإلكتروني</th>
      <th>الرصيد</th>
      <th>الدور</th>
      <th>تاريخ التسجيل</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?php echo htmlspecialchars($row['name']); ?></td>
      <td><?php echo htmlspecialchars($row['email']); ?></td>
      <td><?php echo number_format($row['balance'], 2); ?> جنيه</td>
      <td><?php echo $row['role']; ?></td>
      <td><?php echo $row['created_at']; ?></td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>
  <br>
  <a href="admin_dashboard.php">⬅️ العودة للوحة الأدمن</a>
    

<?php
include 'footer.php';
?>

</body>
</html>
