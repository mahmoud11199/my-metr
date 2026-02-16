<?php
session_start();
require_once 'config.php';

// جلب بيانات المستخدم لو مسجل دخول
$user_email = '';
$user_balance = 0;
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    $stmt = $conn->prepare("SELECT email, balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $user_email = $res['email'];
        $user_balance = $res['balance'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title><?= $page_title ?? 'نظام المحفظة' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background-color: #f4f6f9;
      direction: rtl;
    }
    .navbar {
      background-color: #007bff;
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: #fff;
    }
    .navbar a {
      color: #fff;
      text-decoration: none;
      margin-left: 15px;
      font-weight: bold;
    }
    .navbar a:hover { text-decoration: underline; }
    /* شريط بيانات المستخدم */
    .user-bar {
      background-color: #f1f1f1;
      color: #333;
      padding: 8px 20px;
      font-size: 14px;
      border-bottom: 1px solid #ddd;
      text-align: right;
    }
    .user-bar span {
      margin-left: 20px;
    }
    /* القائمة الجانبية */
    .sidebar {
      height: 100%;
      width: 0;
      position: fixed;
      top: 0;
      right: 0;
      background-color: #111;
      overflow-x: hidden;
      transition: 0.3s;
      padding-top: 60px;
      z-index: 1000;
    }
    .sidebar a {
      padding: 10px 20px;
      text-decoration: none;
      font-size: 16px;
      color: #fff;
      display: block;
      transition: 0.2s;
    }
    .sidebar a:hover {
      background-color: #575757;
    }
    .sidebar .closebtn {
      position: absolute;
      top: 10px;
      left: 20px;
      font-size: 30px;
      color: #fff;
      cursor: pointer;
    }
    .openbtn {
      font-size: 20px;
      cursor: pointer;
      background-color: #007bff;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div> 🟦 عدادك فى جيبك </div>
    <div>
      <!-- روابط رئيسية فقط -->
      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
        <a href="ride.php">الرئيسية</a>
      <?php elseif (!isset($_SESSION['user_id'])): ?>
        <a href="login.html">تسجيل الدخول</a>
        <a href="register.html">إنشاء حساب</a>
      <?php endif; ?>

      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="logout.php">تسجيل الخروج</a>
      <?php endif; ?>

      <!-- زر فتح القائمة الجانبية -->
      <button class="openbtn" onclick="openSidebar()">☰ القائمة</button>
    </div>
  </div>

  <!-- شريط بيانات المستخدم -->
  <?php if ($user_id): ?>
    <div class="user-bar">
      <span>🆔 ID: <?= htmlspecialchars($user_id) ?></span>
      <span>📧 البريد: <?= htmlspecialchars($user_email) ?></span>
      <span>💰 الرصيد: <?= htmlspecialchars(number_format($user_balance, 2)) ?> جنيه</span>
      <span><a href="profile.php">📄 ملفي الشخصي</a></span>
    </div>
  <?php endif; ?>

  <!-- القائمة الجانبية -->
  <div id="mySidebar" class="sidebar">
    <span class="closebtn" onclick="closeSidebar()">×</span>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
      <a href="admin_dashboard.php">لوحة التحكم</a>
      <a href="users.php">المستخدمين</a>
      <a href="admin_vcash_numbers.php">أرقام فودافون كاش</a>
      <a href="admin_rides_map.php">أماكن المستخدمين</a>
    <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
      <a href="recharge.php">شحن الرصيد</a>
      <a href="withdraw.php">سحب الرصيد</a>
      <a href="ride.php">بدء رحلة</a>
      <a href="user_ride_settings.php">ضبط إعدادات سعر الكيلو</a>
      <a href="download.php">حمل التطبيق</a>
    <?php endif; ?>

    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'user'])): ?>
      <a href="recharges.php">طلبات الشحن</a>
      <a href="link_user.php">ربط مستخدم</a>
      <a href="transactions.php">معاملاتي</a>
      <a href="ride_history.php">رحلاتي</a>
    <?php endif; ?>
  </div>

  <script>
    function openSidebar() {
      document.getElementById("mySidebar").style.width = "250px";
    }
    function closeSidebar() {
      document.getElementById("mySidebar").style.width = "0";
    }
  </script>
