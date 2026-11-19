<?php
session_start();
require_once 'db.php';

$me = mysqli_real_escape_string($link, $_SESSION['username'] ?? '');
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : 0;
$chat_with = mysqli_real_escape_string($link, $_GET['chat_with'] ?? '');

session_write_close(); 

if (empty($me) || empty($chat_with)) { exit; }

header("Content-Type: text/event-stream");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$last_hash = null; // Zapamiętuje ostatni stan rozmowy

while (!connection_aborted()) {
    
    if ($is_admin && $chat_with === '_ALL_') {
        $sql = "SELECT * FROM messages ORDER BY idk ASC";
    } else {
        $sql = "SELECT * FROM messages 
                WHERE (user = '$me' AND recipient = '$chat_with') 
                   OR (user = '$chat_with' AND recipient = '$me') 
                ORDER BY idk ASC";
    }
    
    $result = mysqli_query($link, $sql);
    $messages = [];
    $ids = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $idk = $row['idk'];
        $ids[] = $idk;
        $msg = $row['message'];
        $sender = htmlspecialchars($row['user']);
        $receiver = htmlspecialchars($row['recipient']);
        $time = date('H:i', strtotime($row['datetime']));
        
        $delete_icon = "";
        if ($sender === $me) {
            $delete_icon = "<i class='bi bi-trash-fill msg-del-btn ms-2' title='Cofnij wiadomość' onclick='deleteMessage($idk)'></i>";
        }
        
        if ($is_admin && $chat_with === '_ALL_') {
            $css_class = "msg-admin";
            $meta = "<span class='fw-bold'>$sender &rarr; $receiver</span> <span>$time</span> $delete_icon";
        } elseif ($sender === $me) {
            $css_class = "msg-sent";
            $meta = "<span>$time</span> $delete_icon";
        } else {
            $css_class = "msg-received";
            $meta = "<span class='fw-bold'>$sender</span> <span>$time</span>";
        }
        
        $ext = strtolower(pathinfo($msg, PATHINFO_EXTENSION));
        $content = "";
        
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            $content = "<img src='$msg' style='max-width: 100%; border-radius: 8px;'>";
        } elseif ($ext === 'mp4') {
            $content = "<video src='$msg' autoplay muted controls style='max-width: 100%; border-radius: 8px;'></video>";
        } elseif ($ext === 'mp3') {
            $content = "<audio src='$msg' controls style='width: 250px;'></audio>";
        } else {
            $content = nl2br(htmlspecialchars($msg));
        }
        
        $bubble_html = "<div class='msg-bubble $css_class' data-idk='$idk'>"
                     . "<div class='msg-meta'>$meta</div>"
                     . "<div>$content</div>"
                     . "</div>";
                     
        $messages[] = [
            'idk' => $idk,
            'html' => $bubble_html
        ];
    }
    
    // Sprawdzamy czy coś się zmieniło (nowa lub usunięta wiadomość)
    $current_hash = md5(implode(',', $ids));
    
    if ($current_hash !== $last_hash) {
        $last_hash = $current_hash;
        echo "data: " . json_encode($messages) . "\n\n";
    } else {
        // Pusty komentarz SSE (nie wywołuje onmessage w JS, tylko podtrzymuje połączenie)
        echo ": keepalive\n\n";
    }
    
    while (ob_get_level() > 0) { ob_end_flush(); }
    flush();
    sleep(1);
}
mysqli_close($link);
?>