<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'غير مسجل']); exit; }
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) { echo json_encode(['error'=>'CSRF']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$toId = (int)($input['to_user_id'] ?? 0);
$toEmail = trim($input['to_user_email'] ?? '');

if ($toId <= 0 || $toEmail === '') { echo json_encode(['error'=>'بيانات ناقصة']); exit; }

$stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $toId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { echo json_encode(['error'=>'المستخدم غير موجود']); exit; }
if (strcasecmp($user['email'], $toEmail) !== 0) { echo json_encode(['error'=>'البريد لا يطابق هذا الـID']); exit; }

echo json_encode(['user_id'=>$user['id'], 'username'=>$user['name'], 'email'=>$user['email']]);
