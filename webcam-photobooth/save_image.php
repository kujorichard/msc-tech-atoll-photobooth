<?php
if(isset($_POST['image']) && isset($_POST['email'])){
    $email = $_POST['email'];
    $data = $_POST['image'];

    // Sanitize email for folder name (keep only safe characters)
    $folderName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $email);
    $sessionFolder = 'photos/' . $folderName;

    // Create session folder if it doesn't exist
    if (!is_dir($sessionFolder)) {
        mkdir($sessionFolder, 0777, true);
        
        // Create email.txt file with the email address
        file_put_contents($sessionFolder . '/email.txt', $email);
    }

    // Remove prefix (data:image/jpeg;base64,)
    $data = str_replace('data:image/jpeg;base64,', '', $data);
    $data = str_replace(' ', '+', $data);

    // Save image with timestamp
    $filename = date('Ymd_His_') . uniqid() . '.jpg';
    $filepath = $sessionFolder . '/' . $filename;
    file_put_contents($filepath, base64_decode($data));
    
    echo json_encode([
        'success' => true,
        'message' => 'Photo saved',
        'file' => $filename,
        'folder' => $folderName
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing image or email data'
    ]);
}
?>
