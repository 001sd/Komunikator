<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    exit;
}

$user = mysqli_real_escape_string($link, $_SESSION['username']);
$recipient = mysqli_real_escape_string($link, $_POST['recipient'] ?? '');
$post = trim($_POST['post'] ?? '');

if (empty($recipient) || $recipient === '_ALL_') { exit; }

// ZAPIS 1: Najpierw przetwarzamy tekst
if (!empty($post)) {
    $postEsc = mysqli_real_escape_string($link, $post);
    $query = "INSERT INTO messages (message, user, recipient) VALUES ('$postEsc', '$user', '$recipient')";
    mysqli_query($link, $query);
}

// ZAPIS 2: Potem przetwarzamy pliki w pętli (dzięki 'multiple')
if (isset($_FILES['fileToUpload'])) {
    $total_files = count($_FILES['fileToUpload']['name']);
    
    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES['fileToUpload']['error'][$i] == 0) {
            
            $target_dir = "users_cloud/" . $user;
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            // Generujemy osobną unikalną nazwę dla KAŻDEGO z plików
            $filename = uniqid() . '_' . basename($_FILES["fileToUpload"]["name"][$i]);
            $target_file = $target_dir . "/" . $filename;
            
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"][$i], $target_file)) {
                $targetEsc = mysqli_real_escape_string($link, $target_file);
                mysqli_query($link, "INSERT INTO messages (message, user, recipient) VALUES ('$targetEsc', '$user', '$recipient')");
            }
        }
    }
}
?>