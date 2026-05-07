<?php
// Database Module: shared database connection and schema bootstrap.
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'ca2';
$dbPort = (int) (getenv('DB_PORT') ?: 3306);

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($dbHost, $dbUser, $dbPass, '', $dbPort);

if ($conn->connect_error) {
    $message = 'Database connection failed.';

    if ((int) $conn->connect_errno === 2002) {
        $message .= ' MySQL is not accepting connections on ' . $dbHost . ':' . $dbPort . '. Start MySQL in XAMPP and confirm the port is correct.';
    } else {
        $message .= ' ' . $conn->connect_error;
    }

    die($message);
}

$safeDbName = preg_replace('/[^A-Za-z0-9_]/', '', $dbName);
if ($safeDbName === '') {
    die('Database connection failed. Invalid database name.');
}

// SQL: Create the project database if it does not already exist.
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die('Database setup failed. ' . $conn->error);
}

if (!$conn->select_db($safeDbName)) {
    die('Database selection failed. ' . $conn->error);
}

$conn->set_charset('utf8mb4');

// SQL: Create the users table for login accounts and roles.
$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
        PRIMARY KEY (id),
        UNIQUE KEY users_email_unique (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// SQL: Create the projects table for project details and timelines.
$conn->query("
    CREATE TABLE IF NOT EXISTS projects (
        project_id INT NOT NULL AUTO_INCREMENT,
        project_name VARCHAR(150) NOT NULL,
        description TEXT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        PRIMARY KEY (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// SQL: Create the tasks table for assignments, status, progress, and files.
$conn->query("
    CREATE TABLE IF NOT EXISTS tasks (
        task_id INT NOT NULL AUTO_INCREMENT,
        project_id INT DEFAULT NULL,
        team_id INT DEFAULT NULL,
        assigned_to INT NOT NULL,
        task_name VARCHAR(255) NOT NULL,
        status ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
        deadline DATE NOT NULL,
        progress_percent INT NOT NULL DEFAULT 0,
        progress_note TEXT DEFAULT NULL,
        admin_message TEXT DEFAULT NULL,
        user_message TEXT DEFAULT NULL,
        attachment_path VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (task_id),
        KEY tasks_project_id_index (project_id),
        KEY tasks_team_id_index (team_id),
        KEY tasks_assigned_to_index (assigned_to),
        KEY tasks_deadline_index (deadline)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// SQL: Create the teams table for team names and leaders.
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

// SQL: Create the team_members table for users inside each team.
$conn->query("
    CREATE TABLE IF NOT EXISTS team_members (
        team_id INT NOT NULL,
        user_id INT NOT NULL,
        PRIMARY KEY (team_id, user_id),
        KEY team_members_user_id_index (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// SQL: Create the team_messages table for team chat messages.
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

$adminEmail = 'admin@portal.local';
// SQL: Check whether the default admin account already exists.
$adminCheck = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if ($adminCheck) {
    $adminCheck->bind_param('s', $adminEmail);
    $adminCheck->execute();
    $adminResult = $adminCheck->get_result();

    if (!$adminResult || $adminResult->num_rows === 0) {
        $adminName = 'Administrator';
        $adminPassword = 'admin123';
        $adminRole = 'admin';
        // SQL: Insert the default admin account for first login.
        $adminInsert = $conn->prepare('INSERT INTO users(name, email, password, role) VALUES(?, ?, ?, ?)');

        if ($adminInsert) {
            $adminInsert->bind_param('ssss', $adminName, $adminEmail, $adminPassword, $adminRole);
            $adminInsert->execute();
            $adminInsert->close();
        }
    }

    $adminCheck->close();
}
?>
