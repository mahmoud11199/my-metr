<?php
session_start();
require_once 'config.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'user') {
  die("🚫 صلاحية غير كافية.");
}
$user_id = (int)$_SESSION['user_id'];

$vcash_number_id = (int)($_POST['vcash_number_id'] ?? 0);
$user_phone      = trim($_POST['user_phone'] ?? '');
$amount          = (float)($_POST['amount'] ?? 0);

$errors = [];

// ✅ تحقق أولاً: هل للمستخدم طلب شحن سابق قيد الانتظار؟
$check = $conn->prepare("SELECT id FROM recharge_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
$check->bind_param("i", $user_id);
$check->execute();
$pending = $check->get_result()->fetch_assoc();
if ($pending) {
  $errors[] = "لديك طلب شحن قيد الانتظار يجب أن تتم مراجعته من الأدمن قبل إرسال طلب جديد.";
}

// تحقق رقم فودافون كاش المختار
$stmt = $conn->prepare("SELECT id FROM vodafone_cash_numbers WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $vcash_number_id);
$stmt->execute();
$valid_number = $stmt->get_result()->fetch_assoc();
if (!$valid_number) $errors[] = "رقم فودافون كاش غير متاح.";

// تحقق رقم هاتف المستخدم
if (!preg_match('/^01[0-25][0-9]{8}$/', $user_phone)) {
  $errors[] = "رقم الهاتف غير صالح.";
}

// تحقق المبلغ
if ($amount < 1) $errors[] = "المبلغ يجب أن يكون 1 جنيه على الأقل.";

// تحقق المبلغ
$min_recharge = 10;   // الحد الأدنى للشحن
$max_recharge = 5000; // الحد الأقصى للشحن

if ($amount < $min_recharge) {
  $errors[] = "المبلغ يجب أن يكون على الأقل {$min_recharge} جنيه.";
}
if ($amount > $max_recharge) {
  $errors[] = "المبلغ لا يمكن أن يتجاوز {$max_recharge} جنيه.";
}





// تحقق من الصورة
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
  $errors[] = "يجب رفع صورة الإثبات.";
} else {
  $allowed_mime = ['image/png', 'image/jpeg', 'image/webp'];
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($_FILES['proof']['tmp_name']);
  if (!in_array($mime, $allowed_mime)) {
    $errors[] = "صيغة الصورة غير مسموح بها. (PNG/JPG/JPEG/WEBP)";
  }
  if ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
    $errors[] = "حجم الصورة كبير جدًا (أقصى 5MB).";
  }
}

if ($errors) {
  echo "<h3>أخطاء في الطلب:</h3><ul>";
  foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
  echo "</ul><p><a href='recharge.php'>رجوع</a></p>";
  include 'footer.php';
  exit;
}

// حفظ الصورة
$ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
$filename = 'proof_' . $user_id . '_' . time() . '.' . $ext;
$uploadDir = __DIR__ . '/uploads/recharge_proofs';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
$destPath = $uploadDir . '/' . $filename;
move_uploaded_file($_FILES['proof']['tmp_name'], $destPath);

// صياغة حقل proof ليحمل الهاتف + مسار الصورة
$proof_value = 'phone=' . $user_phone . ';file=uploads/recharge_proofs/' . $filename;

// إدخال الطلب في recharge_requests
$stmt = $conn->prepare("INSERT INTO recharge_requests (user_id, amount, method, proof, status, created_at, vcash_number_id)
                        VALUES (?, ?, 'vodafone_cash', ?, 'pending', NOW(), ?)");
$stmt->bind_param("idsi", $user_id, $amount, $proof_value, $vcash_number_id);
$stmt->execute();

echo "<p>✅ تم إرسال طلب الشحن بنجاح وبانتظار مراجعة الأدمن.</p><p><a href='recharges.php'>عرض طلباتي</a></p>";
include 'footer.php';
