<?php
/**
 * VoAnh - Téléchargement de fichiers générés
 */
$content = $_POST['content'] ?? '';
$filename = $_POST['filename'] ?? 'fichier.html';
$type = $_POST['type'] ?? 'text/html';

// Sécuriser le nom de fichier
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

header('Content-Type: ' . $type . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
echo $content;
