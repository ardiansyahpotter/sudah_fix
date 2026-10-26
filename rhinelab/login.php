<?php
session_start();
require_once __DIR__ . '/data.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === '' || $password === '') {
        $message = 'Username dan password wajib diisi.';
        $messageType = 'error';
    } else {
        $user = login_user($username, $password);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['doctor_id'] = $user['doctor_id'];
            
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
                exit;
            } elseif ($user['role'] === 'doctor') {
                header('Location: dokter.php');
                exit;
            }
        } else {
            $message = 'Username atau password salah.';
            $messageType = 'error';
        }
    }
}

// Jika sudah login, redirect ke halaman yang sesuai
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin.php');
        exit;
    } elseif ($_SESSION['role'] === 'doctor') {
        header('Location: dokter.php');
        exit;
    }
}

$doctors = load_data('doctors');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Klinik Sederhana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/style.css" />
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 3rem 2rem;
            width: 100%;
            max-width: 420px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #0f172a;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #64748b;
            margin: 0;
        }
        .form-control {
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.8);
            padding: 14px 16px;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
        }
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function () {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        togglePassword.textContent = 'Sembunyikan';
                    } else {
                        passwordInput.type = 'password';
                        togglePassword.textContent = 'Tampilkan';
                    }
                });
            }
        });
    </script>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h1>Klinik Sederhana</h1>
        <p>Portal Admin & Dokter</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label" for="username">Username / ID Dokter</label>
            <input class="form-control" type="text" id="username" name="username" placeholder="Masukkan username admin atau ID dokter" required autofocus />
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <div class="input-group">
                <input class="form-control" type="password" id="password" name="password" placeholder="Masukkan password" required />
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">Tampilkan</button>
            </div>
        </div>
        <button class="btn btn-primary btn-login" type="submit">Login</button>
    </form>

    <div class="alert alert-info mt-3" role="alert">
        <p class="mb-1"><strong>Akun default:</strong></p>
        <ul class="mb-1">
            <li>Admin: <strong>admin</strong> / <strong>admin123</strong></li>
            <?php foreach ($doctors as $doctor): ?>
                <li>Dokter: <strong><?php echo $doctor['id']; ?></strong> / <strong>doctor123</strong> — <?php echo htmlspecialchars($doctor['name']); ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="mb-0">Gunakan <strong>ID dokter</strong> sebagai username dan <strong>doctor123</strong> sebagai password untuk login dokter.</p>
    </div>

    <div class="login-footer">
        <p class="mb-0">Kembali ke <a href="index.php">halaman utama</a></p>
    </div>
</div>
</body>
</html>
