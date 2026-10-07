<?php
require_once __DIR__ . '/config.php';

/**
 * Membaca semua catatan dari folder NOTES_DIR.
 * Mengembalikan array asosiatif [id => data].
 */
function getNotes(): array
{
    $notes = [];
    $files = glob(NOTES_DIR . '/*.json');

    if ($files === false) {
        return $notes;
    }

    // Urutkan berdasarkan waktu terbaru
    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    foreach ($files as $file) {
        $id = basename($file, '.json');
        $data = readNote($id);
        if ($data !== null) {
            $notes[$id] = $data;
        }
    }

    return $notes;
}

/**
 * Membaca satu catatan berdasarkan ID.
 */
function readNote(string $id): ?array
{
    $file = NOTES_DIR . '/' . sanitizeId($id) . '.json';
    if (!file_exists($file)) {
        return null;
    }

    $content = file_get_contents($file);
    if ($content === false) {
        return null;
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return null;
    }

    return $data;
}

/**
 * Menyimpan catatan baru ke file JSON.
 */
function saveNote(string $title, string $content, array $fileInfo = []): string
{
    $id = generateId();
    $file = NOTES_DIR . '/' . $id . '.json';

    $data = [
        'id' => $id,
        'title' => trim($title),
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'file' => $fileInfo,
    ];

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    return $id;
}

/**
 * Menghapus catatan beserta file lampirannya (jika ada).
 */
function deleteNote(string $id): bool
{
    $note = readNote($id);
    if ($note === null) {
        return false;
    }

    // Hapus file lampiran jika ada
    if (!empty($note['file']['stored_name'])) {
        $attachment = UPLOADS_DIR . '/' . $note['file']['stored_name'];
        if (file_exists($attachment)) {
            @unlink($attachment);
        }
    }

    $file = NOTES_DIR . '/' . sanitizeId($id) . '.json';
    if (file_exists($file)) {
        return @unlink($file);
    }

    return false;
}

/**
 * Memproses upload file dan mengembalikan informasi file.
 */
function handleUpload(array $uploadedFile): array
{
    $result = [
        'stored_name' => '',
        'original_name' => '',
        'mime_type' => '',
        'size' => 0,
        'error' => '',
    ];

    // Jika tidak ada file yang diunggah atau error upload (kecuali UPLOAD_ERR_NO_FILE)
    if (!isset($uploadedFile['tmp_name']) || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
        return $result;
    }

    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Terjadi kesalahan saat mengunggah file.';
        return $result;
    }

    // Validasi ukuran file
    if ($uploadedFile['size'] > MAX_UPLOAD_SIZE) {
        $result['error'] = 'Ukuran file melebihi batas maksimal ' . formatBytes(MAX_UPLOAD_SIZE) . '.';
        return $result;
    }

    // Validasi tipe file (izinkan sebagian besar tipe, kecuali executable berbahaya)
    $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'cmd', 'com', 'scr'];
    $originalName = basename($uploadedFile['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (in_array($extension, $blockedExtensions, true)) {
        $result['error'] = 'Tipe file tidak diizinkan demi keamanan.';
        return $result;
    }

    // Simpan file dengan nama acak agar tidak bentrok dan tidak dapat diakses langsung
    $storedName = generateId() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
    $destination = UPLOADS_DIR . '/' . $storedName;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $destination)) {
        $result['error'] = 'Gagal menyimpan file ke server.';
        return $result;
    }

    $result['stored_name'] = $storedName;
    $result['original_name'] = $originalName;
    $result['mime_type'] = mime_content_type($destination) ?: $uploadedFile['type'];
    $result['size'] = $uploadedFile['size'];

    return $result;
}

/**
 * Mengunduh file lampiran dengan aman.
 */
function downloadFile(string $id): void
{
    $note = readNote($id);
    if ($note === null || empty($note['file']['stored_name'])) {
        header('HTTP/1.1 404 Not Found');
        exit('File tidak ditemukan.');
    }

    $storedName = $note['file']['stored_name'];
    $originalName = $note['file']['original_name'];
    $filePath = UPLOADS_DIR . '/' . $storedName;

    if (!file_exists($filePath)) {
        header('HTTP/1.1 404 Not Found');
        exit('File tidak ditemukan di server.');
    }

    $mimeType = $note['file']['mime_type'] ?: 'application/octet-stream';
    $size = filesize($filePath);

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-cache, must-revalidate');

    readfile($filePath);
    exit;
}

/**
 * Sanitasi ID catatan agar aman digunakan sebagai nama file.
 */
function sanitizeId(string $id): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
}

/**
 * Membuat ID unik untuk catatan/file.
 */
function generateId(): string
{
    return bin2hex(random_bytes(8));
}

/**
 * Format ukuran byte menjadi KB/MB/dst.
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;

    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }

    return round($bytes, $precision) . ' ' . $units[$unitIndex];
}

/**
 * Escape output untuk mencegah XSS.
 */
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
