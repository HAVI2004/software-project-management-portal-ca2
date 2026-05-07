<?php
// Project Management Module: create and list projects.
include(__DIR__ . "/../authentication/auth_session.php");
portal_start_session();
include(__DIR__ . "/../database/database_connection.php");
include(__DIR__ . "/../common/common_portal_helpers.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== "admin") {
    header("Location: ../index.php");
    exit();
}

$flash = portal_get_flash();
$msg = $flash['message'] ?? "";
$msgClass = $flash['type'] ?? "";

if (isset($_POST['create'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['desc']);
    $start = $_POST['start'];
    $end = $_POST['end'];

    if ($name === "" || $desc === "" || $start === "" || $end === "") {
        $msg = "Please fill all fields.";
        $msgClass = "error";
    } elseif ($end < $start) {
        $msg = "End date must be after start date.";
        $msgClass = "error";
    } else {
        // SQL: Insert a new project record.
        $stmt = $conn->prepare("INSERT INTO projects(project_name, description, start_date, end_date) VALUES(?, ?, ?, ?)");

        if ($stmt && $stmt->bind_param("ssss", $name, $desc, $start, $end) && $stmt->execute()) {
            portal_set_flash("Project created successfully.");
            portal_redirect("create_project.php");
        } else {
            $msg = "Error creating project. " . $conn->error;
            $msgClass = "error";
        }

        if ($stmt) {
            $stmt->close();
        }
    }
}

// SQL: Fetch projects to show the existing project list.
$projects = $conn->query("SELECT project_id, project_name, description, start_date, end_date FROM projects ORDER BY project_id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Create Project</title>
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
            <h2>Create Project</h2>
            <p class="muted">Set up a project with a clear description and timeline.</p>

            <?php if ($msg) { ?>
                <div class="alert <?php echo $msgClass; ?>"><?php echo $msg; ?></div>
            <?php } ?>

            <div class="field">
                <label for="name">Project Name</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="field">
                <label for="desc">Description</label>
                <textarea id="desc" name="desc" required></textarea>
            </div>

            <div class="field">
                <label for="start">Start Date</label>
                <input type="date" id="start" name="start" required>
            </div>

            <div class="field">
                <label for="end">End Date</label>
                <input type="date" id="end" name="end" required>
            </div>

            <div class="actions">
                <button type="submit" name="create">Create</button>
            </div>
        </form>

        <div class="task-list">
            <div class="task-card">
                <h3>Existing Projects</h3>
                <?php if ($projects && $projects->num_rows > 0) { ?>
                    <?php while ($project = $projects->fetch_assoc()) { ?>
                        <p><strong>ID:</strong> <?php echo htmlspecialchars($project['project_id']); ?></p>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($project['project_name']); ?></p>
                        <p><strong>Description:</strong> <?php echo htmlspecialchars($project['description']); ?></p>
                        <p><strong>Start:</strong> <?php echo htmlspecialchars($project['start_date']); ?></p>
                        <p><strong>End:</strong> <?php echo htmlspecialchars($project['end_date']); ?></p>
                        <hr>
                    <?php } ?>
                <?php } else { ?>
                    <p>No projects created yet.</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php echo portal_theme_script(); ?>
    <?php echo portal_auto_logout_script("../auto_logout.php"); ?>
</body>
</html>
