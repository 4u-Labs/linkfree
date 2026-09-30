<?php
// dashboard.php - VERSÃƒO STANDALONE
// LOCAL: /raiz_do_projeto/dashboard.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) { 
    header("Location: " . BASE_URL . "/login.php"); 
    exit; 
}

$pageTitle = "Meu Painel";
$userId = getCurrentUserId();
$links = getUserLinks($userId);
$userTag = getCurrentUserTag();
$username = $_SESSION['username'];

// --- PROCESSAMENTO ---
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Upload de Avatar
    if (isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $res = handleAvatarUpload($userId, $_FILES['avatar']);
            if ($res['success']) {
                $msg = "Avatar atualizado com sucesso!";
                $_SESSION['avatar_filename'] = $res['filename']; 
            } else {
                $err = "Erro no upload: " . $res['message'];
            }
        } else {
            $err = "Selecione um arquivo para enviar.";
        }
    }
    
    // 2. Atualizar Perfil
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $res = updateUserProfile($userId, $_POST['username'], $_POST['tag']);
        if ($res === true) {
             $msg = "Perfil atualizado com sucesso!"; 
             $userTag = $_POST['tag']; 
             $username = $_POST['username'];
        } else {
            $err = $res;
        }
    }
}

// URL do Avatar (Local ou Google OAuth)
$avatarFile = $_SESSION['avatar_filename'] ?? DEFAULT_AVATAR_FILENAME;
$avatarUrl = getUserAvatarUrl($avatarFile);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Link Free</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    
    <!-- HEADER -->
    <header class="main-header" style="position:fixed; top:0; width:100%; z-index:1000;">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center; height:75px;">
            <a href="<?php echo BASE_URL; ?>/index.html" class="logo" style="text-decoration:none;">< Link Free /></a>
            <nav>
                <ul style="display:flex; gap:20px; list-style:none;">
                    <li><a href="<?php echo BASE_URL; ?>/dashboard.php">Painel</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout.php">Sair</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- MAIN CONTENT -->
    <main class="container" style="padding-top: 100px;">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <h2 style="margin:0;">Painel de Controle</h2>
            <a href="<?php echo BASE_URL; ?>/profile.php?tag=<?php echo htmlspecialchars($userTag); ?>" target="_blank" class="btn btn-primary">Ver Meu Link Free</a>
        </div>

        <?php if ($msg): ?>
            <div class="success-message" style="margin-bottom: 20px;">
                âœ… <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="error-message" style="margin-bottom: 20px;">
                âŒ <?php echo htmlspecialchars($err); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
            
            <!-- CARD PERFIL -->
            <div class="auth-form" style="max-width: 100%;">
                <h3>Meu Perfil</h3>
                <div style="text-align:center; margin-bottom: 20px;">
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" 
                         style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid var(--border-color); display:block; margin: 0 auto;">
                    
                    <!-- FORMULÃRIO DE UPLOAD -->
                    <form method="POST" enctype="multipart/form-data" style="margin-top:15px;">
                        <input type="hidden" name="action" value="upload_avatar">
                        <input type="file" name="avatar" accept="image/*" required 
                               style="font-size:0.85em; color: var(--text-muted); display:block; margin:10px auto; max-width:100%;">
                        <button type="submit" class="btn btn-secondary" style="margin-top:10px; padding:8px 20px; font-size: 0.9em;">
                            <i class="fas fa-camera"></i> Mudar Foto
                        </button>
                    </form>
                </div>
                
                <hr style="border-color: var(--border-color); margin: 20px 0;">
                
                <!-- FORMULÃRIO DADOS DO PERFIL -->
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>UsuÃ¡rio:</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Tag (URL):</label>
                        <input type="text" name="tag" value="<?php echo htmlspecialchars($userTag); ?>" required pattern="[a-z0-9-]+" title="Apenas letras minÃºsculas, nÃºmeros e hÃ­fen">
                        <small style="color:var(--text-muted); display:block; margin-top:5px;">
                            Seu link: <?php echo BASE_URL; ?>/profile.php?tag=<b><?php echo htmlspecialchars($userTag); ?></b>
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar Dados</button>
                </form>
            </div>

            <!-- CARD ADICIONAR LINK -->
            <div class="auth-form" style="max-width: 100%;">
                <h3>Adicionar Novo Link</h3>
                <form id="add-link-form">
                    <input type="hidden" name="action" value="add_link">
                    <div class="form-group">
                        <label>TÃ­tulo:</label>
                        <input type="text" name="title" required placeholder="Ex: Meu Instagram">
                    </div>
                    <div class="form-group">
                        <label>URL de Destino:</label>
                        <input type="url" name="url" required placeholder="https://instagram.com/voce">
                    </div>
                    <div class="form-group">
                        <label>Ícone (opcional):</label>
                        <input type="text" name="icon" placeholder="Deixe vazio = detecta automático">
                        <small style="color:var(--text-muted); display:block; margin-top:5px;">
                            🔮 Detecta pela URL! Ou informe: fab fa-whatsapp, fas fa-envelope
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">Adicionar Link</button>
                </form>
            </div>

            <!-- LISTA DE LINKS -->
            <div style="grid-column: 1 / -1;">
                <h3>Seus Links Ativos</h3>
                <?php if (empty($links)): ?>
                    <p style="text-align:center; color: var(--text-muted); padding: 20px; border: 1px dashed var(--border-color); border-radius: 6px;">
                        VocÃª ainda nÃ£o tem links. Adicione o primeiro acima!
                    </p>
                <?php else: ?>
                    <ul id="link-list" style="list-style: none; padding: 0;">
                        <?php foreach ($links as $link): ?>
                            <li style="background: var(--card-bg); border: 1px solid var(--border-color); margin-bottom: 10px; padding: 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div style="display: flex; align-items: center; gap: 15px; flex: 1; min-width: 200px;">
                                    <div style="font-size: 1.5rem; color: var(--primary-color); width: 40px; text-align: center;">
                                        <i class="<?php echo htmlspecialchars($link['icon'] ?: 'fas fa-link'); ?>"></i>
                                    </div>
                                    <div>
                                        <strong style="color: var(--text-color); font-size: 1.1rem;"><?php echo htmlspecialchars($link['title']); ?></strong>
                                        <br>
                                        <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" style="font-size: 0.85rem; color: var(--text-muted); word-break: break-all;">
                                            <?php echo htmlspecialchars($link['url']); ?>
                                        </a>
                                    </div>
                                </div>
                                <div>
                                    <button class="btn-delete btn btn-secondary" data-id="<?php echo $link['id']; ?>" style="color: #ff4d4d; border-color: #ff4d4d; padding: 8px 15px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <footer class="footer-clean">
        <span>© 2026 4U.IA.BR</span>
    </footer>

    <!-- SCRIPTS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // Adicionar Link via AJAX
        const addLinkForm = document.getElementById('add-link-form');
        if (addLinkForm) {
            addLinkForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                submitBtn.disabled = true;

                fetch('<?php echo BASE_URL; ?>/ajax_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload(); 
                    } else {
                        alert('Erro: ' + data.message);
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao conectar com o servidor.');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        }

        // Deletar Link
        document.body.addEventListener('click', function(e) {
            const deleteBtn = e.target.closest('.btn-delete');
            if (deleteBtn) {
                if (!confirm('Tem certeza que deseja apagar este link?')) return;

                const linkId = deleteBtn.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'delete_link');
                formData.append('link_id', linkId);

                fetch('<?php echo BASE_URL; ?>/ajax_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) window.location.reload();
                    else alert('Erro ao excluir: ' + (data.message || 'Desconhecido'));
                });
            }
        });
    });
    </script>

</body>
</html>
