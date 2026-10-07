<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Jika sudah login, arahkan ke dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = (string) $_POST['password'];
    if (attemptLogin($password)) {
        $_SESSION['logged_in'] = true;
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Password salah. Silakan coba lagi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Note Repo</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.min.css">
</head>
<body class="auth-body">
    <main class="auth-card">
        <div class="logo">📝 Note Repo</div>
        <p class="subtitle">Masukkan password untuk mengakses repository catatan Anda.</p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
        </form>

        <p class="hint">Password default: <strong>mankcodet</strong></p>
    </main>
</body>
</html>
