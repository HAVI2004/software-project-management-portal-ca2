<?php
// Authentication Module: shared session bootstrapping.
function portal_start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionDir = __DIR__ . "/../uploads/sessions";

    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0777, true);
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    session_start();
}
?>
