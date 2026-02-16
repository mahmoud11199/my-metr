<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    header("Location: login.html");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$amount = (float)($_POST['amount'] ?? 0);
$method = trim($_POST['method'] ?? '');
$account_info = trim($_POST['account_info'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';

$errors = [];

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    $errors[] = "طلب غير موثق. أعد تحميل الصفحة وحاول مرة أخرى.";
}

if ($amount <= 0 || $method === '' || $account_info === '') {
    $errors[] = "يرجى إدخال جميع البيانات بشكل صحيح.";
}

if (strtolower($method) !== 'vodafone_cash') {
    $errors[] = "طريقة السحب المسموح بها هي فودافون كاش فقط.";
}

if (!preg_match('/^01[0-25][0-9]{8}$/', $account_info)) {
    $errors[] = "رقم الهاتف غير صالح. يجب أن يكون رقم فودافون كاش صحيح.";
}

$min_withdraw = 50;
$max_withdraw = 1000;

if ($amount < $min_withdraw) {
    $errors[] = "المبلغ المطلوب للسحب يجب أن يكون على الأقل {$min_withdraw} جنيه.";
}
if ($amount > $max_withdraw) {
    $errors[] = "المبلغ المطلوب للسحب لا يمكن أن يتجاوز {$max_withdraw} جنيه.";
}

$check = $conn->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1");
$check->bind_param("i", $user_id);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$balance = (float)($row['balance'] ?? 0);

if ($amount > $balance) {
    $errors[] = "الرصيد الحالي غير كافٍ لتقديم هذا الطلب.";
}

$pending = $conn->prepare("SELECT id FROM withdraw_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
$pending->bind_param("i", $user_id);
$pending->execute();
if ($pending->get_result()->fetch_assoc()) {
    $errors[] = "لديك طلب سحب قيد المراجعة بالفعل.";
}

if ($errors) {
    echo "<h3>أخطاء في الطلب:</h3><ul>";
    foreach ($errors as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul><p><a href='withdraw.php'>رجوع</a></p>";
    exit;
}

$stmt = $conn->prepare("INSERT INTO withdraw_requests (user_id, amount, method, account_info, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("idss", $user_id, $amount, $method, $account_info);
$stmt->execute();

echo "<p>✅ تم إرسال طلب السحب بنجاح وبانتظار موافقة الإدارة.</p>";
echo "<p><a href='withdraws.php'>متابعة حالة طلبات السحب</a></p>";
?>
