<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Ambil daftar pengguna
$stmt = $pdo->query("SELECT id, username, display_name, role, created_at, last_login FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses tambah/edit pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'guest']) ? $_POST['role'] : 'guest';
    $user_id = trim($_POST['user_id'] ?? '');

    $errors = [];

    // Validasi username
    if (empty($username) || strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, atau garis bawah.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username sudah digunakan.";
        }
    }

    // Validasi kata sandi untuk tambah pengguna
    if ($action === 'add' && (empty($password) || strlen($password) < 6)) {
        $errors[] = "Kata sandi harus minimal 6 karakter.";
    }

    // Validasi nama tampilan
    if (empty($display_name) || strlen($display_name) > 100) {
        $errors[] = "Nama tampilan harus 1-100 karakter.";
    }

    // Proses jika tidak ada kesalahan
    if (empty($errors)) {
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, display_name, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $display_name, $role]);
            } elseif ($action === 'edit') {
                $updates = ["username = ?", "display_name = ?", "role = ?"];
                $params = [$username, $display_name, $role];
                if (!empty($password)) {
                    $updates[] = "password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $params[] = $user_id;
                $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?");
                $stmt->execute($params);
            }
            header("Location: users.php?toast=success&message=" . urlencode("Pengguna berhasil disimpan."));
            exit;
        } catch (PDOException $e) {
            $errors[] = "Kesalahan database: " . $e->getMessage();
        }
    }
    header("Location: users.php?toast=error&message=" . urlencode(implode(" ", $errors)));
    exit;
}

// Proses hapus pengguna
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $user_id = trim($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND username != ?");
        $stmt->execute([$user_id, $_SESSION['user']]);
        header("Location: users.php?toast=success&message=Pengguna berhasil dihapus.");
        exit;
    } catch (PDOException $e) {
        header("Location: users.php?toast=error&message=Kesalahan database: " . urlencode($e->getMessage()));
        exit;
    }
}

date_default_timezone_set('Asia/Jakarta');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - MaCloud</title>
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
            padding-top: 60px;
        }
        main {
            flex: 1 0 auto;
        }
        .navbar {
            background-color: rgba(248, 249, 250, 0.41);
        }
        .navbar-icon {
            width: 150px;
            height: 30px;
            object-fit: contain;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .modal-body form {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        /* Desain Responsif */
        @media (max-width: 576px) {
            .navbar-nav {
                text-align: center;
            }
            .navbar-icon {
                width: 120px;
                height: 25px;
            }
            .table-responsive {
                font-size: 0.9rem;
            }
            .btn-sm {
                font-size: 0.8rem;
                padding: 0.5rem;
            }
            .modal-body {
                padding: 1rem;
            }
            .form-control {
                font-size: 1rem;
            }
            .modal-footer .btn {
                font-size: 0.8rem;
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top navbar-light bg-light shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="gallery.php">
                <img src="../assets/img/icon.png" alt="Gallery Icon" class="navbar-icon me-2">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="gallery.php"><i class="fas fa-arrow-left"></i> Kembali ke Galeri</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="toast-container">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle"></i> <span id="successMessage"><?php echo isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : ''; ?></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
        </div>
        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-circle"></i> <span id="errorMessage"><?php echo isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : ''; ?></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
        </div>
    </div>
    <main class="container mt-5">
        <h2>Manajemen Pengguna</h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()"><i class="fas fa-plus"></i> Tambah Pengguna</button>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nama Tampilan</th>
                        <th>Peran</th>
                        <th>Dibuat</th>
                        <th>Login Terakhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                        <td><?php echo $user['role'] === 'admin' ? 'Admin' : 'Tamu'; ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></td>
                        <td><?php echo $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : '-'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#userModal" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"><i class="fas fa-edit"></i></button>
                            <?php if ($user['username'] !== $_SESSION['user']): ?>
                            <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus pengguna ini?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel"><i class="fas fa-user"></i> Tambah Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm" action="users.php" method="POST">
                        <input type="hidden" name="action" id="action" value="add">
                        <input type="hidden" name="user_id" id="user_id">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required pattern="[a-zA-Z0-9_]{3,50}" title="Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, atau garis bawah.">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Kata Sandi</label>
                            <input type="password" class="form-control" id="password" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="display_name" class="form-label">Nama Tampilan</label>
                            <input type="text" class="form-control" id="display_name" name="display_name" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Peran</label>
                            <select class="form-control" id="role" name="role">
                                <option value="guest">Tamu</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fas fa-times"></i> Tutup</button>
                    <button type="submit" form="userForm" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </div>
        </div>
    </div>
    <footer class="text-center mt-4">
        <p>© 2024 - <?php echo date("Y"); ?> All Rights Reserved.</p>
        <p>Waktu Server: <?php echo date("d M Y H:i:s"); ?> WIB</p>
    </footer>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetForm() {
            document.getElementById('userForm').reset();
            document.getElementById('action').value = 'add';
            document.getElementById('user_id').value = '';
            document.getElementById('userModalLabel').innerText = 'Tambah Pengguna';
            document.getElementById('password').required = true;
        }

        function editUser(user) {
            document.getElementById('action').value = 'edit';
            document.getElementById('user_id').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('display_name').value = user.display_name;
            document.getElementById('role').value = user.role;
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('userModalLabel').innerText = 'Edit Pengguna';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('toast') === 'success') {
                document.getElementById('successMessage').innerText = urlParams.get('message');
                new bootstrap.Toast(document.getElementById('successToast')).show();
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('toast') === 'error') {
                document.getElementById('errorMessage').innerText = urlParams.get('message');
                new bootstrap.Toast(document.getElementById('errorToast')).show();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>