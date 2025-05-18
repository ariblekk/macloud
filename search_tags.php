<?php
session_start();
include '../service/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tag = trim($_POST['tag'] ?? '');
$items_per_page = 12;
$page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$offset = ($page - 1) * $items_per_page;

try {
    // Count total images
    $count_query = "
        SELECT COUNT(DISTINCT i.id)
        FROM images i
        " . ($tag ? "JOIN image_tags it ON i.id = it.image_id JOIN tags t ON it.tag_id = t.id WHERE t.name LIKE :tag" : "");
    $stmt = $pdo->prepare($count_query);
    if ($tag) $stmt->bindValue(':tag', "%$tag%");
    $stmt->execute();
    $total_files = $stmt->fetchColumn();

    // Fetch images
    $query = "
        SELECT i.*, GROUP_CONCAT(t.name) as tags
        FROM images i
        LEFT JOIN image_tags it ON i.id = it.image_id
        LEFT JOIN tags t ON it.tag_id = t.id
        " . ($tag ? "WHERE t.name LIKE :tag" : "") . "
        GROUP BY i.id
        ORDER BY i.upload_date DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($query);
    if ($tag) $stmt->bindValue(':tag', "%$tag%");
    $stmt->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'images' => $images,
        'total' => $total_files,
        'page' => $page,
        'total_pages' => max(1, ceil($total_files / $items_per_page))
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>