    <?php
// Database Module: full SQL setup script.
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'ca2';
$dbPort = (int) (getenv('DB_PORT') ?: 3306);

$server = @new mysqli($dbHost, $dbUser, $dbPass, '', $dbPort);

if ($server->connect_error) {
    http_response_code(500);
    echo "Database server connection failed: " . $server->connect_error;
    exit();
}

$sqlPath = __DIR__ . '/project_portal.sql';
$sql = file_get_contents($sqlPath);

if ($sql === false) {
    http_response_code(500);
    echo "Unable to read SQL file: " . $sqlPath;
    exit();
}

// Respect the configured database name when it differs from the SQL default.
$sql = str_replace('`ca2`', '`' . $server->real_escape_string($dbName) . '`', $sql);

// SQL: Run the full database setup script from project_portal.sql.
if (!$server->multi_query($sql)) {
    http_response_code(500);
    echo "Database setup failed: " . $server->error;
    exit();
}

do {
    if ($result = $server->store_result()) {
        $result->free();
    }
} while ($server->more_results() && $server->next_result());

if ($server->error) {
    http_response_code(500);
    echo "Database setup finished with an error: " . $server->error;
    exit();
}

header('Content-Type: text/plain; charset=UTF-8');
echo "Database created successfully.\n";
echo "Database: " . $dbName . "\n";
echo "Admin email: admin@portal.local\n";
echo "Admin password: admin123\n";
?>
