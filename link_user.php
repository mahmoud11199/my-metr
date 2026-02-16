<?php
session_start();
require_once 'config.php';
 include 'header.php';


// التحقق من تسجيل الدخول كمستخدم
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    die("صلاحية غير كافية.");
}

$user_id = $_SESSION['user_id'];
$message = "";

// عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $linked_email = $_POST['linked_email'];

    // التحقق من وجود المستخدم الآخر
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $linked_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $linked_user = $result->fetch_assoc();

    if (!$linked_user) {
        $message = "❌ لا يوجد مستخدم بهذا البريد.";
    } elseif ($linked_user['id'] == $user_id) {
        $message = "❌ لا يمكنك ربط نفسك بنفسك.";
    } else {
        // حفظ العلاقة في جدول user_links
        $stmt = $conn->prepare("INSERT IGNORE INTO user_links (user_id, linked_user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $linked_user['id']);
        $stmt->execute();
        $message = "✅ تم ربط المستخدم بنجاح.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
  <meta charset="UTF-8">
  <title>ربط مستخدم</title>
</head>
<body>
  <h2>🔗 ربط مستخدم للدفع المشترك</h2>

  <?php if ($message): ?>
    <p style="color: <?php echo strpos($message, '✅') !== false ? 'green' : 'red'; ?>;"><?php echo $message; ?></p>
  <?php endif; ?>

  <form method="POST">
    <label>📧 البريد الإلكتروني للمستخدم الآخر:</label>
    <input type="email" name="linked_email" required><br><br>

    <button type="submit">🔗 ربط المستخدم</button>
  </form>

  <br>
  <a href="dashboard.php">⬅️ العودة للوحة التحكم</a>
    
  
<?php include 'footer.php'; ?>

    
</body>
</html>
