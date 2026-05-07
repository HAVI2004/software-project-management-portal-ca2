<?php
// Dashboard Module: role-aware dashboard landing page.
include(__DIR__ . "/../authentication/auth_session.php");
portal_start_session();
include(__DIR__ . "/../database/database_connection.php");
include(__DIR__ . "/../common/common_portal_helpers.php");

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user = $_SESSION['user'];
ensure_task_progress_columns($conn);
ensure_team_tables($conn);
ensure_team_messages_table($conn);

$roleLabel = portal_role_label($user['role']);
$projectCount = portal_dashboard_count($conn, "projects");
$userCount = portal_dashboard_count($conn, "users");
$teamCount = portal_dashboard_count($conn, "teams");
$taskCount = portal_dashboard_count($conn, "tasks");
$userTaskCount = 0;
$userActiveTaskCount = 0;
$userCompletedTaskCount = 0;
$orphanTaskCount = 0;
// SQL: Count tasks linked to missing projects.
$orphanTaskResult = $conn->query("
    SELECT COUNT(*) AS total
    FROM tasks
    LEFT JOIN projects ON tasks.project_id = projects.project_id
    WHERE tasks.project_id IS NOT NULL AND projects.project_id IS NULL
");
if ($orphanTaskResult && $orphanTaskResult->num_rows > 0) {
    $orphanTaskRow = $orphanTaskResult->fetch_assoc();
    $orphanTaskCount = (int) ($orphanTaskRow['total'] ?? 0);
}
$modules = [];
$focusLine = $user['role'] == "admin"
    ? "Coordinate teams, launch projects, and keep delivery moving."
    : "Stay on top of assigned work and share progress with clarity.";
$userId = (int) $user['id'];
// SQL: Count this user's total, completed, and active tasks.
$userTaskResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_total,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS active_total
    FROM tasks
    WHERE assigned_to = $userId
");

if ($userTaskResult && $userTaskResult->num_rows > 0) {
    $userTaskRow = $userTaskResult->fetch_assoc();
    $userTaskCount = (int) ($userTaskRow['total'] ?? 0);
    $userCompletedTaskCount = (int) ($userTaskRow['completed_total'] ?? 0);
    $userActiveTaskCount = (int) ($userTaskRow['active_total'] ?? 0);
}

$heroMetric = $user['role'] == "admin" ? $projectCount . " Projects" : $userTaskCount . " My Tasks";
$activeTaskCount = 0;
$completedTaskCount = 0;
$messageNotifications = [];

// SQL: Count completed and in-progress tasks for dashboard stats.
$taskStatusResult = $conn->query("
    SELECT
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_total,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS active_total
    FROM tasks
");

if ($taskStatusResult && $taskStatusResult->num_rows > 0) {
    $taskStatusRow = $taskStatusResult->fetch_assoc();
    $completedTaskCount = (int) ($taskStatusRow['completed_total'] ?? 0);
    $activeTaskCount = (int) ($taskStatusRow['active_total'] ?? 0);
}

if ($user['role'] == "admin") {
    // SQL: Fetch latest team messages for admin notifications.
    $notificationResult = $conn->query("
        SELECT team_messages.message_id, team_messages.team_id, team_messages.message_text, team_messages.created_at,
               users.name AS sender_name, users.role AS sender_role, teams.team_name
        FROM team_messages
        INNER JOIN users ON users.id = team_messages.sender_id
        INNER JOIN teams ON teams.team_id = team_messages.team_id
        WHERE team_messages.sender_id <> $userId
        ORDER BY team_messages.created_at DESC, team_messages.message_id DESC
        LIMIT 5
    ");
} else {
    // SQL: Fetch latest team messages visible to this user.
    $notificationResult = $conn->query("
        SELECT DISTINCT team_messages.message_id, team_messages.team_id, team_messages.message_text, team_messages.created_at,
               users.name AS sender_name, users.role AS sender_role, teams.team_name
        FROM team_messages
        INNER JOIN users ON users.id = team_messages.sender_id
        INNER JOIN teams ON teams.team_id = team_messages.team_id
        LEFT JOIN team_members ON team_members.team_id = teams.team_id
        WHERE team_messages.sender_id <> $userId
          AND (team_members.user_id = $userId OR teams.leader_id = $userId)
        ORDER BY team_messages.created_at DESC, team_messages.message_id DESC
        LIMIT 5
    ");
}

if ($notificationResult) {
    while ($notification = $notificationResult->fetch_assoc()) {
        $messageNotifications[] = $notification;
    }
}

if ($user['role'] == "admin") {
    $modules = [
        [
            "title" => "User Management",
            "eyebrow" => "Admin Module",
            "tone" => "admin",
            "description" => "Create user accounts, remove users, and manage portal access.",
            "status" => "Available",
            "features" => ["Add users", "Remove users", "Role control"],
            "links" => [
                [
                    "label" => "Open User Management",
                    "href" => "signup.php"
                ]
            ]
        ],
        [
            "title" => "Team Management",
            "eyebrow" => "Team Module",
            "tone" => "user",
            "description" => "Create teams, select members, and appoint a team leader.",
            "status" => "Available",
            "features" => ["Create team", "Add members", "Make leader"],
            "links" => [
                [
                    "label" => "Open Team Management",
                    "href" => "admin/manage_teams.php"
                ]
            ]
        ],
        [
            "title" => "Task Assignment",
            "eyebrow" => "Work Module",
            "tone" => "admin",
            "description" => "Assign tasks to one user or assign the same task to an entire team.",
            "status" => "Available",
            "features" => ["Assign user task", "Assign team task", "Remove assignments"],
            "links" => [
                [
                    "label" => "Open Task Assignment",
                    "href" => "admin/assign_task.php"
                ]
            ]
        ],
        [
            "title" => "Project Management",
            "eyebrow" => "Project Module",
            "tone" => "tracking",
            "description" => "Create projects and define project timelines before assigning work.",
            "status" => "Available",
            "features" => ["Create projects", "Timeline dates", "Project list"],
            "links" => [
                [
                    "label" => "Open Project Management",
                    "href" => "admin/create_project.php"
                ]
            ]
        ],
        [
            "title" => "Progress Tracking",
            "eyebrow" => "Tracking Module",
            "tone" => "tracking",
            "description" => "Monitor team/user progress, deadlines, uploaded files, and completion percentages.",
            "status" => "Available",
            "features" => ["Project progress", "Team leader view", "Submitted files"],
            "links" => [
                [
                    "label" => "Open Progress Tracking",
                    "href" => "admin/view_progress.php"
                ]
            ]
        ],
        [
            "title" => "Team Messages",
            "eyebrow" => "Message Box",
            "tone" => "user",
            "description" => "Send messages to team leaders and team members inside team chats.",
            "status" => "Available",
            "features" => ["Admin messages", "Team chat", "Leader replies"],
            "links" => [
                [
                    "label" => "Open Team Messages",
                    "href" => "user/team_messages.php"
                ]
            ]
        ]
    ];
} else {
    $modules = [
        [
            "title" => "View Tasks",
            "eyebrow" => "My Work",
            "tone" => "user",
            "description" => "See all tasks assigned to your account, including individual and team tasks.",
            "status" => "Available",
            "features" => ["Task list", "Deadlines", "Team name"],
            "links" => [
                [
                    "label" => "Open Task List",
                    "href" => "user/view_tasks.php"
                ]
            ]
        ],
        [
            "title" => "Update Progress",
            "eyebrow" => "Progress Module",
            "tone" => "admin",
            "description" => "Update completion percentage, progress notes, messages, and uploaded files.",
            "status" => "Available",
            "features" => ["Percentage slider", "Progress note", "File upload"],
            "links" => [
                [
                    "label" => "Open Progress Update",
                    "href" => "user/update_task.php"
                ]
            ]
        ],
        [
            "title" => "Project Tracking",
            "eyebrow" => "Tracking Module",
            "tone" => "tracking",
            "description" => "Follow deadlines and completion progress for the work assigned to you.",
            "status" => "Available",
            "features" => ["Deadline view", "Task status", "Progress history"],
            "links" => [
                [
                    "label" => "Open Tracking View",
                    "href" => "user/view_tasks.php"
                ]
            ]
        ],
        [
            "title" => "Team Messages",
            "eyebrow" => "Message Box",
            "tone" => "user",
            "description" => "Chat with your team leader and team members about project work.",
            "status" => "Available",
            "features" => ["Team chat", "Leader messages", "Member replies"],
            "links" => [
                [
                    "label" => "Open Team Messages",
                    "href" => "user/team_messages.php"
                ]
            ]
        ]
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css?v=23">
</head>
<body class="dashboard-page">
    <div class="page">
        <div class="dashboard-topbar">
            <div class="topbar-brand">
                <span><img src="lpulogo.jpg" alt="LPU Logo"></span>
                <strong>Project Control</strong>
            </div>
            <div class="dashboard-toolbar">
                <button type="button" class="button-link secondary theme-toggle" id="themeToggle">Dark Mode</button>
                <details class="profile-menu">
                    <summary class="profile-trigger"><?php echo htmlspecialchars(strtoupper(substr($user['name'], 0, 1))); ?></summary>
                    <div class="profile-dropdown">
                        <div class="profile-dropdown-head">
                            <div class="profile-avatar small"><?php echo htmlspecialchars(strtoupper(substr($user['name'], 0, 1))); ?></div>
                            <div>
                                <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                <p><?php echo htmlspecialchars($roleLabel); ?></p>
                            </div>
                        </div>
                        <div class="profile-dropdown-body">
                            <div class="profile-mini-item">
                                <span>Email</span>
                                <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                            </div>
                            <div class="profile-mini-item">
                                <span>Role</span>
                                <strong><?php echo htmlspecialchars($roleLabel); ?></strong>
                            </div>
                            <div class="profile-mini-item">
                                <span>ID</span>
                                <strong><?php echo htmlspecialchars((string) $user['id']); ?></strong>
                            </div>
                        </div>
                        <div class="profile-dropdown-actions">
                            <a class="button-link" href="logout.php">Logout</a>
                        </div>
                    </div>
                </details>
            </div>
        </div>

        <div class="hero">
            <div class="hero-layout">
                <div class="hero-copy">
                    <p class="portal-name">Software Project Management Portal</p>
                    <h1>Welcome <?php echo htmlspecialchars($user['name']); ?></h1>
                    <p class="muted">Role: <?php echo htmlspecialchars($roleLabel); ?></p>
                    <p class="hero-lead"><?php echo htmlspecialchars($focusLine); ?></p>
                </div>
                <div class="hero-spotlight">
                    <span class="spotlight-label">Workspace Snapshot</span>
                    <strong><?php echo htmlspecialchars($heroMetric); ?></strong>
                </div>
            </div>
        </div>

        <div class="stats-strip">
            <?php if ($user['role'] == "admin") { ?>
                <div class="stat-pill stat-projects">
                    <span>Projects</span>
                    <strong><?php echo (int) $projectCount; ?></strong>
                </div>
                <div class="stat-pill stat-users">
                    <span>Users</span>
                    <strong><?php echo (int) $userCount; ?></strong>
                </div>
                <div class="stat-pill stat-teams">
                    <span>Teams</span>
                    <strong><?php echo (int) $teamCount; ?></strong>
                </div>
                <div class="stat-pill stat-active">
                    <span>In Progress</span>
                    <strong><?php echo (int) $activeTaskCount; ?></strong>
                </div>
                <div class="stat-pill stat-completed">
                    <span>Completed</span>
                    <strong><?php echo (int) $completedTaskCount; ?></strong>
                </div>
            <?php } else { ?>
                <div class="stat-pill stat-active">
                    <span>My Tasks</span>
                    <strong><?php echo (int) $userTaskCount; ?></strong>
                </div>
                <div class="stat-pill stat-active">
                    <span>In Progress</span>
                    <strong><?php echo (int) $userActiveTaskCount; ?></strong>
                </div>
                <div class="stat-pill stat-completed">
                    <span>Completed</span>
                    <strong><?php echo (int) $userCompletedTaskCount; ?></strong>
                </div>
            <?php } ?>
        </div>

        <div class="notification-box">
            <div class="notification-head">
                <div>
                    <span class="notification-kicker">Message Notifications</span>
                    <h3>Team Message Box</h3>
                </div>
                <span class="notification-count"><?php echo count($messageNotifications); ?></span>
            </div>

            <?php if (count($messageNotifications) > 0) { ?>
                <div class="notification-list">
                    <?php foreach ($messageNotifications as $notification) { ?>
                        <a class="notification-item" href="user/team_messages.php?team_id=<?php echo (int) $notification['team_id']; ?>">
                            <div class="notification-dot"></div>
                            <div>
                                <strong><?php echo htmlspecialchars($notification['sender_name']); ?> sent a message in <?php echo htmlspecialchars($notification['team_name']); ?></strong>
                                <?php $previewText = strlen($notification['message_text']) > 96 ? substr($notification['message_text'], 0, 96) . "..." : $notification['message_text']; ?>
                                <p><?php echo htmlspecialchars($previewText); ?></p>
                                <span><?php echo htmlspecialchars(date("d M Y, h:i A", strtotime($notification['created_at']))); ?></span>
                            </div>
                        </a>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="empty-state">
                    <p>No new team messages from others.</p>
                </div>
            <?php } ?>
        </div>

        <?php if ($user['role'] == "admin" && $projectCount === 0) { ?>
            <div class="alert error">No projects are currently stored in the database. Create a project first so task assignment and tracking can work normally.</div>
        <?php } ?>

        <?php if ($user['role'] == "admin" && $orphanTaskCount > 0) { ?>
            <div class="alert error"><?php echo (int) $orphanTaskCount; ?> existing tasks are linked to missing projects. Recreate or correct those project records so tracking stays accurate.</div>
        <?php } ?>

        <div class="dashboard-section-head">
            <div>
                <h2>Project Modules</h2>
                <p class="muted">
                    This dashboard shows the modules available for your role.
                </p>
            </div>
        </div>

        <div class="dashboard-grid">
            <?php foreach ($modules as $index => $module) { ?>
                <div class="dashboard-card module-card module-<?php echo htmlspecialchars($module['tone']); ?> active">
                    <div class="module-card-top">
                        <span class="module-index"><?php echo str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT); ?></span>
                        <span class="module-badge <?php echo strtolower($module['status']); ?>">
                            <?php echo htmlspecialchars($module['status']); ?>
                        </span>
                    </div>
                    <span class="module-eyebrow"><?php echo htmlspecialchars($module['eyebrow']); ?></span>
                    <h3><?php echo htmlspecialchars($module['title']); ?></h3>
                    <p class="muted"><?php echo htmlspecialchars($module['description']); ?></p>
                    <div class="module-feature-list">
                        <?php foreach ($module['features'] as $feature) { ?>
                            <span><?php echo htmlspecialchars($feature); ?></span>
                        <?php } ?>
                    </div>
                    <div class="module-links">
                        <?php foreach ($module['links'] as $moduleLink) { ?>
                            <a class="button-link<?php echo !empty($moduleLink['disabled']) ? ' secondary disabled-link' : ''; ?>" href="<?php echo htmlspecialchars($moduleLink['href']); ?>"<?php echo !empty($moduleLink['disabled']) ? ' aria-disabled="true"' : ''; ?>>
                                <?php echo htmlspecialchars($moduleLink['label']); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php echo portal_theme_script(); ?>
    <?php echo portal_auto_logout_script("auto_logout.php"); ?>
</body>
</html>
  
