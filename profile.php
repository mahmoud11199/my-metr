<?php
session_start();
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// جلب بيانات المستخدم من قاعدة البيانات
$stmt = $conn->prepare("SELECT id, email, balance, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = "ملفي الشخصي";
include 'header.php';
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>ملفي الشخصي</title>
  <style>
    .profile-container {
      max-width: 600px;
      margin: 30px auto;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .profile-container h2 {
      margin-bottom: 20px;
      color: #007bff;
    }
    .profile-container p {
      font-size: 15px;
      margin: 8px 0;
    }
    .profile-container strong {
      color: #333;
    }
    .password-form {
      margin-top: 25px;
      border-top: 1px solid #eee;
      padding-top: 20px;
    }
    .password-form label {
      display: block;
      margin-bottom: 6px;
      font-weight: bold;
    }
    .password-form input {
      width: 100%;
      padding: 8px;
      margin-bottom: 12px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .password-form button {
      background: #007bff;
      color: #fff;
      border: none;
      padding: 10px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
    }
    .password-form button:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>

<div class="profile-container">
  <h2>👤 ملفي الشخصي</h2>
  <p>🆔 <strong>ID:</strong> <?= htmlspecialchars($user['id']) ?></p>
  <p>📧 <strong>البريد الإلكتروني:</strong> <?= htmlspecialchars($user['email']) ?></p>
  <p>💰 <strong>الرصيد:</strong> <?= htmlspecialchars(number_format($user['balance'], 2)) ?> جنيه</p>
  <p>📅 <strong>تاريخ الإنشاء:</strong> <?= htmlspecialchars($user['created_at']) ?></p>

  <!-- فورم تغيير كلمة المرور -->
  <div class="password-form">
    <h3>🔑 تغيير كلمة المرور</h3>
    <form action="update_password.php" method="POST">
      <label>كلمة المرور الحالية:</label>
      <input type="password" name="current_password" required>

      <label>كلمة المرور الجديدة:</label>
      <input type="password" name="new_password" required>

      <label>تأكيد كلمة المرور الجديدة:</label>
      <input type="password" name="confirm_password" required>

      <button type="submit">تحديث كلمة المرور</button>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
