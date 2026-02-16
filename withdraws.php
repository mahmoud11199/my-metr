<?php
session_start();
require_once 'config.php';

$page_title = "عنوان الصفحة"; // اختياري لتغيير عنوان الصفحة
include 'header.php';



if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("صلاحية غير كافية.");
}

$result = $conn->query("SELECT w.id, u.name, w.amount, w.method, w.account_info, w.status, w.created_at 
                        FROM withdraw_requests w 
                        JOIN users u ON w.user_id = u.id 
                        ORDER BY w.created_at DESC");
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>طلبات السحب</title>
</head>
<body>
  <h2>📤 طلبات السحب</h2>

  <table border="1" cellpadding="5">
    <tr>
      <th>المستخدم</th>
      <th>المبلغ</th>
      <th>الطريقة</th>
      <th>بيانات الحساب</th>
      <th>الحالة</th>
      <th>تاريخ الطلب</th>
      <th>إجراءات</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?php echo htmlspecialchars($row['name']); ?></td>
      <td><?php echo number_format($row['amount'], 2); ?></td>
      <td><?php echo $row['method']; ?></td>
      <td><?php echo nl2br(htmlspecialchars($row['account_info'])); ?></td>
      <td><?php echo $row['status']; ?></td>
      <td><?php echo $row['created_at']; ?></td>
      <td>
        <?php if ($row['status'] === 'pending'): ?>
          <form method="post" action="approve_withdraw.php" style="display:inline;">
            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <button type="submit">✅ موافقة</button>
          </form>
          <form method="post" action="reject_withdraw.php" style="display:inline;">
            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <button type="submit">❌ رفض</button>
          </form>
        <?php else: ?>
          تم المراجعة
        <?php endif; ?>
      </td>
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
