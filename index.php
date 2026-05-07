<?php
// Authentication Module: login screen and login request handling.
include(__DIR__ . "/modules/authentication/auth_session.php");
portal_start_session();
include(__DIR__ . "/modules/database/database_connection.php");

$msg = "";
$msgClass = "";

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // SQL: Check whether the entered login email and password match a user.
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND password = ? LIMIT 1");
    $result = false;

    if ($stmt) {
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user'] = $user;

        header("Location: dashboard.php");
        exit();
    }

    if ($stmt) {
        $stmt->close();
    }

    $msg = "Invalid login details.";
    $msgClass = "error";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Software Project Management Portal | Login</title>
    <link rel="stylesheet" href="assets/css/style.css?v=22">
</head>
<body class="auth-page">
    <div class="page auth-wrap">
        <div class="auth-shell">
            <section class="auth-side">
                <div class="login-brand-mark">
                    <span><img src="lpulogo.jpg" alt="LPU Logo"></span>
                    <strong>Project Control Login</strong>
                </div>
                <p class="portal-name">Software Project Management Portal</p>
                <h1>Plan. Assign. Track. Complete.</h1>
                <p>One workspace for project admins and team members to manage software project delivery with clear task progress.</p>
            </section>

            <form method="POST" class="login-card">
                <div class="login-card-head">
                    <span class="login-icon">IN</span>
                    <div>
                        <p class="portal-name">Welcome Back</p>
                        <h2>Login to Portal</h2>
                    </div>
                </div>
                <p class="muted">Enter your credentials to open your project dashboard.</p>

                <?php if ($msg) { ?>
                    <div class="alert <?php echo $msgClass; ?>"><?php echo $msg; ?></div>
                <?php } ?>

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="actions">
                    <button type="submit" name="login">Login</button>
                </div>
                <p class="inline-link">Admin and user dashboards open automatically based on your role.</p>
            </form>
        </div>
    </div>
</body>
</html>
