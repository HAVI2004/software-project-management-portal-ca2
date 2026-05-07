<?php
// Common Components Module: reusable labels, flash messages, UI scripts, and shared helpers.
require_once __DIR__ . "/../file_upload/file_upload_task_documents.php";

function portal_role_label($role) {
    return $role === "admin" ? "Admin" : "User";
}

function portal_set_flash($message, $type = "success") {
    $_SESSION['portal_flash'] = [
        "message" => (string) $message,
        "type" => $type === "error" ? "error" : "success"
    ];
}

function portal_get_flash() {
    if (!isset($_SESSION['portal_flash']) || !is_array($_SESSION['portal_flash'])) {
        return null;
    }

    $flash = $_SESSION['portal_flash'];
    unset($_SESSION['portal_flash']);
    return $flash;
}

function portal_redirect($path) {
    header("Location: " . $path);
    exit();
}

function portal_theme_script() {
    return <<<HTML
<script>
    (function () {
        var body = document.body;
        var storageKey = "portal-theme";
        var savedTheme = localStorage.getItem(storageKey);
        var prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
        var toggle = document.getElementById("themeToggle");

        if (savedTheme === "dark" || (!savedTheme && prefersDark)) {
            body.classList.add("theme-dark");
        }

        if (!toggle) {
            return;
        }

        function updateThemeLabel() {
            var isDark = body.classList.contains("theme-dark");
            toggle.textContent = isDark ? "Light Mode" : "Dark Mode";
            toggle.setAttribute("aria-label", isDark ? "Switch to light mode" : "Switch to dark mode");
        }

        updateThemeLabel();

        toggle.addEventListener("click", function () {
            body.classList.toggle("theme-dark");
            localStorage.setItem(storageKey, body.classList.contains("theme-dark") ? "dark" : "light");
            updateThemeLabel();
        });
    })();
</script>
HTML;
}

function portal_status_class($status) {
    $safeStatus = strtolower(trim((string) $status));

    if ($safeStatus === "completed") {
        return "is-completed";
    }

    if ($safeStatus === "in progress") {
        return "is-progress";
    }

    return "is-pending";
}

function portal_column_exists($conn, $tableName, $columnName) {
    $safeTableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
    $safeColumnName = $conn->real_escape_string((string) $columnName);

    if ($safeTableName === '' || $safeColumnName === '') {
        return false;
    }

    // SQL: Check whether a column exists in a table.
    $result = $conn->query("SHOW COLUMNS FROM {$safeTableName} LIKE '{$safeColumnName}'");
    return $result && $result->num_rows > 0;
}

function portal_add_column_if_missing($conn, $tableName, $columnName, $definition) {
    $safeTableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
    $safeColumnName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);

    if ($safeTableName === '' || $safeColumnName === '' || portal_column_exists($conn, $safeTableName, $safeColumnName)) {
        return;
    }

    // SQL: Add a missing column to an existing table.
    $conn->query("ALTER TABLE {$safeTableName} ADD {$safeColumnName} {$definition}");
}

function ensure_task_progress_columns($conn) {
    portal_add_column_if_missing($conn, "tasks", "project_id", "INT DEFAULT NULL");
    portal_add_column_if_missing($conn, "tasks", "team_id", "INT DEFAULT NULL");
    portal_add_column_if_missing($conn, "tasks", "progress_percent", "INT NOT NULL DEFAULT 0");
    portal_add_column_if_missing($conn, "tasks", "progress_note", "TEXT NULL");
    portal_add_column_if_missing($conn, "tasks", "admin_message", "TEXT NULL");
    portal_add_column_if_missing($conn, "tasks", "user_message", "TEXT NULL");
    portal_add_column_if_missing($conn, "tasks", "attachment_path", "VARCHAR(255) NULL");

    // SQL: Force completed tasks to show 100 percent progress.
    $conn->query("UPDATE tasks SET progress_percent = 100 WHERE status = 'Completed' AND progress_percent <> 100");
    // SQL: Force pending tasks to show 0 percent progress.
    $conn->query("UPDATE tasks SET progress_percent = 0 WHERE status = 'Pending' AND progress_percent <> 0");
    // SQL: Keep in-progress tasks below 100 percent.
    $conn->query("UPDATE tasks SET progress_percent = 99 WHERE status = 'In Progress' AND progress_percent > 99");
    // SQL: Keep in-progress tasks above 0 percent.
    $conn->query("UPDATE tasks SET progress_percent = 1 WHERE status = 'In Progress' AND progress_percent < 1");
}

function ensure_team_tables($conn) {
    // SQL: Create the teams table if it is missing.
    $conn->query("
        CREATE TABLE IF NOT EXISTS teams (
            team_id INT NOT NULL AUTO_INCREMENT,
            team_name VARCHAR(150) NOT NULL,
            leader_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (team_id),
            KEY teams_leader_id_index (leader_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // SQL: Create the team_members table if it is missing.
    $conn->query("
        CREATE TABLE IF NOT EXISTS team_members (
            team_id INT NOT NULL,
            user_id INT NOT NULL,
            PRIMARY KEY (team_id, user_id),
            KEY team_members_user_id_index (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    portal_add_column_if_missing($conn, "tasks", "team_id", "INT DEFAULT NULL");
}

function ensure_team_messages_table($conn) {
    ensure_team_tables($conn);

    // SQL: Create the team_messages table if it is missing.
    $conn->query("
        CREATE TABLE IF NOT EXISTS team_messages (
            message_id INT NOT NULL AUTO_INCREMENT,
            team_id INT NOT NULL,
            sender_id INT NOT NULL,
            message_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (message_id),
            KEY team_messages_team_id_index (team_id),
            KEY team_messages_sender_id_index (sender_id),
            KEY team_messages_created_at_index (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function portal_deadline_state($deadline) {
    if (empty($deadline)) {
        return "No deadline";
    }

    $today = date("Y-m-d");

    if ($deadline < $today) {
        return "Overdue";
    }

    if ($deadline === $today) {
        return "Due Today";
    }

    return "On Track";
}

function portal_effective_progress($status, $progressPercent) {
    $progressPercent = (int) $progressPercent;

    if ($status === "Completed") {
        return 100;
    }

    if ($status === "Pending") {
        return 0;
    }

    return max(0, min(99, $progressPercent));
}

function portal_status_from_progress($progressPercent) {
    $progressPercent = max(0, min(100, (int) $progressPercent));

    if ($progressPercent === 0) {
        return "Pending";
    }

    if ($progressPercent === 100) {
        return "Completed";
    }

    return "In Progress";
}

function portal_dashboard_count($conn, $tableName) {
    $safeTableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
    if ($safeTableName === '') {
        return 0;
    }

    // SQL: Count total records in the selected dashboard table.
    $result = $conn->query("SELECT COUNT(*) AS total FROM {$safeTableName}");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int) $row['total'];
    }

    return 0;
}

function portal_progress_label($progressPercent) {
    return max(0, min(100, (int) $progressPercent)) . "% Complete";
}

function portal_auto_logout_script($logoutPath) {
    $logoutPath = htmlspecialchars($logoutPath, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<script>
    (function () {
        var navigationFlag = "portal-intentional-navigation";
        var logoutUrl = "{$logoutPath}";

        function markNavigation() {
            sessionStorage.setItem(navigationFlag, "1");
        }

        function clearNavigationFlag() {
            sessionStorage.removeItem(navigationFlag);
        }

        document.addEventListener("click", function (event) {
            var link = event.target.closest("a[href]");
            if (!link) {
                return;
            }

            var href = link.getAttribute("href") || "";
            if (href.startsWith("#") || href.toLowerCase().startsWith("javascript:")) {
                return;
            }

            markNavigation();
        });

        document.addEventListener("submit", function () {
            markNavigation();
        });

        window.addEventListener("pageshow", function () {
            clearNavigationFlag();
        });

        window.addEventListener("pagehide", function () {
            if (sessionStorage.getItem(navigationFlag) === "1") {
                return;
            }

            if (navigator.sendBeacon) {
                navigator.sendBeacon(logoutUrl);
            }
        });
    })();
</script>
HTML;
}
?>
