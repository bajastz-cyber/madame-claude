<?php
/**
 * VoAnh - Upload de fichiers (images, PDF)
 */
require_once dirname(__FILE__) . '/madameclaude2/config.php';
require_once dirname(__FILE__) . '/madameclaude2/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$uploadDir = ROOT_PATH . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Nettoyer les vieux fichiers (> 1 heure)
foreach (glob($uploadDir . '*') as $f) {
    if (filemtime($f) < time() - 3600) @unlink($f);
}

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Erreur upload']);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Type non autorisé (images et PDF uniquement)']);
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Fichier trop grand (max 10MB)']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$newName = uniqid('upload_', true) . '.' . $ext;
$dest = $uploadDir . $newName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'error' => 'Impossible de sauvegarder le fichier']);
    exit;
}

// Convertir en base64 pour l'API Mistral
$base64 = base64_encode(file_get_contents($dest));

echo json_encode([
    'success' => true,
    'filename' => $file['name'],
    'mime' => $mime,
    'base64' => $base64,
    'is_image' => strpos($mime, 'image/') === 0,
    'is_pdf' => $mime === 'application/pdf',
]);
