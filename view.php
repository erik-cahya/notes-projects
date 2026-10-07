<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

// Handle download request
if (isset($_GET['download']) && $_GET['download'] === '1') {
    $id = $_GET['id'] ?? '';
    downloadFile($id);
}

$id = $_GET['id'] ?? '';
$note = readNote($id);

if ($note === null) {
    header('HTTP/1.1 404 Not Found');
    echo 'Catatan tidak ditemukan.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($note['title']) ?> | Note Repo</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.min.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a href="<?= BASE_URL ?>/dashboard.php" class="brand">📝 Note Repo</a>
            <nav class="nav">
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline btn-sm">← Kembali</a>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline btn-sm">Keluar</a>
            </nav>
        </div>
    </header>

    <main class="container main">
        <article class="note-detail">
            <h1 class="page-title"><?= e($note['title']) ?></h1>
            <p class="note-meta detail-meta">
                Dibuat: <?= e(date('d M Y H:i', strtotime($note['created_at']))) ?>
                <?php if ($note['updated_at'] !== $note['created_at']): ?>
                    &nbsp;• Diperbarui: <?= e(date('d M Y H:i', strtotime($note['updated_at']))) ?>
                <?php endif; ?>
            </p>

            <div class="note-content">
                <pre><code><?= e($note['content']) ?></code></pre>
            </div>

            <?php if (!empty($note['file']['stored_name'])): ?>
                <div class="attachment-box">
                    <h3>📎 Lampiran</h3>
                    <p>
                        <strong>Nama:</strong> <?= e($note['file']['original_name']) ?><br>
                        <strong>Ukuran:</strong> <?= e(formatBytes($note['file']['size'])) ?>
                    </p>
                    <a href="<?= BASE_URL ?>/view.php?id=<?= e($id) ?>&download=1" class="btn btn-primary">⬇ Unduh File</a>
                </div>
            <?php endif; ?>
        </article>
    </main>
</body>
</html>
