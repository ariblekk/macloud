<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$upload_dir = 'Uploads/';
$allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['files']) && !empty($_POST['files'])) {
    $files = $_POST['files'];
    $temp_dir = sys_get_temp_dir() . '/macloud_' . uniqid();
    if (!mkdir($temp_dir, 0777, true)) {
        error_log("Failed to create temporary directory: $temp_dir");
        header("Location: gallery.php?toast=error&message=Failed to create temporary directory.");
        exit;
    }

    $zip_file = $temp_dir . '/selected_images.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        error_log("Failed to create ZIP archive: $zip_file");
        rmdir($temp_dir);
        header("Location: gallery.php?toast=error&message=Failed to create ZIP archive.");
        exit;
    }

    $success = false;
    try {
        $placeholders = implode(',', array_fill(0, count($files), '?'));
        $stmt = $pdo->prepare("SELECT filename, file_path FROM images WHERE filename IN ($placeholders)");
        $stmt->execute($files);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($images as $image) {
            $file = $image['filename'];
            $file_path = $image['file_path'];
            if (file_exists($file_path) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $allowed_types)) {
                if ($zip->addFile($file_path, $file)) {
                    $success = true;
                } else {
                    error_log("Failed to add file to ZIP: $file_path");
                }
            } else {
                error_log("Invalid or missing file: $file_path");
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }

    $zip->close();

    if (!$success) {
        error_log("No valid files were added to ZIP");
        unlink($zip_file);
        rmdir($temp_dir);
        header("Location: gallery.php?toast=error&message=No valid files selected for download.");
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="selected_images.zip"');
    header('Content-Length: ' . filesize($zip_file));
    readfile($zip_file);

    unlink($zip_file);
    rmdir($temp_dir);
    exit;
} else {
    error_log("Invalid request or no files selected in batch_download.php");
    header("Location: gallery.php?toast=error&message=Invalid request or no files selected.");
    exit;
}
?>