<?php
header('Content-Type: application/json');

$email = isset($_POST['email']) ? preg_replace('/[^a-zA-Z0-9._-]/', '_', $_POST['email']) : '';

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email not provided']);
    exit;
}

$sessionFolder = 'photos/' . $email;

if (!is_dir($sessionFolder)) {
    echo json_encode(['success' => false, 'message' => 'Session folder not found']);
    exit;
}

// Get all photos from the session folder (excluding collage.jpg)
$photos = glob($sessionFolder . '/*.jpg');
$photos = array_filter($photos, function($file) {
    return basename($file) !== 'collage.jpg';
});
sort($photos);

if (count($photos) < 4) {
    echo json_encode(['success' => false, 'message' => 'Not enough photos (found ' . count($photos) . ', need 4)']);
    exit;
}

$photos = array_slice($photos, 0, 4);

// Create templates folder if it doesn't exist
if (!is_dir('templates')) {
    mkdir('templates', 0777, true);
}

// Load template
$templatePath = 'templates/collage_template.png';
if (!file_exists($templatePath)) {
    echo json_encode(['success' => false, 'message' => 'Template not found at: ' . realpath('templates')]);
    exit;
}

// Check if GD library is available
if (!extension_loaded('gd')) {
    echo json_encode(['success' => false, 'message' => 'GD library not available']);
    exit;
}

$collage = imagecreatefrompng($templatePath);
if (!$collage) {
    echo json_encode(['success' => false, 'message' => 'Failed to load template image']);
    exit;
}

imagesavealpha($collage, true);

// Define where each photo goes on the template (your exact positions)
$photoPositions = [
    ['x' => 63.7, 'y' => 331.4, 'width' => 462.4, 'height' => 615.9],       // Picture 1
    ['x' => 553.9, 'y' => 331.4, 'width' => 462.4, 'height' => 615.9],       // Picture 2
    ['x' => 63.7, 'y' => 972.7, 'width' => 462.4, 'height' => 615.9],        // Picture 3
    ['x' => 553.9, 'y' => 972.7, 'width' => 462.4, 'height' => 615.9]         // Picture 4
];

foreach ($photos as $index => $photoPath) {
    $photo = imagecreatefromjpeg($photoPath);
    if (!$photo) {
        continue; // Skip if photo can't be loaded
    }
    
    $photoW = imagesx($photo);
    $photoH = imagesy($photo);
    
    $pos = $photoPositions[$index];
    
    // Resize photo to fit template slot
    $resized = imagecreatetruecolor($pos['width'], $pos['height']);
    imagecopyresampled($resized, $photo, 0, 0, 0, 0, $pos['width'], $pos['height'], $photoW, $photoH);
    
    // Paste onto template (round coordinates to integers)
    imagecopy($collage, $resized, (int)$pos['x'], (int)$pos['y'], 0, 0, $pos['width'], $pos['height']);
    imagedestroy($photo);
    imagedestroy($resized);
}

// Save final collage
$collageFilename = $sessionFolder . '/collage.jpg';
$saved = imagejpeg($collage, $collageFilename, 90);
imagedestroy($collage);

if (!$saved) {
    echo json_encode(['success' => false, 'message' => 'Failed to save collage']);
    exit;
}

if (!file_exists($collageFilename)) {
    echo json_encode(['success' => false, 'message' => 'Collage file was not created']);
    exit;
}

echo json_encode(['success' => true, 'collage' => 'collage.jpg', 'folderName' => $email, 'path' => $collageFilename]);
?>
