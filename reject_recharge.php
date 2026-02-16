<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
  die("🚫 صلاحية غير كافية.");
}
$admin_id = (int)$_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("طلب غير صالح.");

$stmt = $conn->prepare("UPDATE recharge_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
$stmt->bind_param("ii", $admin_id, $id);
$stmt->execute();

header("Location: recharges.php");
