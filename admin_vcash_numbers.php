<?php
session_start();
require_once 'config.php';
include 'header.php';

// تحقق الدور: أدمن فقط
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
  die("🚫 صلاحية غير كافية. هذه الصفحة للمشرف فقط.");
}

// إضافة رقم جديد
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
  $number = trim($_POST['number'] ?? '');
  $label  = trim($_POST['label'] ?? '');

  // تحقق رقم مصري يبدأ بـ 01 مع كود الشبكة
  if (!preg_match('/^01[0-25][0-9]{8}$/', $number)) {
    $error = "رقم فودافون كاش غير صالح.";
  } else {
    $stmt = $conn->prepare("INSERT INTO vodafone_cash_numbers (number, label) VALUES (?, ?)");
    $stmt->bind_param("ss", $number, $label);
    $stmt->execute();
    $success = "تم إضافة الرقم بنجاح.";
  }
}

// تفعيل/تعطيل
if (isset($_GET['toggle'])) {
  $id = (int)$_GET['toggle'];
  $conn->query("UPDATE vodafone_cash_numbers SET is_active = 1 - is_active WHERE id = $id");
}

// حذف
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $conn->query("DELETE FROM vodafone_cash_numbers WHERE id = $id");
}

$numbers = $conn->query("SELECT * FROM vodafone_cash_numbers ORDER BY is_active DESC, created_at DESC");
?>
<!DOCTYPE html>
<html lang="ar">
<head><meta charset="utf-8"><title>إدارة أرقام فودافون كاش</title></head>
<body>
  <h2>إدارة أرقام فودافون كاش</h2>
  <?php if ($error) echo "<p style='color:red'>$error</p>"; ?>
  <?php if ($success) echo "<p style='color:green'>$success</p>"; ?>

  <form method="post">
    <label>رقم فودافون كاش:</label>
    <input type="text" name="number" placeholder="01XXXXXXXXX" required>
    <label>وصف (اختياري):</label>
    <input type="text" name="label" placeholder="رئيسي / فرعي">
    <button type="submit" name="add">إضافة رقم</button>
  </form>

  <hr>
  <table border="1" cellpadding="6">
    <tr><th>ID</th><th>الرقم</th><th>الوصف</th><th>مفعل</th><th>تحكم</th></tr>
    <?php while($row = $numbers->fetch_assoc()): ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['number']) ?></td>
        <td><?= htmlspecialchars($row['label']) ?></td>
        <td><?= $row['is_active'] ? 'نعم' : 'لا' ?></td>
        <td>
          <a href="?toggle=<?= $row['id'] ?>">تفعيل/تعطيل</a> |
          <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('حذف الرقم؟')">حذف</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>

  <?php include 'footer.php'; ?>
</body>
</html>
