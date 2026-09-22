<?php
require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_id'])) {
    csrf_verify();
    $user_id = (int)$_POST['reset_user_id'];

    $stmt = $pdo->prepare("DELETE FROM results WHERE user_id = ?");
    $stmt->execute([$user_id]);

    audit_log('reset', 'results', $user_id, 'Semua hasil jawaban direset');
    header('Location: index.php?status=reset_success');
    exit;
}

header('Location: index.php');
exit;
