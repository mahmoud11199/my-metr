<?php
session_start();
require_once 'config.php';
include 'header.php';

// تحقق الدور: مستخدم فقط
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'user') {
  die("🚫 صلاحية غير كافية. هذه الصفحة للمستخدم فقط.");
}
$user_id = (int)$_SESSION['user_id'];

// جلب الأرقام المفعلة
$numbers = $conn->query("SELECT id, number, label FROM vodafone_cash_numbers WHERE is_active = 1 ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="ar">
<head><meta charset="utf-8"><title>شحن الرصيد</title></head>
<body>
  <h2>شحن الرصيد عبر فودافون كاش</h2>

  <?php if ($numbers->num_rows === 0): ?>
    <p>لا توجد أرقام فودافون كاش متاحة حاليًا.</p>
  <?php else: ?>
    <form action="submit_recharge.php" method="post" enctype="multipart/form-data">
        <div style="background:#f9f9f9; border:1px solid #ddd; padding:10px; margin-bottom:15px;">
  <h3>📌 تعليمات استخدام صفحة الشحن</h3>
  <ul>
    <li>الحد الأدنى للشحن هو 10 جنيه.</li>
    <li>الحد الأقصى للشحن هو 5000 جنيه.</li>
    <li>يجب إدخال رقم هاتف صحيح يبدأ بـ 01.</li>
    <li>يجب رفع صورة إثبات التحويل (بحد أقصى 5MB).</li>
    <li>لا يمكن إرسال أكثر من طلب شحن في نفس الوقت، يجب انتظار موافقة الأدمن.</li>
  </ul>
</div>

        
      <label>اختر رقم فودافون كاش:</label>
      <select name="vcash_number_id" required>
        <option value="">— اختر رقمًا —</option>
        <?php while($n = $numbers->fetch_assoc()): ?>
          <option value="<?= $n['id'] ?>">
            <?= htmlspecialchars($n['number']) ?> <?= $n['label'] ? '— ' . htmlspecialchars($n['label']) : '' ?>
          </option>
        <?php endwhile; ?>
      </select>

      <label>رقم هاتفك (المرسل منه التحويل):</label>
      <input type="text" name="user_phone" placeholder="01XXXXXXXXX" required>

      <label>المبلغ (بالجنيه):</label>
      <input type="number" name="amount" min="1" step="1" required>

      <label>الإثبات (صورة فقط: png/jpg/jpeg/webp):</label>
      <input type="file" name="proof" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" required>

      <button type="submit">إرسال طلب الشحن</button>
    </form>
  <?php endif; ?>

  <?php include 'footer.php'; ?>
</body>
</html>
