<?php
session_start();
require_once 'config.php';
include 'header.php';

if (!isset($_SESSION['user_id'])) {
  die("🚫 صلاحية غير كافية.");
}
$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'] ?? null;

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// بناء الاستعلام حسب الدور
if ($role === 'admin') {
  $sql = "SELECT r.*, v.number AS vcash_number
          FROM recharge_requests r
          LEFT JOIN vodafone_cash_numbers v ON v.id = r.vcash_number_id
          ORDER BY r.created_at DESC";
} elseif ($role === 'user') {
  $sql = "SELECT r.*, v.number AS vcash_number
          FROM recharge_requests r
          LEFT JOIN vodafone_cash_numbers v ON v.id = r.vcash_number_id
          WHERE r.user_id = $user_id
          ORDER BY r.created_at DESC";
} else {
  die("🚫 صلاحية غير كافية.");
}

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ar">
<head><meta charset="utf-8"><title>طلبات الشحن</title></head>
<body>
  <h2>طلبات الشحن</h2>
  <table border="1" cellpadding="6">
    <tr>
      <th>ID</th>
      <th>المستخدم</th>
      <th>المبلغ</th>
      <th>الطريقة</th>
      <th>رقم فودافون كاش المختار</th>
      <th>الإثبات</th>
      <th>الحالة</th>
      <th>أُعيدت المراجعة بواسطة</th>
      <th>تاريخ المراجعة</th>
      <th>تحكم</th>
    </tr>
    <?php while($r = $res->fetch_assoc()): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= $r['user_id'] ?></td>
        <td><?= number_format((float)$r['amount'], 2) ?></td>
        <td><?= htmlspecialchars($r['method']) ?></td>
        <td><?= htmlspecialchars($r['vcash_number'] ?? '—') ?></td>
        <td>
          <?php
            // عرض جزء من proof + رابط للصورة إن وجدت
            $proof = $r['proof'] ?? '';
            echo htmlspecialchars($proof);
            if (strpos($proof, 'file=') !== false) {
              $parts = explode(';', $proof);
              foreach ($parts as $p) {
                if (strpos($p, 'file=') === 0) {
                  $path = substr($p, 5);
                  echo ' — <a href="'.htmlspecialchars($path).'" target="_blank">عرض الصورة</a>';
                }
              }
            }
          ?>
        </td>
        <td><?= htmlspecialchars($r['status']) ?></td>
        <td><?= $r['reviewed_by'] ? (int)$r['reviewed_by'] : '—' ?></td>
        <td><?= $r['reviewed_at'] ? htmlspecialchars($r['reviewed_at']) : '—' ?></td>
        <td>
          <?php if ($role === 'admin' && $r['status'] === 'pending'): ?>
            <form method="post" action="approve_recharge.php" style="display:inline;">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <button type="submit">موافقة</button>
            </form>
            <form method="post" action="reject_recharge.php" style="display:inline;">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <button type="submit">رفض</button>
            </form>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
  <?php include 'footer.php'; ?>
</body>
</html>
