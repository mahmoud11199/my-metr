<?php
session_start();
require_once 'config.php';

// تحقق الدور: أدمن فقط
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
  die("🚫 صلاحية غير كافية.");
}
$admin_id = (int)$_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("طلب غير صالح.");

$conn->begin_transaction();

$req = $conn->query("SELECT * FROM recharge_requests WHERE id = $id FOR UPDATE")->fetch_assoc();
if (!$req) { $conn->rollback(); die("الطلب غير موجود."); }
if ($req['status'] !== 'pending') { $conn->rollback(); die("لا يمكن معالجة هذا الطلب."); }

$user_id         = (int)$req['user_id'];
$amount          = (float)$req['amount'];
$vcash_number_id = (int)$req['vcash_number_id'];

// تحديث الرصيد
$conn->query("UPDATE users SET balance = balance + $amount WHERE id = $user_id");

// تسجيل المعاملة
$desc = "شحن رصيد عبر فودافون كاش (رقم ID: $vcash_number_id)، طلب #$id";
$stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'recharge', ?, ?, NOW())");
$stmt->bind_param("ids", $user_id, $amount, $desc);
$stmt->execute();

// تحديث حالة الطلب + المراجع
$conn->query("UPDATE recharge_requests SET status = 'approved', reviewed_by = $admin_id, reviewed_at = NOW() WHERE id = $id");

$conn->commit();
header("Location: recharges.php");
