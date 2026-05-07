<?php
// Authentication Module: logout endpoint.
include(__DIR__ . "/auth_session.php");
portal_start_session();
session_unset();        
session_destroy();      

header("Location: index.php"); 
exit();
