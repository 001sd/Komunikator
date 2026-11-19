<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    exit;
}

$user = mysqli_real_escape_string($link, $_SESSION['username']);
$idk = (int)($_POST['idk'] ?? 0);

if ($idk > 0) {
    // 1. Sprawdzamy, czy wiadomość należy do zalogowanego użytkownika (zabezpieczenie)
    $query = mysqli_query($link, "SELECT message FROM messages WHERE idk = $idk AND user = '$user'");
    
    if (mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        $msg = $row['message'];
        
        // 2. Jeśli wiadomość jest ścieżką do przesłanego pliku w folderze nadawcy - FIZYCZNIE skasuj z serwera
        if (strpos($msg, "users_cloud/" . $user . "/") === 0 && file_exists($msg)) {
            unlink($msg);
        }
        
        // 3. Usuwamy rekord z bazy
        mysqli_query($link, "DELETE FROM messages WHERE idk = $idk AND user = '$user'");
    }
}
?>