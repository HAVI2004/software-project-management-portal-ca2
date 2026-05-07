<?php
// Database Module: demo task seed script.
require __DIR__ . "/../config/db.php";

// SQL: Find the demo project that should receive sample tasks.
$projectResult = $conn->query("SELECT project_id FROM projects WHERE project_name = 'CA2 Website Completion' LIMIT 1");
$project = $projectResult ? $projectResult->fetch_assoc() : null;

if (!$project) {
    echo "Project not found. Create the demo project first.\n";
    exit();
}

$projectId = (int) $project["project_id"];
$samples = [
    ["aarav@portal.local", "Frontend UI Polish", "2026-05-10"],
    ["priya@portal.local", "Progress Tracking Test", "2026-05-12"],
];

foreach ($samples as $sample) {
    // SQL: Find the sample task user by email.
    $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $userStmt->bind_param("s", $sample[0]);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        continue;
    }

    $userId = (int) $user["id"];
    // SQL: Check whether this sample task already exists.
    $check = $conn->prepare("SELECT task_id FROM tasks WHERE project_id = ? AND assigned_to = ? AND task_name = ? LIMIT 1");
    $check->bind_param("iis", $projectId, $userId, $sample[1]);
    $check->execute();
    $exists = $check->get_result();
    $check->close();

    if ($exists && $exists->num_rows > 0) {
        continue;
    }

    $status = "Pending";
    $progress = 0;
    $adminMessage = "Update the completion percentage as work progresses.";
    // SQL: Insert the sample task for the demo user.
    $insert = $conn->prepare("INSERT INTO tasks(project_id, assigned_to, task_name, status, deadline, progress_percent, admin_message) VALUES(?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param("iisssis", $projectId, $userId, $sample[1], $status, $sample[2], $progress, $adminMessage);
    $insert->execute();
    $insert->close();
}

echo "Sample user tasks ready.\n";
?>
