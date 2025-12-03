<?php
/**
 * File Upload Handler
 * Opus3D Admin Panel
 */

require_once 'config.php';
requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$type = $_POST['type'] ?? 'product'; // product, gallery

// Validate file
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo file non supportato. Usa JPG, PNG, GIF o WEBP']);
    exit;
}

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File troppo grande. Massimo 5MB']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/uploads/' . $type . '/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('img_', true) . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Return URL using config variable
    $url = UPLOADS_URL . '/' . $type . '/' . $filename;
    
    logAdminAction('file_uploaded', null, null, "File caricato: $filename");
    
    echo json_encode([
        'success' => true,
        'url' => $url,
        'filename' => $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Errore durante il caricamento del file']);
}

