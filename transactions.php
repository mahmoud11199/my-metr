<?php
session_start();
require_once 'config.php';

date_default_timezone_set('Africa/Cairo'); // ✔ عرض التوقيت المصري

$page_title = "العمليات المالية";
include 'header.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// تحديد الفترة المختارة
$period = $_GET['period'] ?? 'all';

switch($period){
    case 'today':
        $startDate = date('Y-m-d 00:00:00');
        break;
    case 'week':
        $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
        break;
    case 'month':
        $startDate = date('Y-m-01 00:00:00');
        break;
    default:
        $startDate = null;
}

// فلتر نوع العملية
$typeFilter = $_GET['type'] ?? 'all';

// بناء الاستعلام
$query = "SELECT type, amount, description, created_at 
          FROM transactions 
          WHERE user_id = ?";

$params = [$user_id];
$types  = "i";

if ($startDate) {
    $query .= " AND created_at >= ?";
    $params[] = $startDate;
    $types   .= "s";
}

if ($typeFilter !== 'all') {
    $query .= " AND type = ?";
    $params[] = $typeFilter;
    $types   .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// حساب الإحصائيات
$statsQuery = "SELECT 
    SUM(CASE WHEN type IN ('recharge','transfer_in') THEN amount ELSE 0 END) AS total_in,
    SUM(CASE WHEN type IN ('withdraw','transfer_out','debit','ride_deduction') THEN amount ELSE 0 END) AS total_out
    FROM transactions WHERE user_id=?";

$st = $conn->prepare($statsQuery);
$st->bind_param("i", $user_id);
$st->execute();
$stats = $st->get_result()->fetch_assoc();

$totalIn  = $stats['total_in']  ?? 0;
$totalOut = $stats['total_out'] ?? 0;
$net      = $totalIn - $totalOut;
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>📊 العمليات المالية</title>
  <style>
    body { font-family: 'Segoe UI', Tahoma; background:#f4f6f9; direction: rtl; padding:20px; }

    .stats-box {
      display:flex; gap:20px; margin-bottom:20px;
    }
    .stat {
      flex:1; background:white; padding:15px; border-radius:10px;
      text-align:center; border:1px solid #ddd;
    }
    .stat h3 { margin:0; font-size:18px; }
    .stat p { margin:0; margin-top:5px; font-size:20px; font-weight:bold; }

    table { border-collapse: collapse; width: 100%; background:#fff; border-radius:10px; overflow:hidden; }
    th, td { padding:12px; border-bottom:1px solid #eee; text-align:center; }
    th { background:#007bff; color:white; }
    tr:nth-child(even) { background:#f9f9f9; }

    .filters a, .filters select {
      text-decoration:none; background:#007bff; color:#fff; padding:7px 12px;
      border-radius:6px; margin-left:6px; border:none;
    }
    .filters a.active { background:#0056b3; }

    .type-recharge { color: green; font-weight:bold; }
    .type-transfer_in { color: teal; font-weight:bold; }
    .type-transfer_out { color: darkorange; font-weight:bold; }
    .type-withdraw { color: red; font-weight:bold; }
    .type-debit { color: gray; font-weight:bold; }
    .type-ride_deduction { color: orange; font-weight:bold; }

  </style>
</head>
<body>

<h2>📊 العمليات المالية</h2>

<!-- صندوق الإحصائيات -->
<div class="stats-box">
  <div class="stat">
    <h3>إجمالي الداخل</h3>
    <p style="color:green;"><?= number_format($totalIn,2) ?> جنيه</p>
  </div>

  <div class="stat">
    <h3>إجمالي الخارج</h3>
    <p style="color:red;"><?= number_format($totalOut,2) ?> جنيه</p>
  </div>

  <div class="stat">
    <h3>الصافي</h3>
    <p style="color:blue;"><?= number_format($net,2) ?> جنيه</p>
  </div>
</div>

<!-- الفلاتر -->
<div class="filters">
  <a href="?period=all&type=<?= $typeFilter ?>"   class="<?= $period==='all'?'active':''; ?>">الكل</a>
  <a href="?period=today&type=<?= $typeFilter ?>" class="<?= $period==='today'?'active':''; ?>">اليوم</a>
  <a href="?period=week&type=<?= $typeFilter ?>"  class="<?= $period==='week'?'active':''; ?>">الأسبوع</a>
  <a href="?period=month&type=<?= $typeFilter ?>" class="<?= $period==='month'?'active':''; ?>">الشهر</a>

  <form method="get" style="display:inline;">
    <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
    <select name="type" onchange="this.form.submit()">
      <option value="all">كل الأنواع</option>
      <option value="recharge" <?= $typeFilter==='recharge'?'selected':''; ?>>⬆️ شحن</option>
      <option value="withdraw" <?= $typeFilter==='withdraw'?'selected':''; ?>>⬇️ سحب</option>
      <option value="transfer_in" <?= $typeFilter==='transfer_in'?'selected':''; ?>>🔁 استلام رحلة</option>
      <option value="transfer_out" <?= $typeFilter==='transfer_out'?'selected':''; ?>>🔁 إرسال رحلة</option>
      <option value="debit" <?= $typeFilter==='debit'?'selected':''; ?>>💸 تشغيل عداد</option>
      <option value="ride_deduction" <?= $typeFilter==='ride_deduction'?'selected':''; ?>>🚕 كاش</option>
    </select>
  </form>
</div>

<!-- جدول العمليات -->
<table>
  <tr>
    <th>النوع</th>
    <th>المبلغ</th>
    <th>الوصف</th>
    <th>التاريخ</th>
  </tr>

  <?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td class="type-<?= $row['type'] ?>">
        <?php
          switch ($row['type']) {
            case 'withdraw':       echo '⬇️ سحب'; break;
            case 'recharge':       echo '⬆️ شحن'; break;
            case 'debit':          echo '💸 تشغيل العداد'; break;
            case 'transfer_out':   echo '🔁 إرسال رحلة'; break;
            case 'transfer_in':    echo '🔁 استلام رحلة'; break;
            case 'ride_deduction': echo '🚕 كاش'; break;
          }
        ?>
      </td>

      <td><b><?= number_format($row['amount'], 2) ?></b> جنيه</td>

      <td><?= htmlspecialchars($row['description']) ?></td>

      <td><?= date("Y-m-d h:i A", strtotime($row['created_at'])) ?></td>
    </tr>
    <?php endwhile; ?>

  <?php else: ?>
    <tr><td colspan="4">لا توجد بيانات</td></tr>
  <?php endif; ?>

</table>

<br>
<a href="ride.php">⬅️ العودة لبدء رحلة جديدة</a>

<?php
$stmt->close();
$conn->close();
include 'footer.php';
?>
</body>
</html>
