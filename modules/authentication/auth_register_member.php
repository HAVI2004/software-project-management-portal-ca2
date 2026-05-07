<?php
// Authentication Module: admin-controlled member registration.
include(__DIR__ . "/auth_session.php");
portal_start_session();
include(__DIR__ . "/../database/database_connection.php");
include(__DIR__ . "/../common/common_portal_helpers.php");

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user = $_SESSION['user'];

if ($user['role'] !== "admin") {
    header("Location: dashboard.php");
    exit();
}

ensure_task_progress_columns($conn);

$flash = portal_get_flash();
$msg = $flash['message'] ?? "";
$msgClass = $flash['type'] ?? "";
$teamMembers = [];

if (isset($_POST['signup'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
    $existingUser = false;
    // SQL: Check whether this email is already registered.
    $existingUserStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");

    if ($existingUserStmt) {
        $existingUserStmt->bind_param("s", $email);
        $existingUserStmt->execute();
        $existingUser = $existingUserStmt->get_result();
    }

    if ($name === "" || $email === "" || $password === "") {
        $msg = "Please fill all fields.";
        $msgClass = "error";
    } elseif ($existingUser && $existingUser->num_rows > 0) {
        $msg = "That email address is already in use.";
        $msgClass = "error";
    } else {
        // SQL: Insert the new team member account.
        $stmt = $conn->prepare("INSERT INTO users(name, email, password, role) VALUES(?, ?, ?, ?)");

        if ($stmt && $stmt->bind_param("ssss", $name, $email, $password, $role) && $stmt->execute()) {
            portal_set_flash("Team member created successfully.");
            portal_redirect("signup.php");
        } else {
            $msg = "Something went wrong while creating the account.";
            $msgClass = "error";
        }

        if ($stmt) {
            $stmt->close();
        }
    }

    if ($existingUserStmt) {
        $existingUserStmt->close();
    }
}

if (isset($_POST['remove_user'])) {
    $memberId = (int) ($_POST['member_id'] ?? 0);

    if ($memberId <= 0) {
        $msg = "Select a valid user to remove.";
        $msgClass = "error";
    } elseif ($memberId === (int) $user['id']) {
        $msg = "You cannot remove the account that is currently logged in.";
        $msgClass = "error";
    } else {
        // SQL: Check that the selected user exists before deleting.
        $memberCheck = $conn->query("SELECT id FROM users WHERE id = $memberId LIMIT 1");

        if (!$memberCheck || $memberCheck->num_rows === 0) {
            $msg = "That user account was not found.";
            $msgClass = "error";
        } else {
            // SQL: Remove tasks assigned to the user being deleted.
            $taskCleanup = $conn->query("DELETE FROM tasks WHERE assigned_to = $memberId");
            // SQL: Delete the selected user account.
            $memberDelete = $conn->query("DELETE FROM users WHERE id = $memberId");

            if ($taskCleanup && $memberDelete) {
                portal_set_flash("User removed successfully.");
                portal_redirect("signup.php");
            } else {
                $msg = "Unable to remove that user right now.";
                $msgClass = "error";
            }
        }
    }
}

// SQL: Fetch all team members with their assigned task count.
$teamResult = $conn->query("
    SELECT
        users.id,
        users.name,
        users.email,
        users.role,
        COUNT(tasks.task_id) AS assigned_task_total
    FROM users
    LEFT JOIN tasks ON tasks.assigned_to = users.id
    GROUP BY users.id, users.name, users.email, users.role
    ORDER BY users.role ASC, users.name ASC
");

if ($teamResult) {
    while ($row = $teamResult->fetch_assoc()) {
        $teamMembers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Add Team Members</title>
    <link rel="stylesheet" href="assets/css/style.css?v=22">
</head>
<body class="auth-page">
    <div class="page auth-wrap">
        <div class="dashboard-topbar page-toolbar">
            <div></div>
            <div class="dashboard-toolbar">
                <a class="button-link secondary" href="dashboard.php">Back to Dashboard</a>
                <button type="button" class="button-link secondary theme-toggle" id="themeToggle">Dark Mode</button>
            </div>
        </div>
        <div class="auth-shell">
            <section class="auth-side">
                <p class="portal-name">Project Admin Panel</p>
                <h1>Add Team Members</h1>
                <p>Create admin and user accounts so project members can log in and work on assigned tasks.</p>
                <div class="auth-badge">Admin Only Access</div>
                <div class="hero-chip-row compact">
                    <span class="hero-chip">Access Control</span>
                    <span class="hero-chip">Admin + User Roles</span>
                </div>
            </section>

            <form method="POST">
                <p class="portal-name">Software Project Management Portal</p>
                <h2>Create Account</h2>
                <p class="muted">Only admins can add team members and control portal access.</p>

                <?php if ($msg) { ?>
                    <div class="alert <?php echo $msgClass; ?>"><?php echo $msg; ?></div>
                <?php } ?>

                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" name="signup">Create Member</button>
                </div>
            </form>
        </div>

        <div class="task-list">
            <div class="task-card">
                <h3>Current Team Members</h3>
                <?php if (count($teamMembers) > 0) { ?>
                    <?php foreach ($teamMembers as $member) { ?>
                        <div class="task-card-stack">
                            <div class="detail-grid">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($member['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                                <p><strong>Role:</strong> <?php echo htmlspecialchars(portal_role_label($member['role'])); ?></p>
                                <p><strong>Assigned Tasks:</strong> <?php echo (int) $member['assigned_task_total']; ?></p>
                            </div>
                            <?php if ((int) $member['id'] !== (int) $user['id']) { ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="member_id" value="<?php echo (int) $member['id']; ?>">
                                    <button type="submit" name="remove_user" class="button-link secondary">Remove User</button>
                                </form>
                            <?php } ?>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p>No team members found.</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php echo portal_theme_script(); ?>
</body>
</html>
