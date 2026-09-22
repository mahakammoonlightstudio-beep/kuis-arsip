<?php
require_once __DIR__ . '/../config/database.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Ambil data peserta
$stmt = $pdo->query("
    SELECT u.nama, u.nim, u.email, u.created_at, COUNT(r.id) as total_done
    FROM users u
    LEFT JOIN results r ON u.id = r.user_id
    WHERE u.role = 'peserta'
    GROUP BY u.id
    ORDER BY u.nama ASC
");
$users = $stmt->fetchAll();

// Header untuk Excel XML
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"data_peserta_" . date('Ymd') . ".xls\"");
header("Pragma: no-cache");
header("Expires: 0");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
    . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
    . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
    . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

// ============================================================
// DEFINISI STYLE — Palet resmi Diarpus Kukar (#0F4D33 hijau tua, #A67C15 emas)
// ============================================================
echo '<Styles>' . "\n";

// Header utama (Hijau tua)
echo '<Style ss:ID="Header">'
    . '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11" ss:Name="Arial"/>'
    . '<Interior ss:Color="#0F4D33" ss:Pattern="Solid"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#082D1E"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#082D1E"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#082D1E"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#082D1E"/>'
    . '</Borders></Style>' . "\n";

// Judul laporan
echo '<Style ss:ID="Title">'
    . '<Font ss:Bold="1" ss:Color="#0F4D33" ss:Size="14" ss:Name="Arial"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
    . '</Style>' . "\n";

// Info baris
echo '<Style ss:ID="Info">'
    . '<Font ss:Color="#454A43" ss:Size="9" ss:Name="Arial"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
    . '</Style>' . "\n";

// Data reguler
echo '<Style ss:ID="Data">'
    . '<Font ss:Size="10" ss:Name="Arial"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '</Borders><Alignment ss:Vertical="Center" ss:WrapText="1"/></Style>' . "\n";

// Zebra striping
echo '<Style ss:ID="Zebra">'
    . '<Font ss:Size="10" ss:Name="Arial"/>'
    . '<Interior ss:Color="#EEF6F1" ss:Pattern="Solid"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '</Borders><Alignment ss:Vertical="Center" ss:WrapText="1"/></Style>' . "\n";

// NIM format text
echo '<Style ss:ID="NIM">'
    . '<Font ss:Size="10" ss:Name="Arial"/>'
    . '<NumberFormat ss:Format="@"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '</Borders><Alignment ss:Vertical="Center"/></Style>' . "\n";

// Center alignment
echo '<Style ss:ID="Center">'
    . '<Font ss:Size="10" ss:Name="Arial"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '</Borders></Style>' . "\n";

// Footer statistik
echo '<Style ss:ID="Footer">'
    . '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="10" ss:Name="Arial"/>'
    . '<Interior ss:Color="#0F4D33" ss:Pattern="Solid"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#082D1E"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#082D1E"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#082D1E"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#082D1E"/>'
    . '</Borders></Style>' . "\n";

echo '</Styles>' . "\n";

// ============================================================
// WORKSHEET
// ============================================================
echo '<Worksheet ss:Name="Data Peserta">' . "\n";
echo '<Table>' . "\n";

// Lebar kolom
echo '<Column ss:Width="45"/>' . "\n";     // No
echo '<Column ss:Width="220"/>' . "\n";    // Nama
echo '<Column ss:Width="120"/>' . "\n";    // NIM/NIP
echo '<Column ss:Width="180"/>' . "\n";    // Email
echo '<Column ss:Width="100"/>' . "\n";    // Ujian Selesai
echo '<Column ss:Width="130"/>' . "\n";    // Tanggal Daftar

// Baris 1: Judul
echo '<Row ss:Height="35">' . "\n";
echo '<Cell ss:StyleID="Title" ss:MergeAcross="5"><Data ss:Type="String">DATA PESERTA QUESTIONNAIRE KEARSIPAN</Data></Cell>' . "\n";
echo '</Row>' . "\n";

// Baris 2: Info
echo '<Row ss:Height="22">' . "\n";
echo '<Cell ss:StyleID="Info" ss:MergeAcross="5"><Data ss:Type="String">Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara — ' . date('d F Y') . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";

// Baris 3: Spacer
echo '<Row ss:Height="10"><Cell ss:StyleID="Data"></Cell></Row>' . "\n";

// Baris 4: Header
echo '<Row ss:Height="30">' . "\n";
$headers = ['No', 'Nama Peserta', 'NIM / NIP', 'Email', 'Ujian Selesai', 'Tanggal Daftar'];
foreach ($headers as $h) {
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>' . "\n";
}
echo '</Row>' . "\n";

// Data rows
$no = 1;
foreach ($users as $u) {
    $tgl = date('d M Y', strtotime($u['created_at']));
    $row_style = ($no % 2 == 0) ? 'Zebra' : 'Data';

    echo '<Row>' . "\n";
    echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . $no++ . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="' . $row_style . '"><Data ss:Type="String">' . htmlspecialchars($u['nama']) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="NIM"><Data ss:Type="String">' . htmlspecialchars($u['nim']) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="' . $row_style . '"><Data ss:Type="String">' . (empty($u['email']) ? '-' : htmlspecialchars($u['email'])) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . (int)$u['total_done'] . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="' . $row_style . '"><Data ss:Type="String">' . htmlspecialchars($tgl) . '</Data></Cell>' . "\n";
    echo '</Row>' . "\n";
}

// Footer total
$total_users = count($users);
echo '<Row ss:Height="28">' . "\n";
echo '<Cell ss:StyleID="Footer" ss:MergeAcross="5"><Data ss:Type="String">TOTAL PESERTA TERDAFTAR: ' . $total_users . ' ORANG</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '</Table>' . "\n";
echo '</Worksheet>' . "\n";
echo '</Workbook>' . "\n";
exit;
