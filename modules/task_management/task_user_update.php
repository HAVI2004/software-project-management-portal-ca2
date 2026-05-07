<?php
// Task Management Module: user task progress update.
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

$flash = portal_get_flash();
$msg = $flash['message'] ?? "";
$msgClass = $flash['type'] ?? "";
$userId = (int) $_SESSION['user']['id'];

if (isset($_POST['update'])) {
    $taskId = (int) $_POST['task_id'];
    $progressInput = isset($_POST['progress_percent']) ? (int) $_POST['progress_percent'] : 0;
    $progressNote = trim($_POST['progress_note']);
    $userMessage = trim($_POST['user_message']);

    if ($progressInput < 0 || $progressInput > 100) {
        $msg = "Completion percentage must be between 0 and 100.";
        $msgClass = "error";
    } elseif ($taskId <= 0) {
        $msg = "Select a valid task.";
        $msgClass = "error";
    } else {
        $progressPercent = max(0, min(100, $progressInput));
        $status = portal_status_from_progress($progressPercent);
        // SQL: Check that this task belongs to the logged-in user.
        $taskCheck = $conn->query("SELECT task_id FROM tasks WHERE task_id = $taskId AND assigned_to = $userId");

        if (!$taskCheck || $taskCheck->num_rows === 0) {
            $msg = "You can only update tasks assigned to your account.";
            $msgClass = "error";
        } else {
            list($uploadOk, $uploadResult) = portal_save_task_document($_FILES['task_document'] ?? null);

            if (!$uploadOk) {
                $msg = $uploadResult;
                $msgClass = "error";
            } else {
                $currentAttachment = null;
                // SQL: Fetch the existing attachment path for this task.
                $attachmentResult = $conn->query("SELECT attachment_path FROM tasks WHERE task_id = $taskId AND assigned_to = $userId LIMIT 1");

                if ($attachmentResult && $attachmentResult->num_rows > 0) {
                    $attachmentRow = $attachmentResult->fetch_assoc();
                    $currentAttachment = $attachmentRow['attachment_path'];
                }

                $attachmentValue = $uploadResult === null ? $currentAttachment : $uploadResult;
                $noteValue = $progressNote === "" ? null : $progressNote;
                $userMessageValue = $userMessage === "" ? null : $userMessage;
                // SQL: Update task status, progress, note, message, and attachment.
                $stmt = $conn->prepare("
                    UPDATE tasks
                    SET status = ?, progress_percent = ?, progress_note = ?, user_message = ?, attachment_path = ?
                    WHERE task_id = ? AND assigned_to = ?
                ");

                if ($stmt && $stmt->bind_param("sisssii", $status, $progressPercent, $noteValue, $userMessageValue, $attachmentValue, $taskId, $userId) && $stmt->execute()) {
                    portal_set_flash("Task updated successfully.");
                    portal_redirect("update_task.php");
                } else {
                    $msg = "Error updating task.";
                    $msgClass = "error";
                }

                if ($stmt) {
                    $stmt->close();
                }
            }
        }
    }
}

// SQL: Fetch this user's tasks for the update dropdown.
$tasks = $conn->query("
    SELECT tasks.task_id, tasks.task_name, tasks.status, tasks.deadline, tasks.progress_percent, projects.project_name, teams.team_name
    FROM tasks
    LEFT JOIN projects ON tasks.project_id = projects.project_id
    LEFT JOIN teams ON tasks.team_id = teams.team_id
    WHERE assigned_to = $userId
    ORDER BY deadline ASC, task_id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Update Task</title>
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

        <form method="POST" enctype="multipart/form-data">
            <p class="portal-name">Software Project Management Portal</p>
            <h2>Update Task</h2>
            <p class="muted">Update the completion percentage for your assigned project work. Status is calculated automatically.</p>

            <?php if ($msg) { ?>
                <div class="alert <?php echo $msgClass; ?>"><?php echo $msg; ?></div>
            <?php } ?>

            <div class="field">
                <label for="task_id">Task</label>
                <select id="task_id" name="task_id" required>
                    <option value="">Select a task</option>
                    <?php if ($tasks) { ?>
                        <?php while ($task = $tasks->fetch_assoc()) { ?>
                            <option
                                value="<?php echo (int) $task['task_id']; ?>"
                                data-status="<?php echo htmlspecialchars($task['status']); ?>"
                                data-progress="<?php echo (int) $task['progress_percent']; ?>"
                            >
                                <?php echo htmlspecialchars($task['project_name'] ? $task['project_name'] . " - " : ""); ?><?php echo htmlspecialchars($task['team_name'] ? "[" . $task['team_name'] . "] " : ""); ?><?php echo htmlspecialchars($task['task_name']); ?> (<?php echo htmlspecialchars($task['status']); ?>, <?php echo (int) $task['progress_percent']; ?>%, due <?php echo htmlspecialchars($task['deadline']); ?>)
                            </option>
                        <?php } ?>
                    <?php } ?>
                </select>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" disabled>
                    <option>Pending</option>
                    <option>In Progress</option>
                    <option>Completed</option>
                </select>
                <p class="field-hint">This changes automatically from the completion percentage.</p>
            </div>

            <div class="field">
                <label for="progress_percent">Project Work Completion</label>
                <div class="progress-control">
                    <input type="range" id="progress_slider" min="0" max="100" value="0">
                    <input type="number" id="progress_percent" name="progress_percent" min="0" max="100" value="0" required>
                    <span id="progressLabel">0%</span>
                </div>
                <p class="field-hint">This percentage is shown to the admin and contributes to the project completion summary.</p>
            </div>

            <div class="field">
                <label for="progress_note">Progress Update</label>
                <textarea id="progress_note" name="progress_note" placeholder="Write a short update about the work completed."></textarea>
            </div>

            <div class="field">
                <label for="user_message">Message to Admin</label>
                <textarea id="user_message" name="user_message" placeholder="Optional reply or note for the admin."></textarea>
            </div>

            <div class="field">
                <label for="task_document">Upload Document</label>
                <input type="file" id="task_document" name="task_document" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg">
            </div>

            <div class="actions">
                <button type="submit" name="update">Update</button>
            </div>
        </form>
    </div>
    <script>
        (function () {
            var taskSelect = document.getElementById("task_id");
            var statusSelect = document.getElementById("status");
            var slider = document.getElementById("progress_slider");
            var numberInput = document.getElementById("progress_percent");
            var label = document.getElementById("progressLabel");

            function statusFromProgress(value) {
                if (value <= 0) {
                    return "Pending";
                }

                if (value >= 100) {
                    return "Completed";
                }

                return "In Progress";
            }

            function setProgress(value) {
                value = Math.max(0, Math.min(100, parseInt(value || "0", 10)));
                slider.value = value;
                numberInput.value = value;
                label.textContent = value + "%";
                statusSelect.value = statusFromProgress(value);
            }

            taskSelect.addEventListener("change", function () {
                var selected = taskSelect.options[taskSelect.selectedIndex];
                setProgress(selected ? selected.getAttribute("data-progress") : 0);
            });

            slider.addEventListener("input", function () {
                setProgress(slider.value);
            });

            numberInput.addEventListener("input", function () {
                setProgress(numberInput.value);
            });
        })();
    </script>
    <?php echo portal_theme_script(); ?>
    <?php echo portal_auto_logout_script("../auto_logout.php"); ?>
</body>
</html>
