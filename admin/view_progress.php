<?php
// Progress Tracking Module: admin progress reports and deadlines.
include(__DIR__ . "/../modules/authentication/auth_session.php");
portal_start_session();
include(__DIR__ . "/../modules/database/database_connection.php");
include(__DIR__ . "/../modules/common/common_portal_helpers.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== "admin") {
    header("Location: ../index.php");
    exit();
}

ensure_task_progress_columns($conn);
ensure_team_tables($conn);

// SQL: Fetch all tasks with user, project, team, and progress details.
$result = $conn->query("
    SELECT tasks.task_id, tasks.task_name, tasks.status, tasks.deadline, tasks.progress_percent, tasks.progress_note, tasks.admin_message, tasks.user_message, tasks.attachment_path,
           users.name AS user_name,
           projects.project_name,
           teams.team_name,
           leaders.name AS leader_name
    FROM tasks
    LEFT JOIN users ON tasks.assigned_to = users.id
    LEFT JOIN projects ON tasks.project_id = projects.project_id
    LEFT JOIN teams ON tasks.team_id = teams.team_id
    LEFT JOIN users leaders ON leaders.id = teams.leader_id
    ORDER BY tasks.deadline ASC, tasks.task_id DESC
");

// SQL: Calculate project-wise task count, average progress, and overdue tasks.
$projectSummary = $conn->query("
    SELECT
        projects.project_id,
        projects.project_name,
        COUNT(tasks.task_id) AS task_total,
        ROUND(AVG(COALESCE(tasks.progress_percent, 0))) AS avg_progress,
        SUM(CASE WHEN tasks.deadline < CURDATE() AND tasks.status <> 'Completed' THEN 1 ELSE 0 END) AS overdue_total
    FROM projects
    LEFT JOIN tasks ON tasks.project_id = projects.project_id
    GROUP BY projects.project_id, projects.project_name
    ORDER BY projects.project_name ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Project Tracking</title>
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
            <h2>Project Tracking</h2>
            <p class="muted">Track deadlines, task status, and completion percentages submitted by users.</p>
        </div>

        <div class="dashboard-grid">
            <?php if ($projectSummary && $projectSummary->num_rows > 0) { ?>
                <?php while ($summary = $projectSummary->fetch_assoc()) { ?>
                    <div class="dashboard-card module-card active">
                        <div class="module-card-top">
                            <span class="module-badge available">Project Summary</span>
                        </div>
                        <h3><?php echo htmlspecialchars($summary['project_name']); ?></h3>
                        <p class="muted"><?php echo (int) $summary['task_total']; ?> tasks linked to this project.</p>
                        <div class="progress-meter"><span style="width: <?php echo (int) ($summary['avg_progress'] ?? 0); ?>%"></span></div>
                        <p><?php echo htmlspecialchars(portal_progress_label($summary['avg_progress'] ?? 0)); ?></p>
                        <p class="muted">Calculated from user task completion percentages.</p>
                        <p class="muted"><?php echo (int) $summary['overdue_total']; ?> overdue tasks.</p>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="dashboard-card module-card active">
                    <h3>No project tracking data</h3>
                    <p class="muted">Create projects and assign tasks to see completion summaries here.</p>
                </div>
            <?php } ?>
        </div>

        <div class="task-list">
            <?php if ($result && $result->num_rows > 0) { ?>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <div class="task-card">
                        <h3><?php echo htmlspecialchars($row['task_name']); ?></h3>
                        <div class="detail-grid">
                            <p><strong>Task ID:</strong> <?php echo htmlspecialchars($row['task_id']); ?></p>
                            <p><strong>User:</strong> <?php echo htmlspecialchars($row['user_name'] ? $row['user_name'] : "Unknown User"); ?></p>
                            <p><strong>Project:</strong> <?php echo htmlspecialchars($row['project_name'] ? $row['project_name'] : "Unlinked Project"); ?></p>
                            <p><strong>Team:</strong> <?php echo htmlspecialchars($row['team_name'] ? $row['team_name'] : "Individual Task"); ?></p>
                            <p><strong>Team Leader:</strong> <?php echo htmlspecialchars($row['leader_name'] ? $row['leader_name'] : "Not assigned"); ?></p>
                            <p><strong>Deadline:</strong> <?php echo htmlspecialchars($row['deadline']); ?></p>
                            <p><strong>Deadline Status:</strong> <?php echo htmlspecialchars(portal_deadline_state($row['deadline'])); ?></p>
                        </div>
                        <div class="progress-meter"><span style="width: <?php echo (int) $row['progress_percent']; ?>%"></span></div>
                        <p><strong>Completion:</strong> <?php echo htmlspecialchars(portal_progress_label($row['progress_percent'])); ?></p>
                        <p><strong>Message to User:</strong> <?php echo htmlspecialchars($row['admin_message'] ? $row['admin_message'] : "No admin message added."); ?></p>
                        <p><strong>Progress Note:</strong> <?php echo htmlspecialchars($row['progress_note'] ? $row['progress_note'] : "No update submitted yet."); ?></p>
                        <p><strong>User Message:</strong> <?php echo htmlspecialchars($row['user_message'] ? $row['user_message'] : "No message from user."); ?></p>
                        <p><strong>Document:</strong>
                            <?php if (!empty($row['attachment_path'])) { ?>
                                <a href="../<?php echo htmlspecialchars($row['attachment_path']); ?>" target="_blank">Open Uploaded File</a>
                            <?php } else { ?>
                                No document uploaded.
                            <?php } ?>
                        </p>
                        <span class="status-badge <?php echo portal_status_class($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="task-card">
                    <p>No task progress is available yet.</p>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php echo portal_theme_script(); ?>
    <?php echo portal_auto_logout_script("../auto_logout.php"); ?>
</body>
</html>
