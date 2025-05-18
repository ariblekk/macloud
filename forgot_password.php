<?php
session_start();
include '../service/db_connect.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Pastikan PHPMailer terinstal melalui Composer

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if (empty($username)) {
        header("Location: forgot_password.php?toast=error&message=Username tidak boleh kosong.");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['email'])) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires]);

            $reset_link = "http://yourdomain.com/reset_password.php?token=$token"; // Ganti dengan domain Anda
            $mail = new PHPMailer(true);
            try {
                // Pengaturan SMTP
                $mail->isSMTP();
                $mail->Host = 'smtp.example.com'; // Ganti dengan host SMTP Anda
                $mail->SMTPAuth = true;
                $mail->Username = 'your_email@example.com'; // Ganti dengan email Anda
                $mail->Password = 'your_password'; // Ganti dengan kata sandi email Anda
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Pengaturan email
                $mail->setFrom('no-reply@yourdomain.com', 'MaCloud');
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Reset Kata Sandi MaCloud';
                $mail->Body = "Klik tautan berikut untuk mereset kata sandi Anda: <a href='$reset_link'>$reset_link</a><br>Tautan ini berlaku selama 1 jam.";
                $mail->AltBody = "Klik tautan berikut untuk mereset kata sandi Anda: $reset_link\nTautan ini berlaku selama 1 jam.";

                $mail->send();
                header("Location: forgot_password.php?toast=success&message=Instruksi reset telah dikirim ke email Anda.");
                exit;
            } catch (Exception $e) {
                header("Location: forgot_password.php?toast=error&message=Gagal mengirim email: " . urlencode($mail->ErrorInfo));
                exit;
            }
        } else {
            header("Location: forgot_password.php?toast=error&message=Username tidak ditemukan atau email belum terdaftar.");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: forgot_password.php?toast=error&message=Kesalahan database: " . urlencode($e->getMessage()));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi - MaCloud</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/fgallery.png">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
            background-color: #fff;
            color: #000;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 500px;
            margin-top: 50px;
            flex: 1 0 auto;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .form-control, .btn {
            border-radius: 0.25rem;
        }
        /* Desain Responsif */
        @media (max-width: 576px) {
            .container {
                margin-top: 20px;
                padding: 0 15px;
            }
            .form-control, .btn {
                font-size: 1rem;
            }
            h2 {
                font-size: 1.5rem;
            }
            .btn-link {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="toast-container">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle"></i> <?php echo isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : ''; ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
        </div>
        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-circle"></i> <?php echo isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : ''; ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
        </div>
    </div>
    <div class="container">
        <h2>Lupa Kata Sandi</h2>
        <form action="forgot_password.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Permintaan</button>
            <a href="../index.php" class="btn btn-link">Kembali ke Login</a>
        </form>
    </div>
    <footer class="text-center mt-4">
        <p>© 2024 - <?php echo date("Y"); ?> All Rights Reserved.</p>
        <p>Waktu Server: <?php echo date("d M Y H:i:s"); ?> WIB</p>
    </footer>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('toast') === 'success') {
                new bootstrap.Toast(document.getElementById('successToast')).show();
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('toast') === 'error') {
                new bootstrap.Toast(document.getElementById('errorToast')).show();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>