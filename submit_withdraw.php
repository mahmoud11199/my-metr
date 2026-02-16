<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$amount = (float)($_POST['amount'] ?? 0);
$method = trim($_POST['method'] ?? '');
$account_info = trim($_POST['account_info'] ?? '');

$errors = [];

// ✅ التحقق من صحة البيانات الأساسية
if ($amount <= 0 || empty($method) || empty($account_info)) {
    $errors[] = "يرجى إدخال جميع البيانات بشكل صحيح.";
}

// ✅ السماح فقط بطريقة فودافون كاش
if (strtolower($method) !== 'vodafone_cash') {
    $errors[] = "طريقة السحب المسموح بها هي فودافون كاش فقط.";
}

// ✅ التحقق من رقم الهاتف (مصري يبدأ بـ 01)
if (!preg_match('/^01[0-25][0-9]{8}$/', $account_info)) {
    $errors[] = "رقم الهاتف غير صالح. يجب أن يكون رقم فودافون كاش صحيح.";
}

// ✅ التحقق من توفر الرصيد الكافي
$check = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$balance = $row['balance'] ?? 0;

if ($amount > $balance) {
    $errors[] = "الرصيد غير كافٍ.";
}
// حدود السحب
$min_withdraw = 50;    // الحد الأدنى للسحب
$max_withdraw = 1000; // الحد الأقصى للسحب

if ($amount < $min_withdraw) {
    $errors[] = "المبلغ المطلوب للسحب يجب أن يكون على الأقل {$min_withdraw} جنيه.";
}
if ($amount > $max_withdraw) {
    $errors[] = "المبلغ المطلوب للسحب لا يمكن أن يتجاوز {$max_withdraw} جنيه.";
}





if ($errors) {
    echo "<h3>أخطاء في الطلب:</h3><ul>";
    foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
    echo "</ul><p><a href='withdraw.php'>رجوع</a></p>";
    exit;
}

// ✅ خصم الرصيد مباشرة
$new_balance = $balance - $amount;
$update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
$update->bind_param("di", $new_balance, $user_id);
$update->execute();

// ✅ حفظ الطلب في قاعدة البيانات
$stmt = $conn->prepare("INSERT INTO withdraw_requests (user_id, amount, method, account_info, status, created_at) 
                        VALUES (?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("idss", $user_id, $amount, $method, $account_info);
$stmt->execute();

echo "<p>✅ تم إرسال طلب السحب بنجاح باستخدام فودافون كاش، وتم خصم المبلغ من رصيدك.</p>";
echo "<p><a href='transactions.php'>عرض معاملاتي</a></p>";
?>
