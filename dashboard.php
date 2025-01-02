<?php
session_start();
require 'db.php'; // Include the database connection

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy(); // Destroy the current session
    header("Location: login.php"); // Redirect to login page
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle adding a new user (admin only)
if ($_SESSION['is_admin'] && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT); // Hash the password
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    if (!empty($username) && !empty($_POST['password'])) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $username, $password, $is_admin);
        if ($stmt->execute()) {
            echo "<script>alert('User added successfully!');</script>";
        } else {
            echo "<script>alert('Error adding user: " . $conn->error . "');</script>";
        }
    } else {
        echo "<script>alert('Username and password are required.');</script>";
    }
    $stmt->close();
}

// Handle adding a new access request or entry
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['request_access']) && !$_SESSION['is_admin']) {
        // Normal user requesting access
        $url = trim($_POST['url']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $added_by = $_SESSION['username'];

        if (!empty($url) && !empty($username) && !empty($password)) {
            $stmt = $conn->prepare("INSERT INTO user_access_requests (url, username, password, added_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $url, $username, $password, $added_by);
            if ($stmt->execute()) {
                echo "<script>alert('Access request submitted successfully!');</script>";
            } else {
                echo "<script>alert('Error submitting access request: " . $conn->error . "');</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('All fields are required.');</script>";
        }
    } elseif (isset($_POST['add_access']) && $_SESSION['is_admin']) {
        // Admin directly adding access
        $url = trim($_POST['url']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $added_by = $_SESSION['username'];

        if (!empty($url) && !empty($username) && !empty($password)) {
            $stmt = $conn->prepare("INSERT INTO access (url, username, password, added_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $url, $username, $password, $added_by);
            if ($stmt->execute()) {
                echo "<script>alert('Access added successfully!');</script>";
            } else {
                echo "<script>alert('Error adding access: " . $conn->error . "');</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('All fields are required.');</script>";
        }
    }
}

// Handle admin accepting or declining access requests
if ($_SESSION['is_admin'] && $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['accept_access'])) {
        $id = intval($_POST['access_id']);
        // Move to access table
        $stmt = $conn->prepare("INSERT INTO access (url, username, password, added_by) SELECT url, username, password, added_by FROM user_access_requests WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Delete from requests
        $conn->query("DELETE FROM user_access_requests WHERE id = $id");
        echo "<script>alert('Access request accepted!');</script>";
    }

    if (isset($_POST['decline_access'])) {
        $id = intval($_POST['access_id']);
        // Delete the request
        $conn->query("DELETE FROM user_access_requests WHERE id = $id");
        echo "<script>alert('Access request declined!');</script>";
    }
}

// Handle file import from CSV
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']['tmp_name'])) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');

        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) === 3) {
                $url = $conn->real_escape_string($data[0]);
                $username = $conn->real_escape_string($data[1]);
                $password = $conn->real_escape_string($data[2]);
                $added_by = $_SESSION['username'];

                $stmt = $conn->prepare("INSERT INTO access (url, username, password, added_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $url, $username, $password, $added_by);
                $stmt->execute();
                $stmt->close();
            }
        }
        fclose($handle);
        echo "<script>alert('CSV file imported successfully!');</script>";
    } else {
        echo "<script>alert('Please select a file to upload.');</script>";
    }
}

// Handle exporting access entries to Excel (CSV)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['export_access'])) {
    header('Content-Type: text/csv; charset=utf-8');
    $filename = time() . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // fputcsv($output, ['URL', 'Username', 'Password', 'Added By']);

    $access_result = $conn->query("SELECT url, username, password FROM access");
    while ($row = $access_result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

// Fetch all access entries for display
$access_result = $conn->query("SELECT * FROM access");

// Fetch existing users
$user_result = $conn->query("SELECT * FROM users");

// Fetch user access requests for admin
$request_result = $_SESSION['is_admin'] ? $conn->query("SELECT * FROM user_access_requests") : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - SAMS</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <style>
        /* Additional CSS for tabs */
        .tab {
            cursor: pointer;
            padding: 10px;
            border: 1px solid #ccc;
            border-bottom: none;
            background-color: #f1f1f1;
            color:#ff0000;
            transition: background-color 0.3s;
        }

        .tab:hover {
            background-color: #ff0000;
            color: #FFFFFF
        }

        .tab.active {
            background-color:#ff0000;
            color: white;
        }

        .tab-content {
            display: none;
            padding: 10px;
            border: 1px solid #ccc;
            background-color: white;
        }

        .tab-content.active {
            display: block; /* Show the active tab content */
        }

        .request-actions {
            display: flex; 
            gap: 5px; 
        }
    </style>
</head>
<body>
    <center><h1>Shell Access Management System</h1></center>

    <!-- Logout Button -->
    <form method="POST" style="display: inline;">
        <input type="submit" name="logout" value="Logout" class="logout-button">
    </form>

    <div class="tabs">
        <?php if ($_SESSION['is_admin']): ?>
            <div class="tab active" onclick="openTab(event, 'AddUser')">Add User</div>
        <?php endif; ?>
        <div class="tab" onclick="openTab(event, 'AddAccess')">Add Access</div>
        <?php if ($_SESSION['is_admin']): ?>
            <div class="tab" onclick="openTab(event, 'UserAccesses')">User Accesses</div>
        <?php endif; ?>
    </div>

    <!-- Add User Form (Admin only) -->
    <?php if ($_SESSION['is_admin']): ?>
    <div id="AddUser" class="tab-content active">
        <h3>Add User</h3>
        <form method="POST">
            Username: <input type="text" name="username" required><br>
            Password: <input type="password" name="password" required><br>
            Admin: <input type="checkbox" name="is_admin"> (Check if admin)<br>
            <input type="submit" name="add_user" value="Add User">
        </form>
    </div>
    <?php endif; ?>

    <!-- Add Access Form -->
    <div id="AddAccess" class="tab-content">
        <h3>Add Access</h3>
        <form method="POST">
            URL: <input type="text" name="url" required><br>
            Username: <input type="text" name="username" required><br>
            Password: <input type="text" name="password" required><br>
            <input type="submit" name="<?php echo $_SESSION['is_admin'] ? 'add_access' : 'request_access'; ?>" value="<?php echo $_SESSION['is_admin'] ? 'Add Access' : 'Request Access'; ?>">
        </form>

        <!-- Import CSV Form -->
        <h3>Import CSV File</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <input type="submit" name="import_csv" value="Import CSV" class="logout-button">
        </form>
    </div>

    <?php if ($_SESSION['is_admin']): ?>
    <div id="UserAccesses" class="tab-content">
        <h3>User Access Requests</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>URL</th>
                <th>Username</th>
                <th>Password</th>
                <th>Added By</th>
                <th>Actions</th>
            </tr>
            <?php if ($request_result && $request_result->num_rows > 0): ?>
                <?php while ($row = $request_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']); ?></td>
                    <td><?= htmlspecialchars($row['url']); ?></td>
                    <td><?= htmlspecialchars($row['username']); ?></td>
                    <td><?= htmlspecialchars($row['password']); ?></td>
                    <td><?= htmlspecialchars($row['added_by']); ?></td>
                    <td class="request-actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="access_id" value="<?= htmlspecialchars($row['id']); ?>">
                            <input type="submit" name="accept_access" value="Accept">
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="access_id" value="<?= htmlspecialchars($row['id']); ?>">
                            <input type="submit" name="decline_access" value="Decline">
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No access requests found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>

    <h3>Access Entries</h3>

    <!-- Export Button -->
    <form method="POST" style="margin-bottom: 10px;">
        <input type="submit" name="export_access" value="Export Access Entries" class="logout-button">
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>URL</th>
            <th>Username</th>
            <th>Password</th>
            <th>Timestamp</th>
            <th>Added By</th>
        </tr>
        <?php if ($access_result && $access_result->num_rows > 0): ?>
            <?php while ($row = $access_result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']); ?></td>
                <td><?= htmlspecialchars($row['url']); ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td><?= htmlspecialchars($row['password']); ?></td>
                <td><?= htmlspecialchars($row['timestamp']); ?></td>
                <td><?= htmlspecialchars($row['added_by']); ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">No access entries found.</td>
            </tr>
        <?php endif; ?>
    </table>

    <?php if ($_SESSION['is_admin']): ?>
        <h3>Existing Users</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Admin</th>
            </tr>
            <?php if ($user_result && $user_result->num_rows > 0): ?>
            <?php while ($row = $user_result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']); ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td><?= $row['is_admin'] ? 'Yes' : 'No'; ?></td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr>
                <td colspan="3">No users found.</td>
            </tr>
            <?php endif; ?>
        </table>
    <?php endif; ?>
    
    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>