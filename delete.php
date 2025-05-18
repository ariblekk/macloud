<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$upload_dir = 'Uploads/';

if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $file_path = $upload_dir . $file;

    try {
        $stmt = $pdo->prepare("SELECT file_path FROM images WHERE filename = ?");
        $stmt->execute([$file]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($image && file_exists($image['file_path'])) {
            if (unlink($image['file_path'])) {
                $stmt = $pdo->prepare("DELETE FROM images WHERE filename = ?");
                $stmt->execute([$file]);
                header("Location: gallery.php?toast=delete");
            } else {
                header("Location: gallery.php?toast=error&message=Failed to delete file.");
            }
        } else {
            header("Location: gallery.php?toast=error&message=File not found.");
        }
    } catch (PDOException $e) {
        header("Location: gallery.php?toast=error&message=Database error: " . urlencode($e->getMessage()));
    }
} else {
    header("Location: gallery.php?toast=error&message=Invalid request.");
}
exit;
?>