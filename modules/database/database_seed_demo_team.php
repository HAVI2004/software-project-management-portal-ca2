<?php
// Database Module: demo team seed script.
require __DIR__ . "/database_connection.php";
require __DIR__ . "/../common/common_portal_helpers.php";

ensure_team_tables($conn);

$teamName = "Frontend Team";
$leaderEmail = "aarav@portal.local";
$memberEmails = ["aarav@portal.local", "priya@portal.local"];

// SQL: Find the demo team leader user by email.
$leaderStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$leaderStmt->bind_param("s", $leaderEmail);
$leaderStmt->execute();
$leader = $leaderStmt->get_result()->fetch_assoc();
$leaderStmt->close();

if (!$leader) {
    echo "Demo users not found. Create Aarav and Priya first.\n";
    exit();
}

$leaderId = (int) $leader["id"];
// SQL: Check whether the demo team already exists.
$teamStmt = $conn->prepare("SELECT team_id FROM teams WHERE team_name = ? LIMIT 1");
$teamStmt->bind_param("s", $teamName);
$teamStmt->execute();
$team = $teamStmt->get_result()->fetch_assoc();
$teamStmt->close();

if ($team) {
    $teamId = (int) $team["team_id"];
    // SQL: Update the existing demo team's leader.
    $updateStmt = $conn->prepare("UPDATE teams SET leader_id = ? WHERE team_id = ?");
    $updateStmt->bind_param("ii", $leaderId, $teamId);
    $updateStmt->execute();
    $updateStmt->close();
} else {
    // SQL: Insert the demo team.
    $insertStmt = $conn->prepare("INSERT INTO teams(team_name, leader_id) VALUES(?, ?)");
    $insertStmt->bind_param("si", $teamName, $leaderId);
    $insertStmt->execute();
    $teamId = (int) $conn->insert_id;
    $insertStmt->close();
}

// SQL: Add demo users as team members without duplicating rows.
$memberStmt = $conn->prepare("INSERT IGNORE INTO team_members(team_id, user_id) VALUES(?, ?)");
foreach ($memberEmails as $email) {
    // SQL: Find each demo member user by email.
    $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $userStmt->bind_param("s", $email);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if ($user && $memberStmt) {
        $userId = (int) $user["id"];
        $memberStmt->bind_param("ii", $teamId, $userId);
        $memberStmt->execute();
    }
}

if ($memberStmt) {
    $memberStmt->close();
}

echo "Demo team ready: Frontend Team.\n";
?>
