<?php
// koneksi.php - Konfigurasi dan Koneksi Database MySQL (PDO)

$host     = 'localhost';
$user     = 'root';
$password = '';
$database = 'db_loote';

try {
    // Membuat koneksi PDO dengan penanganan eror exception dan prepared statements
    $koneksi = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Koneksi database gagal. Pastikan layanan MySQL aktif dan database "' . htmlspecialchars($database) . '" sudah tersedia.');
}

/**
 * Fungsi helper untuk mendapatkan objek koneksi $koneksi
 */
if (!function_exists('getDbConnection')) {
    function getDbConnection()
    {
        global $koneksi;
        return $koneksi;
    }
}
