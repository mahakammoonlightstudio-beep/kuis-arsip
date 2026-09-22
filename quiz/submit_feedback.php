<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $message = trim($_POST['message'] ?? '');

    $user_id = is_logged_in() ? $_SESSION['user_id'] : null;
    $name = is_logged_in() ? $_SESSION['nama'] : 'Tamu (Mode Publik)';

    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Pesan tidak boleh kosong.']);
        exit;
    }
    if (strlen($message) > 400) {
        echo json_encode(['status' => 'error', 'message' => 'Pesan maksimal 400 karakter.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO feedback (user_id, name, message) VALUES (?, ?, ?)");
    if ($stmt->execute([$user_id, $name, $message])) {
        echo json_encode(['status' => 'success', 'message' => 'Terima kasih atas masukan Anda!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim masukan.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
exit;
