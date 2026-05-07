<?php
// Team Management Module: create teams and manage leaders.
include(__DIR__ . "/../modules/authentication/auth_session.php");
portal_start_session();
include(__DIR__ . "/../modules/database/database_connection.php");
include(__DIR__ . "/../modules/common/common_portal_helpers.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== "admin") {
    header("Location: ../index.php");
    exit();
}

ensure_team_tables($conn);

$flash = portal_get_flash();
$msg = $flash['message'] ?? "";
$msgClass = $flash['type'] ?? "";
$users = [];
$teams = [];

// SQL: Fetch users who can be added to teams.
$userResult = $conn->query("SELECT id, name, email FROM users WHERE role = 'user' ORDER BY name ASC");
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
    }
}

if (isset($_POST['create_team'])) {
    $teamName = trim($_POST['team_name'] ?? "");
    $leaderId = (int) ($_POST['leader_id'] ?? 0);
    $memberIds = array_map('intval', $_POST['member_ids'] ?? []);

    if ($leaderId > 0 && !in_array($leaderId, $memberIds, true)) {
        $memberIds[] = $leaderId;
    }

    $memberIds = array_values(array_unique(array_filter($memberIds)));
    // SQL: Confirm the selected team leader is a valid user.
    $leaderCheck = $conn->query("SELECT id FROM users WHERE id = $leaderId AND role = 'user' LIMIT 1");

    if ($teamName === "" || $leaderId <= 0 || count($memberIds) === 0) {
        $msg = "Enter a team name, choose a leader, and select at least one member.";
        $msgClass = "error";
    } elseif (!$leaderCheck || $leaderCheck->num_rows === 0) {
        $msg = "Selected team leader was not found.";
        $msgClass = "error";
    } else {
        // SQL: Insert a new team with its leader.
        $stmt = $conn->prepare("INSERT INTO teams(team_name, leader_id) VALUES(?, ?)");

        if ($stmt && $stmt->bind_param("si", $teamName, $leaderId) && $stmt->execute()) {
            $teamId = (int) $conn->insert_id;
            // SQL: Add selected users as team members.
            $memberStmt = $conn->prepare("INSERT IGNORE INTO team_members(team_id, user_id) VALUES(?, ?)");

            foreach ($memberIds as $memberId) {
                // SQL: Confirm each selected team member is a valid user.
                $memberCheck = $conn->query("SELECT id FROM users WHERE id = $memberId AND role = 'user' LIMIT 1");
                if ($memberStmt && $memberCheck && $memberCheck->num_rows > 0) {
                    $memberStmt->bind_param("ii", $teamId, $memberId);
                    $memberStmt->execute();
                }
            }

            if ($memberStmt) {
                $memberStmt->close();
            }

            portal_set_flash("Team created successfully.");
            portal_redirect("manage_teams.php");
        } else {
            $msg = "Unable to create team.";
            $msgClass = "error";
        }

        if ($stmt) {
            $stmt->close();
        }
    }
}

if (isset($_POST['update_leader'])) {
    $teamId = (int) ($_POST['team_id'] ?? 0);
    $leaderId = (int) ($_POST['new_leader_id'] ?? 0);
    // SQL: Confirm the new leader is a valid user.
    $leaderCheck = $conn->query("SELECT id FROM users WHERE id = $leaderId AND role = 'user' LIMIT 1");

    if ($teamId <= 0 || $leaderId <= 0 || !$leaderCheck || $leaderCheck->num_rows === 0) {
        $msg = "Choose a valid team and leader.";
        $msgClass = "error";
    } else {
        // SQL: Update the leader for the selected team.
        $stmt = $conn->prepare("UPDATE teams SET leader_id = ? WHERE team_id = ?");

        if ($stmt && $stmt->bind_param("ii", $leaderId, $teamId) && $stmt->execute()) {
            // SQL: Ensure the new leader is also saved as a team member.
            $memberStmt = $conn->prepare("INSERT IGNORE INTO team_members(team_id, user_id) VALUES(?, ?)");
            if ($memberStmt) {
                $memberStmt->bind_param("ii", $teamId, $leaderId);
                $memberStmt->execute();
                $memberStmt->close();
            }

            portal_set_flash("Team leader updated.");
            portal_redirect("manage_teams.php");
        } else {
            $msg = "Unable to update team leader.";
            $msgClass = "error";
        }

        if ($stmt) {
            $stmt->close();
        }
    }
}

// SQL: Fetch teams with leader details and member counts.
$teamResult = $conn->query("
    SELECT
        teams.team_id,
        teams.team_name,
        leaders.name AS leader_name,
        leaders.email AS leader_email,
        COUNT(team_members.user_id) AS member_total
    FROM teams
    INNER JOIN users leaders ON leaders.id = teams.leader_id
    LEFT JOIN team_members ON team_members.team_id = teams.team_id
    GROUP BY teams.team_id, teams.team_name, leaders.name, leaders.email
    ORDER BY teams.team_name ASC
");

if ($teamResult) {
    while ($team = $teamResult->fetch_assoc()) {
        $team["members"] = [];
        // SQL: Fetch member names and emails for this team.
        $memberResult = $conn->query("
            SELECT users.name, users.email
            FROM team_members
            INNER JOIN users ON users.id = team_members.user_id
            WHERE team_members.team_id = " . (int) $team["team_id"] . "
            ORDER BY users.name ASC
        ");

        if ($memberResult) {
            while ($member = $memberResult->fetch_assoc()) {
                $team["members"][] = $member;
            }
        }

        $teams[] = $team;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Manage Teams</title>
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
            <h2>Create Team</h2>
            <p class="muted">Create a team, select members, and make one member the team leader.</p>

            <?php if ($msg) { ?>
                <div class="alert <?php echo $msgClass; ?>"><?php echo htmlspecialchars($msg); ?></div>
            <?php } ?>

            <?php if (count($users) === 0) { ?>
                <div class="alert error">Create user accounts first before creating teams.</div>
            <?php } ?>

            <div class="field">
                <label for="team_name">Team Name</label>
                <input type="text" id="team_name" name="team_name" placeholder="Example: Frontend Team" required>
            </div>

            <div class="field">
                <label for="leader_id">Team Leader</label>
                <select id="leader_id" name="leader_id" required>
                    <option value="">Select leader</option>
                    <?php foreach ($users as $teamMember) { ?>
                        <option value="<?php echo (int) $teamMember['id']; ?>"><?php echo htmlspecialchars($teamMember['name']); ?> (<?php echo htmlspecialchars($teamMember['email']); ?>)</option>
                    <?php } ?>
                </select>
            </div>

            <div class="field">
                <label>Team Members</label>
                <div class="checkbox-grid">
                    <?php foreach ($users as $teamMember) { ?>
                        <label>
                            <input type="checkbox" name="member_ids[]" value="<?php echo (int) $teamMember['id']; ?>">
                            <span><?php echo htmlspecialchars($teamMember['name']); ?></span>
                        </label>
                    <?php } ?>
                </div>
                <p class="field-hint">The selected leader will be added as a member automatically.</p>
            </div>

            <div class="actions">
                <button type="submit" name="create_team" <?php echo count($users) === 0 ? 'disabled' : ''; ?>>Create Team</button>
            </div>
        </form>

        <div class="task-list">
            <div class="task-card">
                <h3>Existing Teams</h3>
                <?php if (count($teams) > 0) { ?>
                    <?php foreach ($teams as $team) { ?>
                        <div class="task-card-stack">
                            <div class="detail-grid">
                                <p><strong>Team:</strong> <?php echo htmlspecialchars($team['team_name']); ?></p>
                                <p><strong>Leader:</strong> <?php echo htmlspecialchars($team['leader_name']); ?> (<?php echo htmlspecialchars($team['leader_email']); ?>)</p>
                                <p><strong>Members:</strong> <?php echo (int) $team['member_total']; ?></p>
                            </div>
                            <p><strong>Member List:</strong> <?php echo htmlspecialchars(implode(", ", array_map(function ($member) { return $member["name"]; }, $team["members"]))); ?></p>
                            <form method="POST" class="inline-form inline-controls">
                                <input type="hidden" name="team_id" value="<?php echo (int) $team['team_id']; ?>">
                                <select name="new_leader_id" required>
                                    <option value="">Change leader</option>
                                    <?php foreach ($users as $teamMember) { ?>
                                        <option value="<?php echo (int) $teamMember['id']; ?>"><?php echo htmlspecialchars($teamMember['name']); ?></option>
                                    <?php } ?>
                                </select>
                                <button type="submit" name="update_leader" class="secondary">Make Leader</button>
                            </form>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p>No teams created yet.</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php echo portal_theme_script(); ?>
    <?php echo portal_auto_logout_script("../auto_logout.php"); ?>
</body>
</html>
