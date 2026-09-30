<?php
// profile.php - PÃ¡gina pÃºblica do perfil do usuÃ¡rio
// LOCAL: /raiz_do_projeto/profile.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$tag = $_GET['tag'] ?? '';
$user = getUserByTag($tag);

if (!$user) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>Perfil nÃ£o encontrado</title></head>";
    echo "<body style='background:#0a0f1f;color:#fff;text-align:center;padding-top:100px;font-family:sans-serif;'>";
    echo "<h1>404 - Perfil nÃ£o encontrado</h1>";
    echo "<p>O usuÃ¡rio <strong>@" . htmlspecialchars($tag) . "</strong> nÃ£o existe.</p>";
    echo "<a href='" . BASE_URL . "' style='color:#00aeff;'>Voltar ao inÃ­cio</a>";
    echo "</body></html>";
    exit;
}

$links = getLinksByTag($tag);
$avatarFile = $user['avatar_filename'] ?: DEFAULT_AVATAR_FILENAME;
$avatarUrl = getUserAvatarUrl($avatarFile);

// FunÃ§Ã£o para definir classe de cor por rede social
function getSocialClass($icon) {
    if (strpos($icon, 'facebook') !== false) return 'social-facebook';
    if (strpos($icon, 'instagram') !== false) return 'social-instagram';
    if (strpos($icon, 'twitter') !== false || strpos($icon, 'x-twitter') !== false) return 'social-twitter';
    if (strpos($icon, 'linkedin') !== false) return 'social-linkedin';
    if (strpos($icon, 'youtube') !== false) return 'social-youtube';
    if (strpos($icon, 'github') !== false) return 'social-github';
    if (strpos($icon, 'whatsapp') !== false) return 'social-whatsapp';
    if (strpos($icon, 'tiktok') !== false) return 'social-tiktok';
    if (strpos($icon, 'telegram') !== false) return 'social-telegram';
    if (strpos($icon, 'discord') !== false) return 'social-discord';
    if (strpos($icon, 'twitch') !== false) return 'social-twitch';
    if (strpos($icon, 'spotify') !== false) return 'social-spotify';
    return 'social-default';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> | Link Free</title>
    <meta name="description" content="PÃ¡gina de links de <?php echo htmlspecialchars($user['username']); ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        /* === Reset e VariÃ¡veis === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-color: #0a0f1f;
            --card-bg: #1a2035;
            --primary-color: #00aeff;
            --secondary-color: #8a4fff;
            --text-color: #e0e5f0;
            --text-muted: #a0aec0;
            --border-color: #3a415c;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            background-image: radial-gradient(var(--border-color) 0.5px, transparent 0.5px);
            background-size: 15px 15px;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
        }
        
        /* === Container Principal === */
        .profile-container {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* === Avatar === */
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            box-shadow: 0 0 20px rgba(0, 174, 255, 0.3);
            margin-bottom: 15px;
        }
        
        /* === Nome do UsuÃ¡rio === */
        .profile-username {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.5rem;
            color: var(--text-color);
            margin-bottom: 8px;
        }
        
        .profile-tag {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 30px;
        }
        
        /* === Lista de Links === */
        .profile-links {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        /* === BotÃ£o de Link === */
        .profile-link-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            padding: 16px 20px;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-color);
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .profile-link-btn:hover {
            transform: translateY(-3px);
            border-color: var(--primary-color);
            box-shadow: 0 5px 20px rgba(0, 174, 255, 0.2);
        }
        
        .profile-link-btn i {
            font-size: 1.4rem;
            width: 30px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .profile-link-btn span {
            flex: 1;
            text-align: left;
        }
        
        .profile-link-btn .arrow {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        /* === Cores por Rede Social === */
        .social-whatsapp:hover { border-color: #25D366; }
        .social-whatsapp i { color: #25D366; }
        
        .social-instagram:hover { border-color: #E4405F; }
        .social-instagram i { color: #E4405F; }
        
        .social-facebook:hover { border-color: #1877F2; }
        .social-facebook i { color: #1877F2; }
        
        .social-twitter:hover { border-color: #1DA1F2; }
        .social-twitter i { color: #1DA1F2; }
        
        .social-youtube:hover { border-color: #FF0000; }
        .social-youtube i { color: #FF0000; }
        
        .social-linkedin:hover { border-color: #0A66C2; }
        .social-linkedin i { color: #0A66C2; }
        
        .social-github:hover { border-color: #fff; }
        .social-github i { color: #fff; }
        
        .social-tiktok:hover { border-color: #ff0050; }
        .social-tiktok i { color: #ff0050; }
        
        .social-telegram:hover { border-color: #0088cc; }
        .social-telegram i { color: #0088cc; }
        
        .social-discord:hover { border-color: #5865F2; }
        .social-discord i { color: #5865F2; }
        
        .social-twitch:hover { border-color: #9146FF; }
        .social-twitch i { color: #9146FF; }
        
        .social-spotify:hover { border-color: #1DB954; }
        .social-spotify i { color: #1DB954; }
        
        .social-default i { color: var(--primary-color); }
        .social-default:hover { border-color: var(--primary-color); }
        
        /* === Mensagem Sem Links === */
        .no-links {
            text-align: center;
            color: var(--text-muted);
            padding: 30px;
            border: 1px dashed var(--border-color);
            border-radius: 10px;
            width: 100%;
        }
        
        /* === RodapÃ© === */
        .profile-footer {
            margin-top: 40px;
            text-align: center;
        }
        
        .profile-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-family: 'Roboto Mono', monospace;
            transition: opacity 0.3s;
        }
        
        .profile-footer a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }
        
        /* === Responsivo === */
        @media (max-width: 480px) {
            body {
                padding: 30px 15px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profile-username {
                font-size: 1.3rem;
            }
            
            .profile-link-btn {
                padding: 14px 16px;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Avatar -->
        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" 
             alt="Avatar de <?php echo htmlspecialchars($user['username']); ?>" 
             class="profile-avatar">
        
        <!-- Nome -->
        <h1 class="profile-username"><?php echo htmlspecialchars($user['username']); ?></h1>
        <p class="profile-tag">@<?php echo htmlspecialchars($user['tag']); ?></p>
        
        <!-- Links -->
        <div class="profile-links">
            <?php if (!empty($links)): ?>
                <?php foreach ($links as $link): 
                    $socialClass = getSocialClass($link['icon'] ?? '');
                ?>
                <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="profile-link-btn <?php echo $socialClass; ?>">
                    <i class="<?php echo htmlspecialchars($link['icon'] ?: 'fas fa-link'); ?>"></i>
                    <span><?php echo htmlspecialchars($link['title']); ?></span>
                    <i class="fas fa-external-link-alt arrow"></i>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-links">
                    <i class="fas fa-link" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    <p>Nenhum link adicionado ainda.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Rodapé -->
        <div class="profile-footer">
            <a href="<?php echo BASE_URL; ?>/login.php">
                <i class="fas fa-plus-circle"></i> Criar meu Link Free
            </a>
        </div>
        <footer class="footer-clean">
            <span>© 2026 4U.IA.BR</span>
        </footer>
    </div>
</body>
</html>
