<?php
session_start();
include '../service/db_connect.php';

if (!isset($_GET['token'])) {
    header("Location: ../index.php");
    exit;
}

$token = $_GET['token'];
$stmt = $pdo->prepare("SELECT user_id, expires FROM password_resets WHERE token = ?");
$stmt->execute([$token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset || strtotime($reset['expires']) < time()) {
    header("Location: ../index.php?toast=error&message=Token tidak valid atau kadaluarsa.");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    if (strlen($password) < 6) {
        $error = "Kata sandi harus minimal 6 karakter.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            header("Location: ../index.php?toast=success&message=Kata sandi berhasil direset.");
            exit;
        } catch (PDOException $e) {
            $error = "Kesalahan database: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi - MaCloud</title>
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
        @media (max-width: 576px) {
            .container {
                margin-top: 20px;
                padding: 0 15px;
            }
            .form-control, .btn {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Kata Sandi</h2>
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
            <div class="mb-3">
                <label for="password" class="form-label">Kata Sandi Baru</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Reset Kata Sandi</button>
            <a href="../index.php" class="btn btn-link">Kembali ke Login</a>
        </form>
    </div>
    <footer class="text-center mt-4">
        <p>© 2024 - <?php echo date("Y"); ?> All Rights Reserved.</p>
        <p>Waktu Server: <?php echo date("d M Y H:i:s"); ?> WIB</p>
    </footer>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>