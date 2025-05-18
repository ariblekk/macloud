<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$upload_dir = 'Uploads/';
$allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$display_name = $_SESSION['display_name'] ?? 'Anomali';

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $files = $_FILES['files'];
    $caption = $_POST['caption'] ?? '';
    $tags = isset($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [];
    $tags = array_filter($tags); // Hapus tag kosong
    $upload_date = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');

    $success = false;
    $error_message = '';

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $error_message = 'Gagal mengunggah file: ' . $files['name'][$i];
            continue;
        }

        $file_name = basename($files['name'][$i]);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $file_tmp = $files['tmp_name'][$i];

        if (!in_array($file_ext, $allowed_types)) {
            $error_message = 'Tipe file tidak valid: ' . $file_name;
            continue;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM images WHERE filename = ?");
            $stmt->execute([$file_name]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = 'File sudah ada di database: ' . $file_name;
                continue;
            }
        } catch (PDOException $e) {
            $error_message = 'Kesalahan database saat memeriksa file: ' . $e->getMessage();
            continue;
        }

        $base_name = pathinfo($file_name, PATHINFO_FILENAME);
        $new_file_name = $file_name;
        $destination = $upload_dir . $new_file_name;
        $counter = 1;

        while (file_exists($destination)) {
            $new_file_name = $base_name . '_' . $counter . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;
            $counter++;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM images WHERE filename = ?");
            $stmt->execute([$new_file_name]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = 'File sudah ada di database: ' . $new_file_name;
                continue 2;
            }
        }

        if (move_uploaded_file($file_tmp, $destination)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO images (filename, caption, uploader, upload_date, file_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$new_file_name, htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'), $display_name, $upload_date, $destination]);
                $image_id = $pdo->lastInsertId();

                // Proses tag
                if (!empty($tags)) {
                    foreach ($tags as $tag) {
                        if (empty($tag) || strlen($tag) > 50) continue;
                        $tag = htmlspecialchars(strtolower($tag), ENT_QUOTES, 'UTF-8');
                        try {
                            $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                            $stmt->execute([$tag]);
                            $tag_id = $stmt->fetchColumn();
                            if (!$tag_id) {
                                $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                                $stmt->execute([$tag]);
                                $tag_id = $pdo->lastInsertId();
                            }
                            $stmt = $pdo->prepare("INSERT INTO image_tags (image_id, tag_id) VALUES (?, ?)");
                            $stmt->execute([$image_id, $tag_id]);
                        } catch (PDOException $e) {
                            $error_message = 'Gagal menambahkan tag: ' . $e->getMessage();
                        }
                    }
                }

                $success = true;
            } catch (PDOException $e) {
                $error_message = 'Kesalahan database: ' . $e->getMessage();
                unlink($destination);
            }
        } else {
            $error_message = 'Gagal memindahkan file yang diunggah: ' . $file_name;
        }
    }

    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $query = $success ? 'toast=success' : 'toast=error&message=' . urlencode($error_message);
    if ($current_page > 1) {
        $query .= '&page=' . $current_page;
    }
    header("Location: gallery.php?$query");
    exit;
} else {
    header("Location: gallery.php?toast=error&message=" . urlencode("Tidak ada file yang dipilih"));
    exit;
}
?>