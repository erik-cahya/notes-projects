<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$id = $_GET['id'] ?? '';
$note = readNote($id);

if ($note === null) {
    header('HTTP/1.1 404 Not Found');
    echo 'Catatan tidak ditemukan.';
    exit;
}

$errors = [];
$title = $note['title'];
$content = $note['content'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $removeAttachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1';

    if ($title === '') {
        $errors[] = 'Judul catatan wajib diisi.';
    }
    if ($content === '') {
        $errors[] = 'Isi catatan wajib diisi.';
    }

    $fileInfo = [];
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileInfo = handleUpload($_FILES['attachment']);
        if (!empty($fileInfo['error'])) {
            $errors[] = $fileInfo['error'];
            $fileInfo = [];
        }
    }

    if (empty($errors)) {
        if (updateNote($id, $title, $content, $fileInfo, $removeAttachment)) {
            header('Location: ' . BASE_URL . '/view.php?id=' . e($id) . '&updated=1');
            exit;
        } else {
            $errors[] = 'Gagal memperbarui catatan.';
        }
    }
}

$hasAttachment = !empty($note['file']['stored_name']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Catatan | Note Repo</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.min.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a href="<?= BASE_URL ?>/dashboard.php" class="brand">📝 Note Repo</a>
            <nav class="nav">
                <a href="<?= BASE_URL ?>/view.php?id=<?= e($id) ?>" class="btn btn-outline btn-sm">← Kembali</a>
            </nav>
        </div>
    </header>

    <main class="container main">
        <h1 class="page-title">Edit Catatan</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="form-card">
            <div class="form-group">
                <label for="title">Judul</label>
                <input type="text" id="title" name="title" value="<?= e($title) ?>" required>
            </div>

            <div class="form-group">
                <label for="content">Isi Catatan</label>
                <div class="editor-toolbar" aria-label="Toolbar format">
                    <button type="button" data-tag="**" title="Bold (teks tebal)"><strong>B</strong></button>
                    <button type="button" data-tag="`" title="Inline code">&lt;/&gt;</button>
                    <button type="button" data-tag="```" title="Code block">{ }</button>
                    <button type="button" data-tag="---" title="Divider">—</button>
                </div>
                <textarea id="content" name="content" class="editor-textarea" rows="18" required><?= e($content) ?></textarea>
                <small class="form-hint">Dukungan format: <strong>**bold**</strong>, <code>`inline code`</code>, <code>```bahasa\nkode\n```</code>, dan <code>---</code> untuk divider.</small>
            </div>

            <?php if ($hasAttachment): ?>
                <div class="form-group">
                    <p class="attachment-info">
                        Lampiran saat ini:
                        <a href="<?= BASE_URL ?>/view.php?id=<?= e($id) ?>&download=1" target="_blank">
                            <?= e($note['file']['original_name']) ?> (<?= e(formatBytes($note['file']['size'])) ?>)
                        </a>
                    </p>
                    <label class="checkbox-label">
                        <input type="checkbox" name="remove_attachment" value="1">
                        Hapus lampiran saat ini
                    </label>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="attachment"><?= $hasAttachment ? 'Ganti Lampiran' : 'Tambah Lampiran (opsional)' ?></label>
                <input type="file" id="attachment" name="attachment">
                <small class="form-hint">Maksimal <?= e(formatBytes(MAX_UPLOAD_SIZE)) ?>. File PHP/eksekusi diblokir demi keamanan.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                <a href="<?= BASE_URL ?>/view.php?id=<?= e($id) ?>" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </main>

    <script>
        (function () {
            const textarea = document.getElementById('content');
            document.querySelectorAll('.editor-toolbar button[data-tag]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const tag = btn.getAttribute('data-tag');
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const value = textarea.value;
                    const selected = value.substring(start, end);

                    let replacement = '';
                    if (tag === '---') {
                        replacement = '\n---\n';
                    } else if (tag === '```') {
                        replacement = '```\n' + (selected || 'kode di sini') + '\n```';
                    } else {
                        replacement = tag + (selected || 'teks') + tag;
                    }

                    textarea.value = value.substring(0, start) + replacement + value.substring(end);
                    textarea.focus();
                    const newPos = start + replacement.length;
                    textarea.setSelectionRange(newPos, newPos);
                });
            });
        })();
    </script>
</body>
</html>
