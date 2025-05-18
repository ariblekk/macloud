<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $current_username = $_SESSION['user'];

    $errors = [];

    // Validate username
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    } elseif (strlen($username) > 50) {
        $errors[] = "Username must be 50 characters or less.";
    } else {
        // Check if new username is taken (excluding current user)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND username != ?");
        $stmt->execute([$username, $current_username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username is already taken.";
        }
    }

    // Validate password (if provided)
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
    }

    // Validate display name
    if (empty($display_name)) {
        $errors[] = "Display name is required.";
    } elseif (strlen($display_name) > 100) {
        $errors[] = "Display name must be 100 characters or less.";
    }

    if (empty($errors)) {
        try {
            // Update user data
            $updates = [];
            $params = [];

            // Username
            if ($username !== $current_username) {
                $updates[] = "username = ?";
                $params[] = $username;
            }

            // Password (only if provided)
            if (!empty($password)) {
                $updates[] = "password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            // Display name
            $updates[] = "display_name = ?";
            $params[] = $display_name;

            // Add current username to params for WHERE clause
            $params[] = $current_username;

            // Build and execute query
            if (!empty($updates)) {
                $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE username = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Update session variables
                $_SESSION['user'] = $username;
                $_SESSION['display_name'] = $display_name;
            }

            // Redirect with success message
            header("Location: gallery.php?toast=settings");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    }

    // Redirect with errors
    $error_message = urlencode(implode(" ", $errors));
    header("Location: gallery.php?toast=error&message=$error_message");
    exit;
} else {
    header("Location: gallery.php?toast=error&message=" . urlencode("Invalid request."));
    exit;
}
?>