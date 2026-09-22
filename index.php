<?php
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    // Kalau udah login, admin ke admin, peserta ke quiz
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: quiz/index.php');
    }
} else {
    // Kalau belum login (guest), tetap masuk ke dashboard quiz tapi mode guest
    header('Location: quiz/index.php');
}
exit;