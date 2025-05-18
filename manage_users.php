<?php
session_start();
include '../service/db_connect.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle user addition
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $display_name = trim($_POST['display_name'] ?? '');
        $role = $_POST['role'] ?? '';

        // Debug: Log received role
        error_log("Received role: $role");

        // Validation
        $errors = [];
        if (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        if (empty($display_name)) {
            $errors[] = "Display name cannot be empty.";
        }
        if (!in_array($role, ['admin', 'guest'])) {
            $errors[] = "Invalid role selected: $role";
        }

        // Check for duplicate username or email
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already exists.";
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already exists.";
        }

        if (empty($errors)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, display_name, role) VALUES (?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$username, $email, $hashed_password, $display_name, $role]);
                error_log("User added: $username, Role: $role");
                header("Location: gallery.php?toast=user_added");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Failed to add user: " . $e->getMessage();
                error_log("Database error: " . $e->getMessage());
            }
        }

        // Redirect with errors
        if (!empty($errors)) {
            $error_message = urlencode(implode(" ", $errors));
            error_log("Errors: " . implode(", ", $errors));
            header("Location: gallery.php?toast=error&message=$error_message");
            exit;
        }
    }
    // Handle user deletion
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['username'])) {
        $username_to_delete = trim($_POST['username']);

        // Prevent admin from deleting their own account
        if ($username_to_delete === $_SESSION['user']) {
            $error_message = urlencode("You cannot delete your own account.");
            header("Location: gallery.php?toast=error&message=$error_message");
            exit;
        }

        // Check if user exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username_to_delete]);
        if ($stmt->fetchColumn() == 0) {
            $error_message = urlencode("User does not exist.");
            header("Location: gallery.php?toast=error&message=$error_message");
            exit;
        }

        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        try {
            $stmt->execute([$username_to_delete]);
            error_log("User deleted: $username_to_delete");
            header("Location: gallery.php?toast=user_deleted");
            exit;
        } catch (PDOException $e) {
            $error_message = urlencode("Failed to delete user: " . $e->getMessage());
            error_log("Database error: " . $e->getMessage());
            header("Location: gallery.php?toast=error&message=$error_message");
            exit;
        }
    }
}

header("Location: gallery.php");
exit;
?>