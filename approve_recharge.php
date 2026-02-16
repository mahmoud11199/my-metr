<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  die("🚫 صلاحية غير كافية.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die("طريقة الطلب غير مسموحة.");
}

$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
  http_response_code(403);
  die("طلب غير موثق.");
}

$admin_id = (int)$_SESSION['user_id'];
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) die("طلب غير صالح.");

$conn->begin_transaction();

try {
  $stmt = $conn->prepare("SELECT id, user_id, amount, vcash_number_id, status FROM recharge_requests WHERE id = ? FOR UPDATE");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $req = $stmt->get_result()->fetch_assoc();

  if (!$req) {
    throw new Exception("الطلب غير موجود.");
  }
  if ($req['status'] !== 'pending') {
    throw new Exception("لا يمكن معالجة هذا الطلب.");
  }

  $user_id = (int)$req['user_id'];
  $amount = (float)$req['amount'];
  $vcash_number_id = (int)$req['vcash_number_id'];

  $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
  $stmt->bind_param("di", $amount, $user_id);
  $stmt->execute();

  $desc = "شحن رصيد عبر فودافون كاش (رقم ID: {$vcash_number_id})، طلب #{$id}";
  $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'recharge', ?, ?, NOW())");
  $stmt->bind_param("ids", $user_id, $amount, $desc);
  $stmt->execute();

  $stmt = $conn->prepare("UPDATE recharge_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
  $stmt->bind_param("ii", $admin_id, $id);
  $stmt->execute();

  $conn->commit();
  header("Location: recharges.php");
  exit;
} catch (Exception $e) {
  $conn->rollback();
  die("❌ " . $e->getMessage());
}
?>
