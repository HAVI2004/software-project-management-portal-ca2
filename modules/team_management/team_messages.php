<?php
// Team Management Module: team message box.
include(__DIR__ . "/../authentication/auth_session.php");
portal_start_session();
include(__DIR__ . "/../database/database_connection.php");
include(__DIR__ . "/../common/common_portal_helpers.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

ensure_team_messages_table($conn);

$user = $_SESSION['user'];
$userId = (int) $user['id'];
$isAdmin = $user['role'] === "admin";
$msg = "";
$msgClass = "";
$teams = [];
$selectedTeamId = (int) ($_GET['team_id'] ?? ($_POST['team_id'] ?? 0));

$teamSql = $isAdmin
    ? "
        SELECT teams.team_id, teams.team_name, teams.leader_id, leaders.name AS leader_name
        FROM teams
        INNER JOIN users leaders ON leaders.id = teams.leader_id
        ORDER BY teams.team_name ASC
    "
    : "
        SELECT DISTINCT teams.team_id, teams.team_name, teams.leader_id, leaders.name AS leader_name
        FROM teams
        INNER JOIN users leaders ON leaders.id = teams.leader_id
        LEFT JOIN team_members ON team_members.team_id = teams.team_id
        WHERE team_members.user_id = $userId OR teams.leader_id = $userId
        ORDER BY teams.team_name ASC
    ";

// SQL: Fetch teams visible to the logged-in user.
$teamResult = $conn->query($teamSql);

if ($teamResult) {
    while ($team = $teamResult->fetch_assoc()) {
        $teams[] = $team;
    }
}

if ($selectedTeamId <= 0 && count($teams) > 0) {
    $selectedTeamId = (int) $teams[0]['team_id'];
}

$allowedTeamIds = array_map(function ($team) {
    return (int) $team['team_id'];
}, $teams);

if ($selectedTeamId > 0 && !in_array($selectedTeamId, $allowedTeamIds, true)) {
    $selectedTeamId = count($teams) > 0 ? (int) $teams[0]['team_id'] : 0;
}

if (isset($_POST['send_message'])) {
    $messageText = trim($_POST['message_text'] ?? "");

    if ($selectedTeamId <= 0 || !in_array($selectedTeamId, $allowedTeamIds, true)) {
        $msg = "Choose a valid team.";
        $msgClass = "error";
    } elseif ($messageText === "") {
        $msg = "Enter a message before sending.";
        $msgClass = "error";
    } else {
        // SQL: Insert a new message into the selected team chat.
        $stmt = $conn->prepare("INSERT INTO team_messages(team_id, sender_id, message_text) VALUES(?, ?, ?)");

        if ($stmt && $stmt->bind_param("iis", $selectedTeamId, $userId, $messageText) && $stmt->execute()) {
            $stmt->close();
            portal_redirect("team_messages.php?team_id=" . $selectedTeamId);
        }

        if ($stmt) {
            $stmt->close();
        }

        $msg = "Unable to send message.";
        $msgClass = "error";
    }
}

$selectedTeam = null;
foreach ($teams as $team) {
    if ((int) $team['team_id'] === $selectedTeamId) {
        $selectedTeam = $team;
        break;
    }
}

$members = [];
$messages = [];

if ($selectedTeamId > 0) {
    // SQL: Fetch members of the selected team.
    $memberResult = $conn->query("
        SELECT users.id, users.name, users.email
        FROM team_members
        INNER JOIN users ON users.id = team_members.user_id
        WHERE team_members.team_id = $selectedTeamId
        ORDER BY users.name ASC
    ");

    if ($memberResult) {
        while ($member = $memberResult->fetch_assoc()) {
            $members[] = $member;
        }
    }

    // SQL: Fetch all messages for the selected team conversation.
    $messageResult = $conn->query("
        SELECT team_messages.message_id, team_messages.message_text, team_messages.created_at,
               users.id AS sender_id, users.name AS sender_name, users.role AS sender_role,
               teams.leader_id
        FROM team_messages
        INNER JOIN users ON users.id = team_messages.sender_id
        INNER JOIN teams ON teams.team_id = team_messages.team_id
        WHERE team_messages.team_id = $selectedTeamId
        ORDER BY team_messages.created_at ASC, team_messages.message_id ASC
    ");

    if ($messageResult) {
        while ($message = $messageResult->fetch_assoc()) {
            $messages[] = $message;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Team Messages</title>
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
            <h2>Team Messages</h2>
                <p class="muted">Admins, team leaders, and team members can discuss project work in one shared message box.</p>
        </div>

        <?php if ($msg) { ?>
            <div class="alert <?php echo htmlspecialchars($msgClass); ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php } ?>

        <?php if (count($teams) === 0) { ?>
            <div class="task-card">
                <h3>No Team Found</h3>
                <p class="muted">You are not added to any team yet. Ask the admin to add you to a team.</p>
            </div>
        <?php } else { ?>
            <div class="team-message-layout">
                <aside class="messages-sidebar">
                    <h3>Your Teams</h3>
                    <div class="contact-list">
                        <?php foreach ($teams as $team) { ?>
                            <a class="contact-card <?php echo (int) $team['team_id'] === $selectedTeamId ? 'active' : ''; ?>" href="team_messages.php?team_id=<?php echo (int) $team['team_id']; ?>">
                                <div class="contact-avatar"><?php echo htmlspecialchars(strtoupper(substr($team['team_name'], 0, 1))); ?></div>
                                <div class="contact-text">
                                    <strong><?php echo htmlspecialchars($team['team_name']); ?></strong>
                                    <span>Leader: <?php echo htmlspecialchars($team['leader_name']); ?></span>
                                </div>
                            </a>
                        <?php } ?>
                    </div>
                </aside>

                <section class="messages-panel">
                    <div class="conversation-header">
                        <div class="contact-avatar large"><?php echo htmlspecialchars(strtoupper(substr($selectedTeam['team_name'] ?? "T", 0, 1))); ?></div>
                        <div>
                            <h3><?php echo htmlspecialchars($selectedTeam['team_name'] ?? "Team"); ?></h3>
                            <p class="muted">
                                <?php echo count($members); ?> members · Leader: <?php echo htmlspecialchars($selectedTeam['leader_name'] ?? "Not assigned"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="conversation-list team-conversation-list">
                        <?php if (count($messages) > 0) { ?>
                            <?php foreach ($messages as $message) { ?>
                                <?php $isMine = (int) $message['sender_id'] === $userId; ?>
                                <?php $isLeader = (int) $message['sender_id'] === (int) $message['leader_id']; ?>
                                <?php $senderLabel = $message['sender_role'] === "admin" ? "Admin" : ($isLeader ? "Team Leader" : ""); ?>
                                <div class="message-bubble <?php echo $isMine ? 'mine' : 'theirs'; ?>">
                                    <strong>
                                        <?php echo htmlspecialchars($isMine ? "You" : $message['sender_name']); ?>
                                        <?php echo $senderLabel !== "" ? ' · ' . htmlspecialchars($senderLabel) : ''; ?>
                                    </strong>
                                    <p><?php echo nl2br(htmlspecialchars($message['message_text'])); ?></p>
                                    <span><?php echo htmlspecialchars(date("d M Y, h:i A", strtotime($message['created_at']))); ?></span>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="empty-state large">
                                <p>No messages yet. Start the team discussion.</p>
                            </div>
                        <?php } ?>
                    </div>

                    <form method="POST" class="message-form team-message-form">
                        <input type="hidden" name="team_id" value="<?php echo (int) $selectedTeamId; ?>">
                        <textarea name="message_text" placeholder="Type message to team leader or team members..." required></textarea>
                        <button type="submit" name="send_message">Send Message</button>
                    </form>
                </section>
            </div>
        <?php } ?>
    </div>
    <?php echo portal_theme_script(); ?>
    <?php echo portal_auto_logout_script("../auto_logout.php"); ?>
</body>
</html>
