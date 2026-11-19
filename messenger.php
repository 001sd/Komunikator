<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$me = mysqli_real_escape_string($link, $_SESSION['username']);
$is_admin = $_SESSION['is_admin'] ?? 0;

// Pobranie listy innych użytkowników, posortowanej po dacie ostatniej wspólnej wiadomości
$users_query = mysqli_query($link, "
    SELECT u.username, u.avatar,
           (SELECT MAX(datetime) FROM messages 
            WHERE (user = u.username AND recipient = '$me') 
               OR (user = '$me' AND recipient = u.username)) as last_interaction
    FROM users u
    WHERE u.username != '$me'
    ORDER BY last_interaction IS NULL, last_interaction DESC, u.username ASC
");
?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/sd.png">
    <title>Komunikator P2P</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="twoj_css.css?v=2">
    <style>
        .messenger-wrapper { height: 75vh; min-height: 500px; border: 1px solid #333; border-radius: 12px; overflow: hidden; background-color: #000; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .sidebar { background-color: #121212; border-right: 1px solid #282828; display: flex; flex-direction: column; height: 100%; }
        .chat-area { background-color: #0a0a0a; display: flex; flex-direction: column; height: 100%; }
        
        .messenger-header { height: 60px; padding: 0 20px; background-color: #212529; border-bottom: 1px solid #333; display: flex; align-items: center; flex-shrink: 0; }
        
        .contact-item { cursor: pointer; padding: 15px; border-bottom: 1px solid #282828; transition: 0.2s; display: flex; align-items: center; }
        .contact-item:hover { background-color: #1a1a1a; }
        .contact-item.active { background-color: #0d6efd !important; color: #fff !important; border-color: #0d6efd; }
        .contact-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 15px; background-color: #333; flex-shrink: 0; }
        
        .chat-box { flex: 1 1 0; overflow-y: auto; padding: 20px; display: flex; flex-direction: column-reverse; }
        .msg-bubble { max-width: 70%; padding: 12px 18px; border-radius: 20px; margin-bottom: 15px; font-size: 0.95rem; }
        .msg-sent { background-color: #0d6efd; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-received { background-color: #2b3035; color: #e5e5e5; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #333; }
        .msg-admin { background-color: #dc3545; color: white; align-self: center; border-radius: 15px; text-align: center; }
        
        /* Ikonka usuwania wiadomości i wyrównanie tekstu meta */
        .msg-meta { font-size: 0.75rem; opacity: 0.7; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
        .msg-sent .msg-meta { justify-content: flex-end; }
        .msg-del-btn { cursor: pointer; opacity: 0.5; transition: 0.2s; }
        .msg-del-btn:hover { opacity: 1; color: #ffcccc; transform: scale(1.2); }

        .chat-input-area { background-color: #121212; padding: 15px; border-top: 1px solid #282828; flex-shrink: 0; display: flex; flex-direction: column; }
        .chat-input { border-radius: 30px; padding-left: 20px; background-color: #2a2a2a; border: none; color: white; }
        .chat-input:focus { background-color: #333; color: white; box-shadow: none; border: none; }
        .btn-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; flex-shrink: 0; }
        
        input[type="file"]::file-selector-button { display: none; }
        input[type="file"] { color: transparent; width: 40px; padding: 0; }
        
        .btn-close-chat { color: #888; transition: 0.2s; cursor: pointer; text-decoration: none; }
        .btn-close-chat:hover { color: #fff; transform: scale(1.1); }
    </style>
</head>
<body>

<main class="container mt-4 mb-5">
    <div class="row g-0 messenger-wrapper mt-3">
        
        <!-- KOLUMNA LEWA -->
        <div class="col-md-4 sidebar">
            <div class="messenger-header justify-content-between text-white fw-bold shadow-sm">
                <span><i class="bi bi-chat-left-text-fill me-2"></i> Konwersacje</span>
                <?php if($is_admin) echo '<span class="badge bg-danger">ADMIN</span>'; ?>
            </div>
            <div class="flex-grow-1 overflow-auto">
                <?php if($is_admin): ?>
                <div class="contact-item text-danger fw-bold" id="contact__ALL_" onclick="selectUser('_ALL_', 'Globalny podgląd (Admin)')">
                    <div class="contact-avatar d-flex align-items-center justify-content-center bg-danger text-white"><i class="bi bi-globe"></i></div>
                    <div>Globalny podgląd</div>
                </div>
                <?php endif; ?>
                
                <?php while($u = mysqli_fetch_assoc($users_query)): 
                    $uname = htmlspecialchars($u['username']);
                    $uavatar = $u['avatar'] ? "media/avatars/".htmlspecialchars(basename($u['avatar'])) : "default-avatar.svg";
                ?>
                <div class="contact-item" id="contact_<?php echo $uname; ?>" onclick="selectUser('<?php echo $uname; ?>', '<?php echo $uname; ?>')">
                    <img src="<?php echo $uavatar; ?>" class="contact-avatar" alt="Avatar">
                    <div class="fw-bold"><?php echo $uname; ?></div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- KOLUMNA PRAWA -->
        <div class="col-md-8 chat-area">
            <div class="messenger-header justify-content-between text-white fw-bold shadow-sm" style="border-left: 1px solid #333;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-person-circle fs-4 me-3 text-secondary"></i>
                    <span id="chatHeader" class="fs-5">Wybierz kontakt...</span>
                </div>
                <a href="home.php" class="btn-close-chat" title="Zamknij komunikator"><i class="bi bi-x-lg fs-5"></i></a>
            </div>
            
            <div id="chatBox" class="chat-box">
                <div class="text-center text-muted my-auto align-self-center">
                    <i class="bi bi-chat-dots fs-1 d-block mb-2"></i> Kliknij kontakt po lewej.
                </div>
            </div>

            <div class="chat-input-area" id="inputArea" style="opacity: 0.5; pointer-events: none;">
                
                <div id="filePreview" class="d-none align-items-center bg-dark p-2 rounded border border-secondary mb-2" style="max-width: fit-content;">
                    <i class="bi bi-file-earmark-check-fill text-success me-2"></i>
                    <span id="fileNameDisplay" class="text-info small me-3 fw-bold"></span>
                    <button type="button" class="btn-close btn-close-white" style="font-size: 0.6rem;" onclick="clearFilePreview()"></button>
                </div>

                <form id="chatForm" enctype="multipart/form-data">
                    <input type="hidden" name="recipient" id="recipientInput" value="">
                    
                    <div class="d-flex gap-2 align-items-center">
                        <div class="position-relative">
                            <label for="fileUpload" class="btn btn-secondary btn-circle" style="cursor: pointer;" title="Załącz plik(i)">
                                <i class="bi bi-images fs-5"></i>
                            </label>
                            <input type="file" name="fileToUpload[]" id="fileUpload" class="position-absolute top-0 start-0 opacity-0 w-100 h-100" style="cursor: pointer;" accept=".jpg,.jpeg,.png,.gif,.mp3,.mp4" multiple>
                        </div>
                        
                        <input type="text" name="post" class="form-control chat-input flex-grow-1" placeholder="Napisz wiadomość..." maxlength="255" autocomplete="off">
                        <button type="submit" class="btn btn-primary btn-circle"><i class="bi bi-send-fill"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
    let currentEvtSource = null;

    const fileInput = document.getElementById('fileUpload');
    const filePreview = document.getElementById('filePreview');
    const fileNameDisplay = document.getElementById('fileNameDisplay');

    fileInput.addEventListener('change', function() {
        if(this.files && this.files.length > 0) {
            if (this.files.length === 1) {
                fileNameDisplay.innerText = this.files[0].name;
            } else {
                fileNameDisplay.innerText = "Wybrano plików: " + this.files.length;
            }
            filePreview.classList.remove('d-none');
            filePreview.classList.add('d-flex');
        } else {
            clearFilePreview();
        }
    });

    function clearFilePreview() {
        fileInput.value = '';
        filePreview.classList.add('d-none');
        filePreview.classList.remove('d-flex');
        fileNameDisplay.innerText = '';
    }

    function deleteMessage(idk) {
        if(confirm('Na pewno usunąć tę wiadomość dla obu stron?')) {
            let formData = new FormData();
            formData.append('idk', idk);
            
            fetch('messenger_del.php', {
                method: 'POST',
                body: formData
            });
        }
    }

    function selectUser(username, displayName) {
        document.querySelectorAll('.contact-item').forEach(el => el.classList.remove('active'));
        document.getElementById('contact_' + username).classList.add('active');
        document.getElementById('chatHeader').innerText = displayName;
        document.getElementById('recipientInput').value = username;
        
        if(username === '_ALL_') {
            document.getElementById('inputArea').style.opacity = '0.5';
            document.getElementById('inputArea').style.pointerEvents = 'none';
        } else {
            document.getElementById('inputArea').style.opacity = '1';
            document.getElementById('inputArea').style.pointerEvents = 'auto';
        }

        if (currentEvtSource) currentEvtSource.close();
        
        const chatBox = document.getElementById('chatBox');
        chatBox.innerHTML = '<div class="spinner-border text-secondary align-self-center my-auto" role="status"></div>';
        
        currentEvtSource = new EventSource('messenger_sse.php?chat_with=' + encodeURIComponent(username));
        
        currentEvtSource.onmessage = function(e) {
            const messages = JSON.parse(e.data);
            
            const spinner = chatBox.querySelector('.spinner-border');
            if (spinner) spinner.remove();

            if (messages.length === 0) {
                chatBox.innerHTML = '<div class="text-center text-muted my-auto align-self-center empty-msg">Brak wiadomości. Napisz jako pierwszy!</div>';
                return;
            }

            const emptyMsg = chatBox.querySelector('.empty-msg');
            if (emptyMsg) emptyMsg.remove();

            // 1. Usuń z ekranu wiadomości skasowane w bazie
            const newIds = new Set(messages.map(m => String(m.idk)));
            chatBox.querySelectorAll('.msg-bubble').forEach(el => {
                if (!newIds.has(el.dataset.idk)) {
                    el.remove();
                }
            });

            // 2. Dodaj tylko nowe wiadomości (stare zostają nietknięte)
            const existingIds = new Set(
                Array.from(chatBox.querySelectorAll('.msg-bubble')).map(el => el.dataset.idk)
            );
            
            messages.forEach(msg => {
                if (!existingIds.has(String(msg.idk))) {
                    chatBox.insertAdjacentHTML('afterbegin', msg.html);
                }
            });
        };
        
        // Nie zamykamy połączenia w onerror, żeby EventSource sam się wznawiał po ewentualnym timeoucie PHP
        currentEvtSource.onerror = function() {
            console.log('Ponowne łączenie SSE...');
        };
    }

    document.getElementById('chatForm').addEventListener('submit', function(e) {
        e.preventDefault(); 
        if(!document.getElementById('recipientInput').value) return;
        
        const hasText = this.elements['post'].value.trim() !== '';
        const hasFile = fileInput.files.length > 0;
        if(!hasText && !hasFile) return;
        
        let formData = new FormData(this);
        
        fetch('messenger_add.php', { method: 'POST', body: formData })
        .then(() => {
            this.elements['post'].value = '';
            clearFilePreview(); 
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>