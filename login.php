<?php
session_start();
require 'db.php'; // Include the database connection

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the username and password from POST
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Prepare the SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, password, is_admin FROM users WHERE username = ?");
    $stmt->bind_param("s", $username); // Bind the username parameter

    // Execute the statement
    $stmt->execute();
    $stmt->store_result(); // Store the result

    // Check if the username exists
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $is_admin);
        $stmt->fetch(); // Fetch the results

        // Verify the password
        if (password_verify($password, $hashed_password)) {
            // Password is correct, set the session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = $is_admin;

            // Redirect to the dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            // Invalid password
            echo "<script>alert('Invalid username or password.');</script>";
        }
    } else {
        // Invalid username
        echo "<script>alert('Invalid username or password.');</script>";
    }

    $stmt->close(); // Close the statement
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <h2>Login</h2>
    <form method="POST">
        Username: <input type="text" name="username" required><br>
        Password: <input type="password" name="password" required><br>
        <input type="submit" value="Login">
    </form>
</body>
</html>