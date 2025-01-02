<?php
// Include database connection
include('db.php');

// Start the session
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['username']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php"); // Redirect to login if the user is not an admin
    exit();
}

// Handle deletion of users
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    
    // Ensure the admin does not delete themselves
    if ($user_id != $_SESSION['user_id']) {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
    }
}

// Handle deletion of access records
if (isset($_GET['delete_access'])) {
    $access_id = $_GET['delete_access'];
    
    $sql = "DELETE FROM access WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $access_id);
    $stmt->execute();
}

// Fetch users
$sql_users = "SELECT * FROM users";
$result_users = $conn->query($sql_users);

// Fetch access records
$sql_access = "SELECT * FROM access";
$result_access = $conn->query($sql_access);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Admin Dashboard</h1>
    <a href="logout.php" class="logout-button">Logout</a>
    
    <div class="tabs">
        <div class="tab active" id="users-tab">Users</div>
        <div class="tab" id="access-tab">Access Records</div>
    </div>

    <!-- Users Table -->
    <div class="tab-content" id="users-content">
        <h2>Users</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Admin Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $result_users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo $user['is_admin'] ? 'Admin' : 'User'; ?></td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): // Prevent admin from deleting themselves ?>
                                <a href="?delete_user=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                            <?php else: ?>
                                Admin cannot delete themselves.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Access Records Table -->
    <div class="tab-content" id="access-content" style="display:none;">
        <h2>Access Records</h2>
        <table>
            <thead>
                <tr>
                    <th>URL</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Added By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($access = $result_access->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($access['url']); ?></td>
                        <td><?php echo htmlspecialchars($access['username']); ?></td>
                        <td><?php echo htmlspecialchars($access['password']); ?></td>
                        <td><?php echo htmlspecialchars($access['added_by']); ?></td>
                        <td><a href="?delete_access=<?php echo $access['id']; ?>" onclick="return confirm('Are you sure you want to delete this access record?');">Delete</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Tab switch functionality
        document.getElementById('users-tab').addEventListener('click', function() {
            document.getElementById('users-content').style.display = 'block';
            document.getElementById('access-content').style.display = 'none';
            document.getElementById('users-tab').classList.add('active');
            document.getElementById('access-tab').classList.remove('active');
        });

        document.getElementById('access-tab').addEventListener('click', function() {
            document.getElementById('users-content').style.display = 'none';
            document.getElementById('access-content').style.display = 'block';
            document.getElementById('access-tab').classList.add('active');
            document.getElementById('users-tab').classList.remove('active');
        });
    </script>
</body>
</html>
