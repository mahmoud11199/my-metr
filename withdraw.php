<?php
session_start();
require_once 'config.php';

$page_title = "طلب سحب رصيد";
include 'header.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>طلب سحب رصيد</title>
</head>
<body>
  <h2>📤 طلب سحب رصيد</h2>

  <form action="submit_withdraw.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div style="background:#f9f9f9; border:1px solid #ddd; padding:10px; margin-bottom:15px;">
  <h3>📌 تعليمات استخدام صفحة السحب</h3>
  <ul>
    <li>طريقة السحب المتاحة هي <strong>فودافون كاش فقط</strong>.</li>
    <li>الحد الأدنى للسحب هو 50 جنيه.</li>
    <li>الحد الأقصى للسحب هو 1000 جنيه.</li>
    <li>يجب إدخال رقم هاتف فودافون كاش صحيح يبدأ بـ 01.</li>
    <li>لن يتم خصم المبلغ إلا بعد موافقة الإدارة على الطلب.</li>
    <li>الطلب سيظل في حالة "قيد المراجعة" حتى موافقة الإدارة.</li>
  </ul>
</div>

      
    <label>المبلغ المطلوب:</label>
    <input type="number" name="amount" min="1" required><br><br>

    <!-- طريقة السحب ثابتة: فودافون كاش فقط -->
    <input type="hidden" name="method" value="vodafone_cash">
    <p>طريقة السحب: <strong>فودافون كاش فقط</strong></p>

    <label>رقم الهاتف (فودافون كاش):</label>
    <input type="tel" name="account_info" pattern="01[0-25][0-9]{8}" placeholder="01XXXXXXXXX" required><br><br>

    <button type="submit">إرسال الطلب</button>
  </form>

  <br>
  <a href="ride.php">⬅️ العودة لبدء رحله جديده </a>

<?php
include 'footer.php';
?>
</body>
</html>
