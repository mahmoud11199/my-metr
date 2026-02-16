<?php
// صفحة عرض كل العمليات المالية في النظام - للأدمن

session_start();
require_once 'config.php';

$page_title = "عنوان الصفحة"; // اختياري لتغيير عنوان الصفحة
include 'header.php';



// التحقق من صلاحية الأدمن
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("صلاحية غير كافية.");
}

// جلب العمليات من قاعدة البيانات
$result = $conn->query("SELECT t.user_id, u.name, t.type, t.amount, t.description, t.created_at 
                        FROM transactions t 
                        JOIN users u ON t.user_id = u.id 
                        ORDER BY t.created_at DESC");
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>كل العمليات المالية</title>
</head>
<body>
  <h2>📊 كل العمليات المالية في النظام</h2>

  <table border="1" cellpadding="5">
    <tr>
      <th>المستخدم</th>
      <th>النوع</th>
      <th>المبلغ</th>
      <th>الوصف</th>
      <th>التاريخ</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?php echo htmlspecialchars($row['name']); ?></td>
      <td>
        <?php
          if ($row['type'] === 'add') echo 'شحن';
          elseif ($row['type'] === 'withdraw') echo 'سحب';
          else echo htmlspecialchars($row['type']);
        ?>
      </td>
      <td><?php echo number_format($row['amount'], 2); ?> جنيه</td>
      <td><?php echo htmlspecialchars($row['description']); ?></td>
      <td><?php echo $row['created_at']; ?></td>
    </tr>
    <?php endwhile; ?>
  </table>

  <br>
  <a href="admin_dashboard.php">⬅️ العودة للوحة الأدمن</a>
    

<?php
include 'footer.php';
?>

</body>
</html>
