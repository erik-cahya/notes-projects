<?php
require_once __DIR__ . '/config.php';

if (!defined('INDEX_FILE')) {
    define('INDEX_FILE', NOTES_DIR . '/index.json');
}
if (!defined('NOTES_PER_PAGE')) {
    define('NOTES_PER_PAGE', 12);
}

/**
 * Membaca seluruh metadata dari file index.
 */
function getNoteIndex(): array
{
    if (!file_exists(INDEX_FILE)) {
        return [];
    }

    $content = file_get_contents(INDEX_FILE);
    if ($content === false || $content === '') {
        return [];
    }

    $index = json_decode($content, true);
    return is_array($index) ? $index : [];
}

/**
 * Menyimpan metadata ke file index.
 */
function saveNoteIndex(array $index): void
{
    file_put_contents(INDEX_FILE, json_encode($index), LOCK_EX);
}

/**
 * Membangun ulang index dari file JSON catatan.
 * Berguna saat sinkronisasi atau pertama kali.
 */
function rebuildNoteIndex(): array
{
    $index = [];
    $files = glob(NOTES_DIR . '/*.json');
    if ($files === false) {
        return $index;
    }

    foreach ($files as $file) {
        if (basename($file) === 'index.json') {
            continue;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['id'])) {
            continue;
        }

        $index[$data['id']] = [
            'id' => $data['id'],
            'title' => $data['title'] ?? '',
        'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $data['updated_at'] ?? $data['created_at'] ?? date('Y-m-d H:i:s'),
        'has_attachment' => !empty($data['file']['stored_name']),
        'file_size' => $data['file']['size'] ?? 0,
        'excerpt' => makeExcerpt($data['content'] ?? '', 120),
        ];
    }

    // Urutkan berdasarkan created_at terbaru
    uasort($index, function ($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

    saveNoteIndex($index);
    return $index;
}

/**
 * Memastikan index tersedia dan valid.
 */
function ensureNoteIndex(): array
{
    $index = getNoteIndex();
    if (empty($index)) {
        $index = rebuildNoteIndex();
    }
    return $index;
}

/**
 * Mendapatkan daftar catatan dengan paginasi.
 * Mengembalikan [items, total, totalPages, page, perPage].
 */
function getNotesPaginated(int $page = 1, int $perPage = NOTES_PER_PAGE): array
{
    $index = ensureNoteIndex();
    $total = count($index);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $items = array_slice($index, $offset, $perPage, true);

    return [
        'items' => $items,
        'total' => $total,
        'totalPages' => $totalPages,
        'page' => $page,
        'perPage' => $perPage,
    ];
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
 * Menyimpan catatan baru ke file JSON dan memperbarui index.
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

    file_put_contents($file, json_encode($data), LOCK_EX);

    // Update index ringan
    $index = ensureNoteIndex();
    $index[$id] = [
        'id' => $id,
        'title' => $data['title'],
        'created_at' => $data['created_at'],
        'updated_at' => $data['updated_at'],
        'has_attachment' => !empty($fileInfo['stored_name']),
        'file_size' => $fileInfo['size'] ?? 0,
        'excerpt' => makeExcerpt($data['content'], 120),
    ];
    uasort($index, function ($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });
    saveNoteIndex($index);

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
    $deleted = false;
    if (file_exists($file)) {
        $deleted = @unlink($file);
    }

    // Hapus dari index
    $index = getNoteIndex();
    if (isset($index[$id])) {
        unset($index[$id]);
        saveNoteIndex($index);
    }

    return $deleted;
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

    if (!isset($uploadedFile['tmp_name']) || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
        return $result;
    }

    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Terjadi kesalahan saat mengunggah file.';
        return $result;
    }

    if ($uploadedFile['size'] > MAX_UPLOAD_SIZE) {
        $result['error'] = 'Ukuran file melebihi batas maksimal ' . formatBytes(MAX_UPLOAD_SIZE) . '.';
        return $result;
    }

    $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'cmd', 'com', 'scr'];
    $originalName = basename($uploadedFile['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (in_array($extension, $blockedExtensions, true)) {
        $result['error'] = 'Tipe file tidak diizinkan demi keamanan.';
        return $result;
    }

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
 * Menggunakan chunked read agar hemat memori untuk file besar.
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

    // Bersihkan buffer output
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: private, max-age=0');
    header('Expires: -1');
    header('Pragma: no-cache');

    // Chunked streaming
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Gagal membaca file.');
    }

    $chunkSize = 1024 * 1024; // 1 MB per chunk
    while (!feof($handle)) {
        echo fread($handle, $chunkSize);
        flush();
    }
    fclose($handle);
    exit;
}

/**
 * Membuat cuplikan teks untuk ditampilkan di daftar.
 */
function makeExcerpt(?string $text, int $maxLength = 120): string
{
    if ($text === null) {
        return '';
    }

    $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
    $text = trim($text);

    if (mb_strlen($text, 'UTF-8') <= $maxLength) {
        return $text;
    }

    return mb_substr($text, 0, $maxLength, 'UTF-8') . '…';
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
