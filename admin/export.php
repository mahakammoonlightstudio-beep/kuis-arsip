<?php
require_once __DIR__ . '/../config/database.php';

// Proteksi admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Ambil data peserta beserta info room & survey
$filter_room = $_GET['room_id'] ?? 'all';

if ($filter_room !== 'all' && $filter_room !== '') {
    $stmt = $pdo->prepare("
        SELECT u.nama, u.nim, r.room_name, s.title as survey_title, res.score, res.total_questions, res.created_at
        FROM results res
        JOIN users u ON res.user_id = u.id
        LEFT JOIN rooms r ON res.room_id = r.id
        LEFT JOIN surveys s ON res.survey_id = s.id
        WHERE res.room_id = ?
        ORDER BY res.score DESC, res.created_at ASC
    ");
    $stmt->execute([$filter_room]);
} else {
    $stmt = $pdo->query("
        SELECT u.nama, u.nim, r.room_name, s.title as survey_title, res.score, res.total_questions, res.created_at
        FROM results res
        JOIN users u ON res.user_id = u.id
        LEFT JOIN rooms r ON res.room_id = r.id
        LEFT JOIN surveys s ON res.survey_id = s.id
        ORDER BY res.score DESC, res.created_at ASC
    ");
}
$results = $stmt->fetchAll();

// Hitung statistik untuk footer
$total_nilai = 0;
$total_soal = 0;
$total_lulus = 0;
foreach ($results as $r) {
    if ($r['score'] !== null && $r['total_questions'] > 0) {
        $total_nilai += round(($r['score'] / $r['total_questions']) * 100);
        $total_soal += $r['total_questions'];
        if (round(($r['score'] / $r['total_questions']) * 100) >= 70) $total_lulus++;
    }
}
$rata_rata = count($results) > 0 ? round($total_nilai / count($results)) : 0;

audit_log('export', 'results', '', 'Export nilai' . ($filter_room !== 'all' && $filter_room !== '' ? " room_id=$filter_room" : ''));

// Header untuk Excel XML (Native Spreadsheet)
$filter_label = ($filter_room !== 'all' && $filter_room !== '') ? '_ruangan_' . (int)$filter_room : '';
$filename = 'rekap_nilai_kearsipan' . $filter_label . '_' . date('Ymd') . '.xls';
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Mulai bikin struktur XML Excel
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

// Style Header (Hijau tua, putih, bold, center)
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

// Style Sub-Header (Baris kedua judul — warna emas)
echo '<Style ss:ID="SubHeader">'
    . '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="10" ss:Name="Arial"/>'
    . '<Interior ss:Color="#A67C15" ss:Pattern="Solid"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#7A5A0D"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#7A5A0D"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#7A5A0D"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#7A5A0D"/>'
    . '</Borders></Style>' . "\n";

// Style Judul Laporan (Baris paling atas — merge cell)
echo '<Style ss:ID="Title">'
    . '<Font ss:Bold="1" ss:Color="#0F4D33" ss:Size="14" ss:Name="Arial"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
    . '</Style>' . "\n";

// Style Info Perusahaan (Baris kedua judul)
echo '<Style ss:ID="Info">'
    . '<Font ss:Color="#454A43" ss:Size="9" ss:Name="Arial"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'
    . '</Style>' . "\n";

// Style Data Normal (Sel reguler)
echo '<Style ss:ID="Data">'
    . '<Font ss:Size="10" ss:Name="Arial"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '</Borders><Alignment ss:Vertical="Center" ss:WrapText="1"/></Style>' . "\n";

// Style Zebra (Baris genap)
echo '<Style ss:ID="Zebra">'
    . '<Font ss:Size="10" ss:Name="Arial"/>'
    . '<Interior ss:Color="#EEF6F1" ss:Pattern="Solid"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '</Borders><Alignment ss:Vertical="Center" ss:WrapText="1"/></Style>' . "\n";

// Style NIM / Teks yang tidak boleh jadi angka
echo '<Style ss:ID="NIM">'
    . '<Font ss:Size="10" ss:Name="Arial"/>'
    . '<NumberFormat ss:Format="@"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '</Borders><Alignment ss:Vertical="Center"/></Style>' . "\n";

// Style Center (Angka rata tengah)
echo '<Style ss:ID="Center">'
    . '<Font ss:Size="10" ss:Name="Arial"/>'
    . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D5CE"/>'
    . '</Borders></Style>' . "\n";

// Style Lulus (Background hijau muda)
echo '<Style ss:ID="Pass">'
    . '<Font ss:Bold="1" ss:Color="#175C3D" ss:Size="10" ss:Name="Arial"/>'
    . '<Interior ss:Color="#DCF1E4" ss:Pattern="Solid"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9ACCB3"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9ACCB3"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9ACCB3"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9ACCB3"/>'
    . '</Borders><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>' . "\n";

// Style Gagal (Background merah muda)
echo '<Style ss:ID="Fail">'
    . '<Font ss:Bold="1" ss:Color="#86271E" ss:Size="10" ss:Name="Arial"/>'
    . '<Interior ss:Color="#F7E6E4" ss:Pattern="Solid"/>'
    . '<Borders>'
    . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D8A9A2"/>'
    . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D8A9A2"/>'
    . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D8A9A2"/>'
    . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D8A9A2"/>'
    . '</Borders><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>' . "\n";

// Style Footer statistik
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
echo '<Worksheet ss:Name="Rekap Nilai Kearsipan">' . "\n";
echo '<Table>' . "\n";

// Set Lebar Kolom
echo '<Column ss:Width="45"/>' . "\n";   // No
echo '<Column ss:Width="200"/>' . "\n";  // Nama Peserta
echo '<Column ss:Width="110"/>' . "\n";  // NIM/NIP
echo '<Column ss:Width="60"/>' . "\n";   // Tipe
echo '<Column ss:Width="220"/>' . "\n";  // Kegiatan
echo '<Column ss:Width="55"/>' . "\n";   // Skor
echo '<Column ss:Width="65"/>' . "\n";   // Total Soal
echo '<Column ss:Width="80"/>' . "\n";   // Persentase
echo '<Column ss:Width="120"/>' . "\n";  // Tanggal Submit

// Baris 1: Judul Utama (Merge semua kolom)
echo '<Row ss:Height="35">' . "\n";
echo '<Cell ss:StyleID="Title" ss:MergeAcross="8"><Data ss:Type="String">REKAPITULASI NILAI QUESTIONNAIRE KEARSIPAN</Data></Cell>' . "\n";
echo '</Row>' . "\n";

// Baris 2: Info Instansi
echo '<Row ss:Height="22">' . "\n";
echo '<Cell ss:StyleID="Info" ss:MergeAcross="8"><Data ss:Type="String">Dinas Kearsipan dan Perpustakaan Kabupaten Kutai Kartanegara — ' . date('d F Y') . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";

// Baris 3: Spacer
echo '<Row ss:Height="10"><Cell ss:StyleID="Data"></Cell></Row>' . "\n";

// Baris 4: Header Tabel (2 baris karena ada sub-judul Tipe & Kegiatan)
echo '<Row ss:Height="30">' . "\n";
$headers = ['No', 'Nama Peserta', 'NIM / NIP', 'Tipe', 'Kegiatan', 'Skor', 'Total Soal', 'Persentase', 'Tanggal Submit'];
foreach ($headers as $h) {
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>' . "\n";
}
echo '</Row>' . "\n";

// Data Rows
$no = 1;
foreach ($results as $r) {
    $pct = $r['total_questions'] > 0 ? round(($r['score'] / $r['total_questions']) * 100) : 0;
    $tipe = $r['room_name'] ? 'Room' : 'Individu';
    $kegiatan = $r['room_name'] ? $r['room_name'] : $r['survey_title'];
    $tgl = date('d M Y, H:i', strtotime($r['created_at']));

    $row_style = ($no % 2 == 0) ? 'Zebra' : 'Data';
    $pct_style = $pct >= 70 ? 'Pass' : 'Fail';

    echo '<Row>' . "\n";
    echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . $no++ . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="' . $row_style . '"><Data ss:Type="String">' . htmlspecialchars($r['nama']) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="NIM"><Data ss:Type="String">' . htmlspecialchars($r['nim']) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="Center"><Data ss:Type="String">' . htmlspecialchars($tipe) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="' . $row_style . '"><Data ss:Type="String">' . htmlspecialchars($kegiatan) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . htmlspecialchars($r['score']) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . htmlspecialchars($r['total_questions']) . '</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="' . $pct_style . '"><Data ss:Type="String">' . $pct . '%</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="' . $row_style . '"><Data ss:Type="String">' . htmlspecialchars($tgl) . '</Data></Cell>' . "\n";
    echo '</Row>' . "\n";
}

// Baris Total (Footer statistik)
$final_no = $no - 1;
echo '<Row ss:Height="28">' . "\n";
echo '<Cell ss:StyleID="Footer" ss:MergeAcross="4"><Data ss:Type="String">TOTAL PESERTA: ' . $final_no . ' ORANG  |  RATA-RATA: ' . $rata_rata . '%  |  LULUS: ' . $total_lulus . ' ORANG</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="Footer"><Data ss:Type="Number">' . $final_no . '</Data></Cell>' . "\n";
echo '<Cell ss:StyleID="Footer"></Cell><Cell ss:StyleID="Footer"></Cell><Cell ss:StyleID="Footer"></Cell>' . "\n";
echo '</Row>' . "\n";

echo '</Table>' . "\n";
echo '</Worksheet>' . "\n";
echo '</Workbook>' . "\n";
exit;
