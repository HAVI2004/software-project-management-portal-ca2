<?php
// Task Management Module: user task list.
include(__DIR__ . "/../authentication/auth_session.php");
portal_start_session();
include(__DIR__ . "/../database/database_connection.php");
include(__DIR__ . "/../common/common_portal_helpers.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['user']['role'] !== "user") {
    header("Location: ../dashboard.php");
    exit();
}

ensure_task_progress_columns($conn);
ensure_team_tables($conn);

$userId = (int) $_SESSION['user']['id'];
// SQL: Fetch tasks assigned to the logged-in user.
$result = $conn->query("
    SELECT tasks.*, projects.project_name, teams.team_name
    FROM tasks
    LEFT JOIN projects ON tasks.project_id = projects.project_id
    LEFT JOIN teams ON tasks.team_id = teams.team_id
    WHERE tasks.assigned_to = $userId
    ORDER BY tasks.deadline ASC, tasks.task_id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | View Tasks</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=22">
</head>
<body>
    <div class="page">
        <div class="dashboard-topbar page-toolbar">
            <div></div>
            <div class="dashboard-toolbar">
                <a class="button-link secondary" href="../dashboard.php">Back to Dashboard</a>
                <button type="button" class="button-link secondary theme-toggle" id="themeToggle">Dark Mode</button>
            </div>
        </div>
        <div class="hero">
            <p class="portal-name">Software Project Management Portal</p>
            <h2>Your Tasks</h2>
            <p class="muted">Track the tasks assigned to you together with deadlines, status, and submitted progress.</p>
        </div>

        <div class="task-list">
            <?php if ($result && $result->num_rows > 0) { ?>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <div class="task-card">
                        <h3><?php echo htmlspecialchars($row['task_name']); ?></h3>
                        <div class="detail-grid">
                            <p><strong>Task ID:</strong> <?php echo htmlspecialchars($row['task_id']); ?></p>
                            <p><strong>Project:</strong> <?php echo htmlspecialchars($row['project_name'] ? $row['project_name'] : "Project #" . $row['project_id']); ?></p>
                            <p><strong>Team:</strong> <?php echo htmlspecialchars($row['team_name'] ? $row['team_name'] : "Individual Task"); ?></p>
                            <p><strong>Deadline:</strong> <?php echo htmlspecialchars($row['deadline']); ?></p>
                            <p><strong>Deadline Status:</strong> <?php echo htmlspecialchars(portal_deadline_state($row['deadline'])); ?></p>
                        </div>
                        <div class="progress-meter"><span style="width: <?php echo (int) $row['progress_percent']; ?>%"></span></div>
                        <p><strong>Completion:</strong> <?php echo htmlspecialchars(portal_progress_label($row['progress_percent'])); ?></p>
                        <p><strong>Message from Admin:</strong> <?php echo htmlspecialchars($row['admin_message'] ? $row['admin_message'] : "No message from admin."); ?></p>
                        <p><strong>Progress Note:</strong> <?php echo htmlspecialchars($row['progress_note'] ? $row['progress_note'] : "No progress submitted yet."); ?></p>
                        <p><strong>Your Message:</strong> <?php echo htmlspecialchars($row['user_message'] ? $row['user_message'] : "No message sent to admin."); ?></p>
                        <p><strong>Document:</strong>
                            <?php if (!empty($row['attachment_path'])) { ?>
                                <a href="../<?php echo htmlspecialchars($row['attachment_path']); ?>" target="_blank">View Uploaded File</a>
                            <?php } else { ?>
                                No document uploaded.
                            <?php } ?>
                        </p>
                        <span class="status-badge <?php echo portal_status_class($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="task-card">
                    <p>No tasks assigned yet.</p>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php echo portal_theme_script(); ?>
    <?php echo portal_auto_logout_script("../auto_logout.php"); ?>
</body>
</html>
