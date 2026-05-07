<?php
// Task Management Module: assign tasks to users and teams.
include(__DIR__ . "/../authentication/auth_session.php");
portal_start_session();
include(__DIR__ . "/../database/database_connection.php");
include(__DIR__ . "/../common/common_portal_helpers.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== "admin") {
    header("Location: ../index.php");
    exit();
}

ensure_task_progress_columns($conn);
ensure_team_tables($conn);

$flash = portal_get_flash();
$msg = $flash['message'] ?? "";
$msgClass = $flash['type'] ?? "";
$projects = [];
$users = [];
$projectWindows = [];
$projectAssignments = [];
$teams = [];

// SQL: Fetch available projects for task assignment.
$projectResult = $conn->query("SELECT project_id, project_name, start_date, end_date FROM projects ORDER BY project_name ASC");
if ($projectResult) {
    while ($row = $projectResult->fetch_assoc()) {
        $projectWindows[(int) $row['project_id']] = $row;
        $projects[] = $row;
    }
}

// SQL: Fetch users who can receive assigned tasks.
$userResult = $conn->query("SELECT id, name, email FROM users WHERE role = 'user' ORDER BY name ASC");
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
    }
}

$assignmentBlocked = count($projects) === 0 || count($users) === 0;

// SQL: Fetch teams available for team task assignment.
$teamResult = $conn->query("
    SELECT teams.team_id, teams.team_name, users.name AS leader_name, COUNT(team_members.user_id) AS member_total
    FROM teams
    INNER JOIN users ON users.id = teams.leader_id
    LEFT JOIN team_members ON team_members.team_id = teams.team_id
    GROUP BY teams.team_id, teams.team_name, users.name
    ORDER BY teams.team_name ASC
");
if ($teamResult) {
    while ($row = $teamResult->fetch_assoc()) {
        $teams[] = $row;
    }
}

if (isset($_POST['assign'])) {
    $projectId = (int) $_POST['project'];
    $userId = (int) $_POST['user'];
    $taskName = trim($_POST['task']);
    $deadline = $_POST['deadline'];
    $adminMessage = trim($_POST['admin_message']);
    $selectedProject = $projectWindows[$projectId] ?? null;

    // SQL: Confirm the selected project exists.
    $projectCheck = $conn->query("SELECT project_id FROM projects WHERE project_id = $projectId");
    // SQL: Confirm the selected user exists and has user role.
    $userCheck = $conn->query("SELECT id FROM users WHERE id = $userId AND role = 'user'");

    if ($taskName === "" || $deadline === "") {
        $msg = "Please fill all fields.";
        $msgClass = "error";
    } elseif (!$projectCheck || $projectCheck->num_rows === 0) {
        $msg = "Selected project was not found.";
        $msgClass = "error";
    } elseif (!$userCheck || $userCheck->num_rows === 0) {
        $msg = "Selected user was not found.";
        $msgClass = "error";
    } elseif ($selectedProject && ($deadline < $selectedProject['start_date'] || $deadline > $selectedProject['end_date'])) {
        $msg = "Task deadline must stay within the selected project's timeline.";
        $msgClass = "error";
    } else {
        $adminMessageValue = $adminMessage === "" ? null : $adminMessage;
        $status = "Pending";
        $progressPercent = 0;
        // SQL: Insert a new task assigned to one user.
        $stmt = $conn->prepare("
            INSERT INTO tasks(project_id, assigned_to, task_name, status, deadline, progress_percent, progress_note, admin_message, user_message, attachment_path)
            VALUES(?, ?, ?, ?, ?, ?, NULL, ?, NULL, NULL)
        ");

        if ($stmt && $stmt->bind_param("iisssis", $projectId, $userId, $taskName, $status, $deadline, $progressPercent, $adminMessageValue) && $stmt->execute()) {
            portal_set_flash("Task assigned successfully.");
            portal_redirect("assign_task.php");
        } else {
            $msg = "Error assigning task. " . $conn->error;
            $msgClass = "error";
        }

        if ($stmt) {
            $stmt->close();
        }
    }
}

if (isset($_POST['assign_team'])) {
    $projectId = (int) ($_POST['team_project'] ?? 0);
    $teamId = (int) ($_POST['team_id'] ?? 0);
    $taskName = trim($_POST['team_task'] ?? "");
    $deadline = $_POST['team_deadline'] ?? "";
    $adminMessage = trim($_POST['team_admin_message'] ?? "");
    $selectedProject = $projectWindows[$projectId] ?? null;

    // SQL: Confirm the selected project exists.
    $projectCheck = $conn->query("SELECT project_id FROM projects WHERE project_id = $projectId");
    // SQL: Confirm the selected team exists.
    $teamCheck = $conn->query("SELECT team_id FROM teams WHERE team_id = $teamId");
    // SQL: Fetch all members of the selected team.
    $memberResult = $conn->query("SELECT user_id FROM team_members WHERE team_id = $teamId");
    $memberIds = [];

    if ($memberResult) {
        while ($member = $memberResult->fetch_assoc()) {
            $memberIds[] = (int) $member['user_id'];
        }
    }

    if ($taskName === "" || $deadline === "") {
        $msg = "Please fill all team task fields.";
        $msgClass = "error";
    } elseif (!$projectCheck || $projectCheck->num_rows === 0) {
        $msg = "Selected project was not found.";
        $msgClass = "error";
    } elseif (!$teamCheck || $teamCheck->num_rows === 0) {
        $msg = "Selected team was not found.";
        $msgClass = "error";
    } elseif (count($memberIds) === 0) {
        $msg = "Selected team has no members.";
        $msgClass = "error";
    } elseif ($selectedProject && ($deadline < $selectedProject['start_date'] || $deadline > $selectedProject['end_date'])) {
        $msg = "Team task deadline must stay within the selected project's timeline.";
        $msgClass = "error";
    } else {
        $adminMessageValue = $adminMessage === "" ? null : $adminMessage;
        $status = "Pending";
        $progressPercent = 0;
        // SQL: Insert one team task record for each team member.
        $stmt = $conn->prepare("
            INSERT INTO tasks(project_id, team_id, assigned_to, task_name, status, deadline, progress_percent, progress_note, admin_message, user_message, attachment_path)
            VALUES(?, ?, ?, ?, ?, ?, ?, NULL, ?, NULL, NULL)
        ");
        $createdCount = 0;

        foreach ($memberIds as $memberId) {
            if ($stmt && $stmt->bind_param("iiisssis", $projectId, $teamId, $memberId, $taskName, $status, $deadline, $progressPercent, $adminMessageValue) && $stmt->execute()) {
                $createdCount++;
            }
        }

        if ($stmt) {
            $stmt->close();
        }

        if ($createdCount > 0) {
            portal_set_flash("Team task assigned to $createdCount team members.");
            portal_redirect("assign_task.php");
        } else {
            $msg = "Unable to assign task to team.";
            $msgClass = "error";
        }
    }
}

if (isset($_POST['remove_assignment'])) {
    $projectId = (int) ($_POST['project_remove'] ?? 0);
    $userId = (int) ($_POST['user_remove'] ?? 0);

    if ($projectId <= 0 || $userId <= 0) {
        $msg = "Select both a project and a user to remove from project work.";
        $msgClass = "error";
    } else {
        // SQL: Delete task assignments for the selected user and project.
        $assignmentDelete = $conn->query("DELETE FROM tasks WHERE project_id = $projectId AND assigned_to = $userId");

        if ($assignmentDelete) {
            $removedCount = (int) $conn->affected_rows;
            portal_set_flash($removedCount > 0
                ? "User removed from the selected project."
                : "No matching project assignments were found for that user.");
            portal_redirect("assign_task.php");
        } else {
            $msg = "Unable to remove that user from the selected project.";
            $msgClass = "error";
        }
    }
}

// SQL: Fetch current project assignment summary.
$assignmentResult = $conn->query("
    SELECT
        projects.project_name,
        users.name AS user_name,
        users.email,
        COUNT(tasks.task_id) AS task_total
    FROM tasks
    INNER JOIN projects ON projects.project_id = tasks.project_id
    INNER JOIN users ON users.id = tasks.assigned_to
    GROUP BY projects.project_id, projects.project_name, users.id, users.name, users.email
    ORDER BY projects.project_name ASC, users.name ASC
");

if ($assignmentResult) {
    while ($row = $assignmentResult->fetch_assoc()) {
        $projectAssignments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Assign Task</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=22">
</head>
<body>
    <div class="page narrow">
        <div class="dashboard-topbar page-toolbar">
            <div></div>
            <div class="dashboard-toolbar">
                <a class="button-link secondary" href="../dashboard.php">Back to Dashboard</a>
                <button type="button" class="button-link secondary theme-toggle" id="themeToggle">Dark Mode</button>
            </div>
        </div>

        <form method="POST">
            <p class="portal-name">Software Project Management Portal</p>
            <h2>Assign Task</h2>
            <p class="muted">Create a task, assign it to a user, and set the deadline.</p>

            <?php if ($msg) { ?>
                <div class="alert <?php echo $msgClass; ?>"><?php echo $msg; ?></div>
            <?php } ?>

            <?php if (count($projects) === 0) { ?>
                <div class="alert error">No projects are available. Create a project before assigning tasks.</div>
            <?php } ?>

            <?php if (count($users) === 0) { ?>
                <div class="alert error">No user accounts are available. Add a team member before assigning tasks.</div>
            <?php } ?>

            <div class="field">
                <label for="project">Project</label>
                <select id="project" name="project" required <?php echo $assignmentBlocked ? 'disabled' : ''; ?>>
                    <option value="">Select a project</option>
                    <?php foreach ($projects as $project) { ?>
                        <option value="<?php echo (int) $project['project_id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?> (<?php echo htmlspecialchars($project['start_date']); ?> to <?php echo htmlspecialchars($project['end_date']); ?>)</option>
                    <?php } ?>
                </select>
            </div>

            <div class="field">
                <label for="user">Team Member</label>
                <select id="user" name="user" required <?php echo $assignmentBlocked ? 'disabled' : ''; ?>>
                    <option value="">Select a user</option>
                    <?php foreach ($users as $teamMember) { ?>
                        <option value="<?php echo (int) $teamMember['id']; ?>"><?php echo htmlspecialchars($teamMember['name']); ?> (<?php echo htmlspecialchars($teamMember['email']); ?>)</option>
                    <?php } ?>
                </select>
            </div>

            <div class="field">
                <label for="task">Task Name</label>
                <input type="text" id="task" name="task" required <?php echo $assignmentBlocked ? 'disabled' : ''; ?>>
            </div>

            <div class="field">
                <label for="deadline">Deadline</label>
                <input type="date" id="deadline" name="deadline" required <?php echo $assignmentBlocked ? 'disabled' : ''; ?>>
            </div>

            <div class="field">
                <label for="admin_message">Message to User</label>
                <textarea id="admin_message" name="admin_message" placeholder="Optional instructions or notes for the assigned user." <?php echo $assignmentBlocked ? 'disabled' : ''; ?>></textarea>
            </div>

            <div class="actions">
                <button type="submit" name="assign" <?php echo $assignmentBlocked ? 'disabled' : ''; ?>>Assign</button>
                <?php if (count($projects) === 0) { ?>
                    <a class="button-link secondary" href="create_project.php">Create Project First</a>
                <?php } ?>
            </div>
        </form>

        <form method="POST">
            <p class="portal-name">Software Project Management Portal</p>
            <h2>Assign Task To Team</h2>
            <p class="muted">Create one task for every member in the selected team. The task remains linked to that team.</p>

            <?php if (count($teams) === 0) { ?>
                <div class="alert error">No teams are available. Create a team before assigning team tasks.</div>
            <?php } ?>

            <div class="field">
                <label for="team_project">Project</label>
                <select id="team_project" name="team_project" required <?php echo count($projects) === 0 || count($teams) === 0 ? 'disabled' : ''; ?>>
                    <option value="">Select a project</option>
                    <?php foreach ($projects as $project) { ?>
                        <option value="<?php echo (int) $project['project_id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?> (<?php echo htmlspecialchars($project['start_date']); ?> to <?php echo htmlspecialchars($project['end_date']); ?>)</option>
                    <?php } ?>
                </select>
            </div>

            <div class="field">
                <label for="team_id">Team</label>
                <select id="team_id" name="team_id" required <?php echo count($teams) === 0 ? 'disabled' : ''; ?>>
                    <option value="">Select a team</option>
                    <?php foreach ($teams as $team) { ?>
                        <option value="<?php echo (int) $team['team_id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?> - Leader: <?php echo htmlspecialchars($team['leader_name']); ?> (<?php echo (int) $team['member_total']; ?> members)</option>
                    <?php } ?>
                </select>
            </div>

            <div class="field">
                <label for="team_task">Task Name</label>
                <input type="text" id="team_task" name="team_task" required <?php echo count($teams) === 0 ? 'disabled' : ''; ?>>
            </div>

            <div class="field">
                <label for="team_deadline">Deadline</label>
                <input type="date" id="team_deadline" name="team_deadline" required <?php echo count($teams) === 0 ? 'disabled' : ''; ?>>
            </div>

            <div class="field">
                <label for="team_admin_message">Message to Team</label>
                <textarea id="team_admin_message" name="team_admin_message" placeholder="Optional instructions for the team." <?php echo count($teams) === 0 ? 'disabled' : ''; ?>></textarea>
            </div>

            <div class="actions">
                <button type="submit" name="assign_team" <?php echo count($projects) === 0 || count($teams) === 0 ? 'disabled' : ''; ?>>Assign To Team</button>
                <?php if (count($teams) === 0) { ?>
                    <a class="button-link secondary" href="manage_teams.php">Create Team First</a>
                <?php } ?>
            </div>
        </form>

        <form method="POST">
            <p class="portal-name">Software Project Management Portal</p>
            <h2>Remove User From Project</h2>
            <p class="muted">Remove a user from a project by clearing their task assignments in that project.</p>

            <div class="field">
                <label for="project_remove">Project</label>
                <select id="project_remove" name="project_remove" required>
                    <option value="">Select a project</option>
                    <?php foreach ($projects as $project) { ?>
                        <option value="<?php echo (int) $project['project_id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="field">
                <label for="user_remove">User</label>
                <select id="user_remove" name="user_remove" required>
                    <option value="">Select a user</option>
                    <?php foreach ($users as $teamMember) { ?>
                        <option value="<?php echo (int) $teamMember['id']; ?>"><?php echo htmlspecialchars($teamMember['name']); ?> (<?php echo htmlspecialchars($teamMember['email']); ?>)</option>
                    <?php } ?>
                </select>
            </div>

            <div class="actions">
                <button type="submit" name="remove_assignment" class="secondary">Remove From Project</button>
            </div>
        </form>

        <div class="task-list">
            <div class="task-card">
                <h3>Current Project Assignments</h3>
                <?php if (count($projectAssignments) > 0) { ?>
                    <?php foreach ($projectAssignments as $assignment) { ?>
                        <div class="task-card-stack">
                            <div class="detail-grid">
                                <p><strong>Project:</strong> <?php echo htmlspecialchars($assignment['project_name']); ?></p>
                                <p><strong>User:</strong> <?php echo htmlspecialchars($assignment['user_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($assignment['email']); ?></p>
                                <p><strong>Assigned Tasks:</strong> <?php echo (int) $assignment['task_total']; ?></p>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p>No project assignments exist yet.</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php echo portal_theme_script(); ?>
    <?php echo portal_auto_logout_script("../auto_logout.php"); ?>
</body>
</html>
