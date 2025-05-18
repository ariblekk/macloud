<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$upload_dir = 'Uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['files'])) {
    $files = $_POST['files'];
    $success = false;

    try {
        $pdo->beginTransaction();
        foreach ($files as $file) {
            $file = basename($file);
            $stmt = $pdo->prepare("SELECT file_path FROM images WHERE filename = ?");
            $stmt->execute([$file]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image && file_exists($image['file_path'])) {
                if (unlink($image['file_path'])) {
                    $stmt = $pdo->prepare("DELETE FROM images WHERE filename = ?");
                    $stmt->execute([$file]);
                    $success = true;
                }
            }
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: gallery.php?toast=error&message=Database error: " . urlencode($e->getMessage()));
        exit;
    }

    if ($success) {
        header("Location: gallery.php?toast=delete");
    } else {
        header("Location: gallery.php?toast=error&message=Failed to delete some files.");
    }
} else {
    header("Location: gallery.php?toast=error&message=Invalid request.");
}
exit;
?>