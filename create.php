<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$errors = [];
$title = '';
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';

    if ($title === '') {
        $errors[] = 'Judul catatan wajib diisi.';
    }
    if ($content === '') {
        $errors[] = 'Isi catatan wajib diisi.';
    }

    $fileInfo = [];
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handleUpload($_FILES['attachment']);
        if (!empty($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } elseif (!empty($uploadResult['stored_name'])) {
            $fileInfo = $uploadResult;
        }
    }

    if (empty($errors)) {
        $id = saveNote($title, $content, $fileInfo);
        header('Location: ' . BASE_URL . '/dashboard.php?created=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Catatan | Note Repo</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
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
        <h1 class="page-title">Buat Catatan Baru</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="note-form">
            <div class="form-group">
                <label for="title">Judul</label>
                <input type="text" id="title" name="title" value="<?= e($title) ?>" placeholder="Judul catatan" required>
            </div>

            <div class="form-group">
                <label for="content">Isi Catatan (Teks / Kode)</label>
                <textarea id="content" name="content" rows="18" placeholder="Tulis catatan Anda di sini..." required><?= e($content) ?></textarea>
            </div>

            <div class="form-group">
                <label for="attachment">Lampiran (maks. 10 MB)</label>
                <input type="file" id="attachment" name="attachment">
                <small class="help-text">Kosongkan jika tidak ingin melampirkan file.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Simpan Catatan</button>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </main>
</body>
</html>
