<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$upload_dir = 'Uploads/';

if (isset($_GET['file'])) {
    $file = basename($_GET['file']);

    try {
        $stmt = $pdo->prepare("SELECT file_path FROM images WHERE filename = ?");
        $stmt->execute([$file]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($image && file_exists($image['file_path'])) {
            $file_path = $image['file_path'];
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if ($_SESSION['role'] == 'guest') {
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $image = match ($ext) {
                        'jpg', 'jpeg' => imagecreatefromjpeg($file_path),
                        'png' => imagecreatefrompng($file_path),
                        'gif' => imagecreatefromgif($file_path),
                        default => null
                    };

                    if (!$image) {
                        header("Location: gallery.php?toast=error&message=Unsupported file format.");
                        exit;
                    }

                    $font = __DIR__ . '/fonts/arial.ttf';
                    $font_size = 25;
                    $text = '@ariharyanto_';
                    $text_color = imagecolorallocate($image, 255, 255, 255);
                    $shadow_color = imagecolorallocate($image, 0, 0, 0);

                    $bbox = imagettfbbox($font_size, 0, $font, $text);
                    $text_width = $bbox[2] - $bbox[0];
                    $x = (imagesx($image) - $text_width) / 2;
                    $y = imagesy($image) - 30;

                    imagettftext($image, $font_size, 0, $x + 2, $y + 2, $shadow_color, $font, $text);
                    imagettftext($image, $font_size, 0, $x, $y, $text_color, $font, $text);

                    ob_start();
                    match ($ext) {
                        'jpg', 'jpeg' => imagejpeg($image, null, 90),
                        'png' => imagepng($image),
                        'gif' => imagegif($image)
                    };
                    $image_data = ob_get_clean();
                    imagedestroy($image);

                    header('Content-Type: image/' . $ext);
                    header('Content-Disposition: attachment; filename="' . $file . '"');
                    header('Content-Length: ' . strlen($image_data));
                    echo $image_data;
                    exit;
                } else {
                    header("Location: gallery.php?toast=error&message=Unsupported file format.");
                }
            } else {
                header('Content-Type: ' . mime_content_type($file_path));
                header('Content-Disposition: attachment; filename="' . $file . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit;
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