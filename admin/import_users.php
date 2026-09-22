<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$imported = false;
$success_count = 0;
$skip_count = 0;
$duplicate_nims = [];
$upload_error = '';

if (isset($_GET['action']) && $_GET['action'] == 'download_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_peserta.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Nama Lengkap', 'NIM / NIP']);
    fputcsv($output, ['Muhammad Herianto', '12234567']);
    fputcsv($output, ['Gery Al-Rizky', '12234568']);
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    csrf_verify();
    $file = $_FILES['csv_file']['tmp_name'];

    if ($_FILES['csv_file']['error'] === 0) {
        $handle = fopen($file, "r");
        fgetcsv($handle, 1000, ",");

        $default_pass = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (nama, nim, password) VALUES (?, ?, ?)");

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) == 2) {
                $nama = trim($row[0]);
                $nim = trim($row[1]);

                if (strlen($nama) >= 3 && ctype_alnum($nim)) {
                    $check = $pdo->prepare("SELECT id FROM users WHERE nim = ?");
                    $check->execute([$nim]);
                    if (!$check->fetch()) {
                        $stmt->execute([$nama, $nim, $default_pass]);
                        $success_count++;
                    } else {
                        $skip_count++;
                        $duplicate_nims[] = $nim;
                    }
                } else {
                    $skip_count++;
                }
            } else {
                $skip_count++;
            }
        }
        fclose($handle);
        $imported = true;
    } else {
        $upload_error = "Terjadi kesalahan saat mengunggah file.";
    }
}

$open_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

layout_header([
    'title'  => 'Impor Peserta',
    'active' => 'users',
    'role'   => 'admin',
    'tickets' => $open_tickets,
]);
?>


<section class="panel">
    <header>
        <h2>Impor Peserta Massal</h2>
        <p>Daftarkan banyak peserta sekaligus melalui berkas <span class="format-badge">CSV</span>.</p>
    </header>
    <div class="panel-body">
        <a class="btn-sm btn-quiet" href="users.php">&larr; Kembali ke daftar peserta</a>

        <?php if ($imported): ?>
        <div class="import-result" role="status">
            <p class="notice notice-ok no-auto-dismiss"><strong>Impor selesai.</strong> Berikut ringkasan hasil impor:</p>
            <dl class="import-stats">
                <div class="import-stat import-stat-ok">
                    <dd><?= $success_count ?></dd>
                    <dt>Berhasil dibuat</dt>
                </div>
                <div class="import-stat import-stat-warn">
                    <dd><?= $skip_count ?></dd>
                    <dt>Dilewati</dt>
                </div>
                <div class="import-stat import-stat-err">
                    <dd><?= count(array_unique($duplicate_nims)) ?></dd>
                    <dt>NIM duplikat</dt>
                </div>
            </dl>
            <?php if (!empty($duplicate_nims)): ?>
            <p class="import-duplicates"><strong>NIM duplikat yang dilewati:</strong> <?= e(implode(', ', array_unique($duplicate_nims))) ?></p>
            <?php endif; ?>
        </div>
        <?php elseif ($upload_error): ?>
            <p class="notice notice-error no-auto-dismiss"><?= e($upload_error) ?></p>
        <?php endif; ?>

        <div class="panel">
            <header><h3>Cara Impor</h3></header>
            <div class="panel-body">
                <ol class="steps-list">
                    <li>Unduh berkas templat <span class="format-badge">CSV</span> di bawah ini.</li>
                    <li>Buka dengan Microsoft Excel atau Google Sheets.</li>
                    <li>Masukkan nama lengkap dan NIM/NIP. <strong>Jangan ubah baris pertama</strong>.</li>
                    <li>Simpan ulang sebagai <span class="format-badge">CSV</span>.</li>
                    <li>Tarik berkas ke area unggah di bawah.</li>
                    <li>Kata sandi default semua akun hasil impor adalah <strong>123456</strong>.</li>
                </ol>
                <div class="btn-row">
                    <a class="btn-sm" href="import_users.php?action=download_template">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Unduh templat CSV
                    </a>
                </div>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" id="importForm">
            <?= csrf_field() ?>
            <label class="upload-zone" id="uploadZone" for="csv_file">
                <span class="upload-zone-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </span>
                <span class="upload-zone-title">Tarik & letakkan berkas di sini</span>
                <span class="upload-zone-hint">atau klik untuk memilih berkas <span class="format-badge">CSV</span> dari perangkat Anda. Maksimal ukuran mengikuti setelan server.</span>
                <span class="file-preview" id="filePreview">
                    <span class="file-preview-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </span>
                    <span class="file-preview-name" id="fileName"></span>
                    <span class="file-preview-size" id="fileSize"></span>
                    <button type="button" class="file-preview-remove" id="fileRemove" aria-label="Hapus berkas terpilih">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </span>
                <input id="csv_file" class="file-input-hidden" type="file" name="csv_file" accept=".csv" required>
            </label>
            <div class="btn-row" style="margin-top: var(--s4);">
                <button type="submit" class="btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Unggah & impor
                </button>
            </div>
        </form>
    </div>
</section>


<?php layout_footer(['base' => '..', 'scripts' => '
<script>
(function () {
    var zone = document.getElementById("uploadZone");
    var input = document.getElementById("csv_file");
    var preview = document.getElementById("filePreview");
    var nameEl = document.getElementById("fileName");
    var sizeEl = document.getElementById("fileSize");
    var removeBtn = document.getElementById("fileRemove");
    if (!zone || !input) return;

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
        return (bytes / 1048576).toFixed(1) + " MB";
    }

    function showFile(file) {
        nameEl.textContent = file.name;
        sizeEl.textContent = formatSize(file.size);
        preview.classList.add("visible");
    }

    function clearFile() {
        input.value = "";
        preview.classList.remove("visible");
    }

    input.addEventListener("change", function () {
        if (input.files.length > 0) showFile(input.files[0]);
    });

    removeBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearFile();
    });

    ["dragenter", "dragover"].forEach(function (ev) {
        zone.addEventListener(ev, function (e) {
            e.preventDefault();
            zone.classList.add("is-dragover");
        });
    });

    ["dragleave", "drop"].forEach(function (ev) {
        zone.addEventListener(ev, function (e) {
            e.preventDefault();
            zone.classList.remove("is-dragover");
        });
    });

    zone.addEventListener("drop", function (e) {
        if (e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            showFile(input.files[0]);
        }
    });
})();
</script>
']); ?>
