<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$notesData = getNotesPaginated($page);
$notes = $notesData['items'];

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = sanitizeId($_POST['delete_id']);
    if (deleteNote($deleteId)) {
        header('Location: ' . BASE_URL . '/dashboard.php?deleted=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Note Repo</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.min.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a href="<?= BASE_URL ?>/dashboard.php" class="brand">📝 Note Repo</a>
            <nav class="nav">
                <a href="<?= BASE_URL ?>/create.php" class="btn btn-primary btn-sm">+ Buat Catatan</a>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline btn-sm">Keluar</a>
            </nav>
        </div>
    </header>

    <main class="container main">
        <h1 class="page-title">Daftar Catatan</h1>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
            <div class="alert alert-success">Catatan berhasil dihapus.</div>
        <?php endif; ?>
        <?php if (isset($_GET['created']) && $_GET['created'] === '1'): ?>
            <div class="alert alert-success">Catatan berhasil disimpan.</div>
        <?php endif; ?>

        <?php if (empty($notes)): ?>
            <div class="empty-state">
                <div class="empty-icon">📂</div>
                <h3>Belum ada catatan</h3>
                <p>Mulailah membuat catatan teks/kode atau lampirkan file.</p>
                <a href="<?= BASE_URL ?>/create.php" class="btn btn-primary">Buat Catatan Pertama</a>
            </div>
        <?php else: ?>
            <div class="note-grid">
                <?php foreach ($notes as $id => $note): ?>
                    <article class="note-card">
                        <a href="<?= BASE_URL ?>/view.php?id=<?= e($id) ?>" class="note-link">
                            <h2 class="note-title"><?= e($note['title'] ?: '(Tanpa judul)') ?></h2>
                            <p class="note-meta">
                                <?= e(date('d M Y H:i', strtotime($note['created_at']))) ?>
                                <?php if (!empty($note['has_attachment'])): ?>
                                    <span class="badge">📎 <?= e(formatBytes($note['file_size'])) ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="note-excerpt">
                                <?= e($note['excerpt']) ?>
                            </p>
                        </a>
                        <form method="POST" action="" class="note-actions" onsubmit="return confirm('Yakin ingin menghapus catatan ini?');">
                            <input type="hidden" name="delete_id" value="<?= e($id) ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus">🗑 Hapus</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($notesData['totalPages'] > 1): ?>
                <nav class="pagination" aria-label="Navigasi halaman">
                    <?php if ($notesData['page'] > 1): ?>
                        <a href="<?= BASE_URL ?>/dashboard.php?page=<?= $notesData['page'] - 1 ?>" class="btn btn-outline btn-sm">← Sebelumnya</a>
                    <?php endif; ?>

                    <span class="page-info">Halaman <?= $notesData['page'] ?> dari <?= $notesData['totalPages'] ?></span>

                    <?php if ($notesData['page'] < $notesData['totalPages']): ?>
                        <a href="<?= BASE_URL ?>/dashboard.php?page=<?= $notesData['page'] + 1 ?>" class="btn btn-outline btn-sm">Berikutnya →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
