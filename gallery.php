<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

$upload_dir = 'Uploads/';
$allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

$items_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Count total images
$stmt = $pdo->query("SELECT COUNT(*) FROM images");
$total_files = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_files / $items_per_page));

// Fetch images for the current page
$stmt = $pdo->prepare("SELECT * FROM images ORDER BY upload_date DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all users for the view users table (admin only)
$users = [];
if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->query("SELECT username, email, display_name, role, last_login FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

date_default_timezone_set('Asia/Jakarta');
$hour = date('H');
$greeting = match (true) {
    $hour >= 5 && $hour < 11 => 'Selamat Pagi',
    $hour >= 11 && $hour < 15 => 'Selamat Siang',
    $hour >= 15 && $hour < 18 => 'Selamat Sore',
    default => 'Selamat Malam'
};

$display_name = $_SESSION['display_name'] ?? 'Anomali';
$toast_message = isset($_GET['message']) ? urldecode($_GET['message']) : 'Failed to upload file!';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaCloud</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/fgallery.png">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #fff;
            color: #000;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'JetBrains Mono', monospace;
            padding-top: 60px;
        }
        main { flex: 1 0 auto; }
        .navbar { background-color: rgba(248, 249, 250, 0.41); }
        .card { background-color: #fff; }
        .gallery img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            cursor: pointer;
        }
        .card-body { padding: 10px; }
        .card-text {
            font-size: 0.9rem;
            color: #333;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 0.5rem;
        }
        .gallery .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .gallery .card:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background-color: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: pulse 2s infinite;
        }
        .fab:hover { background-color: #0056b3; }
        .modal-dialog-full {
            max-width: 100%;
            width: 100vw;
            height: 100dvh;
            margin: 0;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
        }
        .modal-content {
            width: 100%;
            height: 100%;
            border-radius: 8px;
            border: none;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            flex-shrink: 0;
            background-color: #f8f9fa;
        }
        .modal-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
        }
        .modal-footer {
            flex-shrink: 0;
            justify-content: end;
        }
        .modal-content img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }
        .modal-caption {
            margin-top: 10px;
            font-size: 1rem;
            color: #333;
            text-align: center;
        }
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.7) !important;
            opacity: 1 !important;
        }
        #uploadModal .modal-body form, #settingsModal .modal-body form, #userManagementModal .modal-body form {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
        }
        .nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 15px;
            font-size: 24px;
            cursor: pointer;
            border-radius: 20%;
            z-index: 1000;
            transition: background-color 0.3s;
        }
        .nav-btn:hover {
            background-color: rgba(0, 0, 0, 0.8);
        }
        .nav-btn.prev { left: 20px; }
        .nav-btn.next { right: 20px; }
        .nav-btn:disabled {
            background-color: rgba(0, 0, 0, 0.3);
            cursor: not-allowed;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .toast {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-body i { margin-right: 8px; }
        .modal.fade .modal-dialog {
            transform: translateY(-50px);
            transition: transform 0.3s ease-out;
        }
        .modal.show .modal-dialog { transform: translateY(0); }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .logout-btn { margin-left: 10px; }
        .navbar-icon {
            width: 150px;
            height: 30px;
            vertical-align: middle;
            object-fit: contain;
        }
        .total-photos {
            margin-left: 10px;
            font-size: 0.9rem;
            color: #333;
            font-weight: 500;
        }
        @media (max-width: 576px) {
            .gallery .col-md-3 { flex: 0 0 100%; max-width: 100%; }
            .modal-body { padding: 1rem; }
            .nav-btn { padding: 10px; font-size: 18px; }
            .nav-btn.prev { left: 10px; }
            .nav-btn.next { right: 10px; }
        }
        #deleteSelected, #downloadSelected { display: none; }
        #deleteSelected.active, #downloadSelected.active { display: inline-block; }
        a.btn:disabled, a.btn.disabled {
            pointer-events: none;
            opacity: 0.65;
            cursor: not-allowed;
        }
        .dropdown-menu { min-width: 150px; }
        .dropdown-item i { margin-right: 8px; }
        .table-responsive { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top navbar-light bg-light shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
                <img src="../assets/img/icon.png" alt="Gallery Icon" class="navbar-icon me-2"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <div class="btn-group" role="group" aria-label="Basic example">
                            <a class="btn btn-danger btn-sm" type="submit" form="batchActionForm" formaction="batch_delete.php" id="deleteSelected" disabled><i class="fas fa-trash-alt"></i> Delete</a>
                            <a class="btn btn-primary btn-sm" type="submit" form="batchActionForm" formaction="batch_download.php" id="downloadSelected" disabled><i class="fas fa-download"></i> Download</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <span class="total-photos" aria-label="Total photos in gallery: <?php echo $total_files; ?>">Total Photos: <?php echo $total_files; ?></span>
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <input type="checkbox" id="markAll" class="form-check-input me-2" aria-label="Mark all <?php echo $total_files; ?> photos">
                        <?php endif; ?>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user"></i> <?php echo $greeting; ?>, <?php echo htmlspecialchars($display_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#userManagementModal"><i class="fas fa-users"></i> Manage Users</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal"><i class="fas fa-cog"></i> Settings</a></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="toast-container">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle"></i> File uploaded successfully!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($toast_message); ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div id="deleteToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-trash"></i> File deleted successfully!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div id="downloadToast" class="toast align-items-center text-white bg-info border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-download"></i> File download started!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div id="settingsToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle"></i> Settings updated successfully!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div id="userManagementToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle"></i> User added successfully!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div id="userDeletedToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-trash"></i> User deleted successfully!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <main class="container mt-5">
        <form id="batchActionForm" method="POST">
            <div class="row gallery">
                <?php if (empty($images)): ?>
                    <div class="col-12 text-center">
                        <p>Kosong.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($images as $image): ?>
                        <?php
                        $ext = strtolower(pathinfo($image['filename'], PATHINFO_EXTENSION));
                        if (in_array($ext, $allowed_types)):
                            $upload_date = date('d M Y H:i', strtotime($image['upload_date']));
                            $uploader = htmlspecialchars($image['uploader']);
                        ?>
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <?php if ($_SESSION['role'] == 'admin'): ?>
                                        <input type="checkbox" name="files[]" value="<?php echo htmlspecialchars($image['filename']); ?>" class="form-check-input position-absolute top-0 start-0 m-2 file-checkbox">
                                    <?php endif; ?>
                                    <img src="<?php echo $image['file_path']; ?>" class="card-img-top" alt="<?php echo $image['filename']; ?>" 
                                         data-bs-toggle="modal" data-bs-target="#mediaModal" 
                                         data-file="<?php echo htmlspecialchars($image['filename']); ?>" 
                                         data-type="image">
                                    <div class="card-body">
                                        <p class="card-text"><i class="fas fa-calendar-alt"></i> <?php echo $upload_date; ?></p>
                                        <p class="card-text"><i class="fas fa-user"></i> <?php echo $uploader; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Gallery pagination">
                <ul class="pagination justify-content-center mt-4">
                    <li class="page-item <?php echo $current_page == 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </main>
    <div class="fab" data-bs-toggle="modal" data-bs-target="#uploadModal" aria-label="Upload images">
        <i class="fas fa-upload"></i>
    </div>
    <div class="modal fade" id="mediaModal" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-full modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mediaModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <button class="nav-btn prev" id="prevImage" aria-label="Previous image"><i class="fas fa-chevron-left"></i></button>
                    <div id="mediaContent"></div>
                    <button class="nav-btn next" id="nextImage" aria-label="Next image"><i class="fas fa-chevron-right"></i></button>
                    <div id="mediaCaption" class="modal-caption"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fas fa-times"></i> Close</button>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a id="deleteButton" href="#" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this file?')"><i class="fas fa-trash"></i> Delete</a>
                    <?php endif; ?>
                    <a id="downloadButton" href="#" class="btn btn-success btn-sm" onclick="handleDownload()"><i class="fas fa-download"></i> Download</a>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel"><i class="fas fa-upload"></i> Unggah Gambar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="files" class="form-label">Pilih Gambar</label>
                            <input type="file" class="form-control" id="files" name="files[]" accept="image/*" multiple required>
                        </div>
                        <div class="mb-3">
                            <label for="caption" class="form-label">Keterangan (optional)</label>
                            <input type="text" class="form-control" autocomplete="off" id="caption" name="caption" maxlength="100" placeholder="Masukkan keterangan (maks 100 karakter)">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fas fa-times"></i> Close</button>
                    <button type="submit" form="uploadForm" class="btn btn-primary btn-sm" id="uploadButton"><i class="fas fa-cloud-upload-alt"></i> Upload</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel"><i class="fas fa-cog"></i> Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="settingsForm" action="settings.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_SESSION['user']); ?>" required aria-describedby="usernameHelp">
                                <div id="usernameHelp" class="form-text">Enter your new username (minimum 3 characters).</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" aria-describedby="passwordHelp">
                                <button type="button" class="input-group-text toggle-password" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordHelp" class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" aria-describedby="confirmPasswordHelp">
                                <button type="button" class="input-group-text toggle-confirm-password" aria-label="Toggle confirm password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="confirmPasswordHelp" class="form-text">Re-enter your new password for confirmation.</div>
                        </div>
                        <div class="mb-3">
                            <label for="display_name" class="form-label">Display Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="display_name" name="display_name" value="<?php echo htmlspecialchars($_SESSION['display_name']); ?>" required aria-describedby="displayNameHelp">
                                <div id="displayNameHelp" class="form-text">Enter your display name (visible to others).</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fas fa-times"></i> Close</button>
                    <button type="submit" form="settingsForm" class="btn btn-primary btn-sm" id="saveSettingsButton"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </div>
    </div>
    <!-- User Management Modal -->
    <div class="modal fade" id="userManagementModal" tabindex="-1" aria-labelledby="userManagementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userManagementModalLabel"><i class="fas fa-users"></i> Manage Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <ul class="nav nav-tabs mb-3" id="userManagementTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="add-user-tab" data-bs-toggle="tab" data-bs-target="#add-user" type="button" role="tab" aria-controls="add-user" aria-selected="true">Add User</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="view-users-tab" data-bs-toggle="tab" data-bs-target="#view-users" type="button" role="tab" aria-controls="view-users" aria-selected="false">View Users</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="userManagementTabContent">
                            <div class="tab-pane fade show active" id="add-user" role="tabpanel" aria-labelledby="add-user-tab">
                                <form id="addUserForm" action="manage_users.php" method="POST">
                                    <input type="hidden" name="action" value="add">
                                    <div class="mb-3">
                                        <label for="new_username" class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="new_username" name="username" required aria-describedby="newUsernameHelp">
                                            <div id="newUsernameHelp" class="form-text">Enter username (minimum 3 characters).</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_email" class="form-label">Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="new_email" name="email" required aria-describedby="newEmailHelp">
                                            <div id="newEmailHelp" class="form-text">Enter a valid email address.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="new_password" name="password" required aria-describedby="newPasswordHelp">
                                            <button type="button" class="input-group-text toggle-new-password" aria-label="Toggle password visibility">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div id="newPasswordHelp" class="form-text">Password must be at least 8 characters long.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_new_password" class="form-label">Confirm Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_password" required aria-describedby="confirmNewPasswordHelp">
                                            <button type="button" class="input-group-text toggle-confirm-new-password" aria-label="Toggle confirm password visibility">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div id="confirmNewPasswordHelp" class="form-text">Re-enter the password for confirmation.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_display_name" class="form-label">Display Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                            <input type="text" class="form-control" id="new_display_name" name="display_name" required aria-describedby="newDisplayNameHelp">
                                            <div id="newDisplayNameHelp" class="form-text">Enter display name (visible to others).</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <select class="form-select" id="role" name="role" required aria-describedby="roleHelp">
                                            <option value="" disabled selected>Select a role</option>
                                            <option value="admin">Admin</option>
                                            <option value="guest">Guest</option>
                                        </select>
                                        <div id="roleHelp" class="form-text">Select the user's role.</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm" id="addUserButton"><i class="fas fa-user-plus"></i> Add User</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="view-users" role="tabpanel" aria-labelledby="view-users-tab">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Display Name</th>
                                                <th>Role</th>
                                                <th>Last Login</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email'] ?: 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                                    <td><?php echo $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                                    <td>
                                                        <?php if ($user['username'] !== $_SESSION['user']): ?>
                                                            <form action="manage_users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-muted">Cannot delete own account</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>User management is only available for admin users.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fas fa-times"></i> Close</button>
                </div>
            </div>
        </div>
    </div>
    <footer class="text-center">
        <p>© 2024 - <?php echo date("Y"); ?> All Rights Reserved.</p>
        <p>Server Time: <?php echo date("d M Y H:i:s"); ?> WIB</p>
    </footer>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap JavaScript is not loaded.');
        }

        function showToast(toastId) {
            const toastElement = document.getElementById(toastId);
            if (toastElement) {
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
            }
        }

        function handleDownload() {
            showToast('downloadToast');
            const mediaModal = document.getElementById('mediaModal');
            if (mediaModal) {
                const modalInstance = bootstrap.Modal.getInstance(mediaModal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const mediaModal = document.getElementById('mediaModal');
            const pagedFiles = <?php echo json_encode(array_column($images, 'filename')); ?>;
            const captions = <?php echo json_encode(array_column($images, null, 'filename')); ?>;
            let currentIndex = -1;

            if (mediaModal) {
                mediaModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const file = button.getAttribute('data-file');
                    const type = button.getAttribute('data-type');
                    currentIndex = pagedFiles.indexOf(file);

                    updateModalContent(file, type);
                });

                document.getElementById('prevImage').addEventListener('click', function () {
                    if (currentIndex > 0) {
                        currentIndex--;
                    } else {
                        currentIndex = pagedFiles.length - 1;
                    }
                    updateModalContent(pagedFiles[currentIndex], 'image');
                });

                document.getElementById('nextImage').addEventListener('click', function () {
                    if (currentIndex < pagedFiles.length - 1) {
                        currentIndex++;
                    } else {
                        currentIndex = 0;
                    }
                    updateModalContent(pagedFiles[currentIndex], 'image');
                });

                function updateModalContent(file, type) {
                    const mediaContent = document.getElementById('mediaContent');
                    const mediaName = document.getElementById('mediaModalLabel');
                    const mediaCaption = document.getElementById('mediaCaption');
                    const deleteButton = document.getElementById('deleteButton');
                    const downloadButton = document.getElementById('downloadButton');

                    if (!file || !type) {
                        mediaContent.innerHTML = '<p>Error: Missing file or type information.</p>';
                        return;
                    }

                    const filePath = `Uploads/${file}`;
                    const ext = file.split('.').pop().toLowerCase();
                    if (!['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                        mediaContent.innerHTML = '<p>Error: Unsupported file format.</p>';
                        return;
                    }

                    if (type === 'image') {
                        mediaContent.innerHTML = `<img src="${filePath}" alt="${file}" class="img-fluid">`;
                        const caption = captions[file]?.caption || 'No caption';
                        const uploader = captions[file]?.uploader || 'Unknown';
                        mediaCaption.innerHTML = `<p><i class="fas fa-comment"></i> ${caption}</p>`;
                        mediaName.innerHTML = `<i class="fas fa-user"></i> ${uploader}`;
                    }

                    if (deleteButton) {
                        deleteButton.href = `delete.php?file=${encodeURIComponent(file)}&toast=delete`;
                    }
                    if (downloadButton) {
                        downloadButton.href = '<?php echo ($_SESSION['role'] == 'guest') ? 'download.php?file=' : 'Uploads/'; ?>' + encodeURIComponent(file);
                        downloadButton.setAttribute('download', file);
                    }
                }
            }

            const markAllCheckbox = document.getElementById('markAll');
            const deleteSelectedBtn = document.getElementById('deleteSelected');
            const downloadSelectedBtn = document.getElementById('downloadSelected');
            const batchActionForm = document.getElementById('batchActionForm');
            const checkboxes = document.querySelectorAll('.file-checkbox');
            // Password visibility toggle for settings
            const togglePassword = document.querySelector('.toggle-password');
            const passwordInput = document.querySelector('#password');
            const toggleConfirmPassword = document.querySelector('.toggle-confirm-password');
            const confirmPasswordInput = document.querySelector('#confirm_password');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye', type === 'password');
                    icon.classList.toggle('fa-eye-slash', type === 'text');
                });
            }

            if (toggleConfirmPassword && confirmPasswordInput) {
                toggleConfirmPassword.addEventListener('click', function () {
                    const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordInput.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye', type === 'password');
                    icon.classList.toggle('fa-eye-slash', type === 'text');
                });
            }

            // Password visibility toggle for add user
            const toggleNewPassword = document.querySelector('.toggle-new-password');
            const newPasswordInput = document.querySelector('#new_password');
            const toggleConfirmNewPassword = document.querySelector('.toggle-confirm-new-password');
            const confirmNewPasswordInput = document.querySelector('#confirm_new_password');

            if (toggleNewPassword && newPasswordInput) {
                toggleNewPassword.addEventListener('click', function () {
                    const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    newPasswordInput.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye', type === 'password');
                    icon.classList.toggle('fa-eye-slash', type === 'text');
                });
            }

            if (toggleConfirmNewPassword && confirmNewPasswordInput) {
                toggleConfirmNewPassword.addEventListener('click', function () {
                    const type = confirmNewPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmNewPasswordInput.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye', type === 'password');
                    icon.classList.toggle('fa-eye-slash', type === 'text');
                });
            }

            // Settings form validation
            const settingsForm = document.getElementById('settingsForm');
            const saveSettingsButton = document.getElementById('saveSettingsButton');
            if (settingsForm && saveSettingsButton) {
                settingsForm.addEventListener('submit', function (event) {
                    const username = document.getElementById('username').value;
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    const displayName = document.getElementById('display_name').value;

                    // Validate username length
                    if (username.length < 3) {
                        event.preventDefault();
                        showToast('errorToast');
                        document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Username must be at least 3 characters long!';
                        return;
                    }

                    // Validate password if provided
                    if (password) {
                        if (password.length < 8) {
                            event.preventDefault();
                            showToast('errorToast');
                            document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters long!';
                            return;
                        }
                        if (password !== confirmPassword) {
                            event.preventDefault();
                            showToast('errorToast');
                            document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
                            return;
                        }
                    }

                    // Validate display name
                    if (displayName.length < 1) {
                        event.preventDefault();
                        showToast('errorToast');
                        document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Display name cannot be empty!';
                        return;
                    }

                    saveSettingsButton.disabled = true;
                    saveSettingsButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                });
            }

            // Add user form validation
            const addUserForm = document.getElementById('addUserForm');
            const addUserButton = document.getElementById('addUserButton');
            if (addUserForm && addUserButton) {
                addUserForm.addEventListener('submit', function (event) {
                    const username = document.getElementById('new_username').value;
                    const email = document.getElementById('new_email').value;
                    const password = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_new_password').value;
                    const displayName = document.getElementById('new_display_name').value;
                    const role = document.getElementById('role').value;

                    console.log('Selected role:', role); // Debug: Log selected role

                    // Validate username length
                    if (username.length < 3) {
                        event.preventDefault();
                        showToast('errorToast');
                        document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Username must be at least 3 characters long!';
                        return;
                    }

                    // Validate email
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        event.preventDefault();
                        showToast('errorToast');
                        document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a valid email address!';
                        return;
                    }

                    // Validate password
                    if (password.length < 8) {
                        event.preventDefault();
                        showToast('errorToast');
                        document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters long!';
                        return;
                    }
                    if (password !== confirmPassword) {
                        event.preventDefault();
                        showToast('errorToast');
                        document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
                        return;
                    }

                    // Validate display name
                    if (displayName.length < 1) {
                        event.preventDefault();
                        showToast('errorToast');
                        document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Display name cannot be empty!';
                        return;
                    }

                    // Validate role
                    if (!['admin', 'guest'].includes(role)) {
                        event.preventDefault();
                        showToast('errorToast');
                        document.querySelector('#errorToast .toast-body').innerHTML = '<i class="fas fa-exclamation-circle"></i> Please select a valid role!';
                        return;
                    }

                    addUserButton.disabled = true;
                    addUserButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                });
            }

            if (markAllCheckbox && checkboxes.length > 0) {
                updateButtonStates();

                markAllCheckbox.addEventListener('change', function () {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = markAllCheckbox.checked;
                    });
                    updateButtonStates();
                });

                function updateButtonStates() {
                    const checkedCount = document.querySelectorAll('.file-checkbox:checked').length;
                    if (deleteSelectedBtn) {
                        deleteSelectedBtn.disabled = checkedCount === 0;
                        deleteSelectedBtn.classList.toggle('active', checkedCount > 0);
                    }
                    if (downloadSelectedBtn) {
                        downloadSelectedBtn.disabled = checkedCount === 0;
                        downloadSelectedBtn.classList.toggle('active', checkedCount > 0);
                    }
                }

                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function () {
                        updateButtonStates();
                        const allChecked = document.querySelectorAll('.file-checkbox').length === document.querySelectorAll('.file-checkbox:checked').length;
                        markAllCheckbox.checked = allChecked;
                    });
                });

                if (deleteSelectedBtn && batchActionForm) {
                    deleteSelectedBtn.addEventListener('click', function (event) {
                        event.preventDefault();
                        if (confirm('Are you sure you want to delete the selected files?')) {
                            batchActionForm.action = 'batch_delete.php';
                            batchActionForm.submit();
                        }
                    });
                }

                if (downloadSelectedBtn && batchActionForm) {
                    downloadSelectedBtn.addEventListener('click', function (event) {
                        event.preventDefault();
                        if (confirm('Download selected files as a ZIP archive?')) {
                            batchActionForm.action = 'batch_download.php';
                            batchActionForm.submit();
                        }
                    });
                }
            }

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('toast') === 'success') {
                showToast('successToast');
                window.history.replaceState({}, document.title, window.location.pathname + (urlParams.get('page') ? '?page=' + urlParams.get('page') : ''));
            } else if (urlParams.get('toast') === 'error') {
                showToast('errorToast');
                window.history.replaceState({}, document.title, window.location.pathname + (urlParams.get('page') ? '?page=' + urlParams.get('page') : ''));
            } else if (urlParams.get('toast') === 'delete') {
                showToast('deleteToast');
                window.history.replaceState({}, document.title, window.location.pathname + (urlParams.get('page') ? '?page=' + urlParams.get('page') : ''));
            } else if (urlParams.get('toast') === 'settings') {
                showToast('settingsToast');
                window.history.replaceState({}, document.title, window.location.pathname + (urlParams.get('page') ? '?page=' + urlParams.get('page') : ''));
            } else if (urlParams.get('toast') === 'user_added') {
                showToast('userManagementToast');
                window.history.replaceState({}, document.title, window.location.pathname + (urlParams.get('page') ? '?page=' + urlParams.get('page') : ''));
            } else if (urlParams.get('toast') === 'user_deleted') {
                showToast('userDeletedToast');
                window.history.replaceState({}, document.title, window.location.pathname + (urlParams.get('page') ? '?page=' + urlParams.get('page') : ''));
            }
        });
    </script>
</body>
</html>