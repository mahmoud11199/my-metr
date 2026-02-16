<?php
session_start();
require_once 'config.php';
$page_title = 'سجل الرحلات';
include 'header.php'; 

// التحقق من تسجيل الدخول كمستخدم
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    header("Location: login.html");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// حساب بداية اليوم والأسبوع والشهر
$todayStart  = date('Y-m-d 00:00:00');
$weekStart   = date('Y-m-d 00:00:00', strtotime('monday this week'));
$monthStart  = date('Y-m-01 00:00:00');

// دالة تجيب الإجماليات لفترة معينة
function getSummary($conn, $user_id, $startDate) {
    $stmt = $conn->prepare("SELECT 
        COALESCE(SUM(distance_km),0) AS total_distance,
        COALESCE(SUM(duration_min),0) AS total_duration,
        COALESCE(SUM(total_fare),0) AS total_fare
        FROM rides 
        WHERE user_id = ? AND created_at >= ?");
    $stmt->bind_param("is", $user_id, $startDate);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$todaySummary  = getSummary($conn, $user_id, $todayStart);
$weekSummary   = getSummary($conn, $user_id, $weekStart);
$monthSummary  = getSummary($conn, $user_id, $monthStart);

// جلب الرحلات التفصيلية
$stmt = $conn->prepare("SELECT * FROM rides WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>سجل الرحلات</title>
  <style>
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background:#f4f6f9; direction: rtl; padding:20px; }
    h2 { color:#007bff; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; background:#fff; }
    th, td { border:1px solid #ddd; padding:8px; text-align:center; }
    th { background:#007bff; color:#fff; }
    tr:nth-child(even){ background:#fafafa; }
  </style>
</head>
<body>
  <h2>📋 سجل الرحلات الخاصة بك</h2>

  <!-- تقرير ملخص الرحلات -->
  <h3>📊 تقرير ملخص</h3>
  <table>
    <tr>
      <th>الفترة</th>
      <th>إجمالي المسافة (كم)</th>
      <th>إجمالي المدة (دقيقة)</th>
      <th>إجمالي التكلفة (جنيه)</th>
    </tr>
    <tr>
      <td>اليوم</td>
      <td><?= number_format($todaySummary['total_distance'],2) ?></td>
      <td><?= $todaySummary['total_duration'] ?></td>
      <td><?= number_format($todaySummary['total_fare'],2) ?></td>
    </tr>
    <tr>
      <td>الأسبوع</td>
      <td><?= number_format($weekSummary['total_distance'],2) ?></td>
      <td><?= $weekSummary['total_duration'] ?></td>
      <td><?= number_format($weekSummary['total_fare'],2) ?></td>
    </tr>
    <tr>
      <td>الشهر</td>
      <td><?= number_format($monthSummary['total_distance'],2) ?></td>
      <td><?= $monthSummary['total_duration'] ?></td>
      <td><?= number_format($monthSummary['total_fare'],2) ?></td>
    </tr>
  </table>

  <!-- جدول الرحلات التفصيلية -->
  <h3>📑 تفاصيل الرحلات</h3>
  <table>
    <tr>
      <th>رمز المشاركة</th>
      <th>المسافة (كم)</th>
      <th>المدة (دقيقة)</th>
      <th>التكلفة (جنيه)</th>
      <th>الحالة</th>
      <th>تاريخ الرحلة</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($row['share_code']); ?></td>
      <td><?= number_format($row['distance_km'], 2); ?></td>
      <td><?= $row['duration_min']; ?></td>
      <td><?= number_format($row['total_fare'], 2); ?></td>
      <td>
        <?php
          if ($row['status'] === 'started') echo '🚀 بدأت';
          elseif ($row['status'] === 'ended') echo '⏹️ انتهت (بانتظار الدفع)';
          elseif ($row['status'] === 'paid') echo '✅ مدفوعة';
          else echo htmlspecialchars($row['status']);
        ?>
      </td>
      <td><?= htmlspecialchars($row['created_at']); ?></td>
    </tr>
    <?php endwhile; ?>
  </table>

  <br>
  <a href="ride.php">⬅️ بدء رحلة جديدة</a>

<?php 
$stmt->close();
$conn->close();
include 'footer.php'; 
?>
</body>
</html>
