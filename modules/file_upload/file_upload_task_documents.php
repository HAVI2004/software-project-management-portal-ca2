<?php
// File Upload Module: task document upload validation and storage.
function portal_upload_directory() {
    return __DIR__ . "/../../uploads/task_docs";
}

function portal_save_task_document($file) {
    if (!isset($file) || !is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [true, null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, "Document upload failed."];
    }

    $allowedExtensions = ["pdf", "doc", "docx", "txt", "png", "jpg", "jpeg"];
    $originalName = basename((string) $file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        return [false, "Only PDF, DOC, DOCX, TXT, PNG, JPG, and JPEG files are allowed."];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return [false, "Document size must be 5MB or less."];
    }

    $uploadDir = portal_upload_directory();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        return [false, "Unable to create the upload folder."];
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
    $fileName = time() . "_" . $safeName;
    $destination = $uploadDir . "/" . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [false, "Unable to save the uploaded document."];
    }

    return [true, "uploads/task_docs/" . $fileName];
}
?>
