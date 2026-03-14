<?php
header('Content-Type: application/json');

// Include database connection
include 'db-connect.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Log received data for debugging
    error_log("Signup attempt: " . print_r($_POST, true));

    // Get form data
    $full_name = $_POST['fullname'] ?? '';
    $username = $_POST['username'] ?? '';
    $email    = $_POST['email'] ?? '';
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $phone_number = $_POST['phone'] ?? '';

    // Validation
    $errors = [];

    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($role)) $errors[] = "Role is required";
    if (empty($phone_number)) $errors[] = "Phone number is required";

    // Validate email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Validate password strength
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
    }

    // If errors, return them
    if (!empty($errors)) {
        echo json_encode([
            'status' => 'error',
            'message' => implode(". ", $errors)
        ]);
        exit;
    }

    try {
        // Check if user exists
        $check_sql = "SELECT id FROM users WHERE email = ? OR username = ? OR phone_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $email, $username, $phone_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email, username or phone number already exists!'
            ]);
            exit;
        }

        // Generate UUID for id
        $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

        // ✅ BCRYPT HASHING - This is the key part!
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Log the hash for debugging (remove in production)
        error_log("Password hash created: " . $hashed_password);

        // Determine verification status
        $is_verified = ($role == 'client') ? 1 : 0; // Clients auto-verified, drivers need KYC

        // Insert user with proper column names
        $sql = "INSERT INTO users (
                    id, 
                    full_name, 
                    username, 
                    email, 
                    password_hash, 
                    role, 
                    phone_number, 
                    is_verified, 
                    is_active, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssi",
            $id,
            $full_name,
            $username,
            $email,
            $hashed_password,
            $role,
            $phone_number,
            $is_verified
        );

        if ($stmt->execute()) {

            // Auto login after registration
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['fullname'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            $_SESSION['logged_in'] = true;

            // Determine redirect page - ADD /SPEEDLY/ to the paths
            if ($role == 'driver') {
                $redirect = '/SPEEDLY/verify_otp.php';
            } else {
                $redirect = '/SPEEDLY/verify_otp.php';
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Registration successful! Welcome to Speedly!',
                'redirect' => $redirect,
                'user' => [
                    'id' => $id,
                    'name' => $full_name,
                    'role' => $role
                ]
            ]);
        } else {
            throw new Exception($stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Signup error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Registration failed: ' . $e->getMessage()
        ]);
    }

    $check_stmt->close();
    $conn->close();
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}  

