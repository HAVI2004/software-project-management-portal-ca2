<?php
// Authentication Module: beacon logout endpoint.
include(__DIR__ . "/modules/authentication/auth_session.php");
portal_start_session();
session_unset();
session_destroy();

http_response_code(204);
exit();
