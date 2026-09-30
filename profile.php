<?php
// profile.php - Página Pública do Perfil LinkFree Pro
// LOCAL: /raiz_do_projeto/profile.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$tag = strtolower(trim($_GET['tag'] ?? ''));
$user = getUserByTag($tag);

if (!$user) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Perfil Não Encontrado | LinkFree</title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    </head>
    <body style="display:flex; flex-direction:column; justify-content:center; align-items:center; min-height:100vh; text-align:center; padding:20px;">
        <div style="max-width:450px; background:var(--card-bg); border:1px solid var(--border-color); padding:40px 30px; border-radius:16px;">
            <i class="fas fa-ghost" style="font-size:3.5rem; color:var(--primary-color); margin-bottom:20px; display:block;"></i>
            <h1 style="font-size:1.8rem; margin-bottom:10px;">Perfil não encontrado</h1>
            <p style="color:var(--text-muted); margin-bottom:25px;">O usuário <strong>@<?php echo htmlspecialchars($tag); ?></strong> não existe ou a tag foi alterada.</p>
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary" style="display:inline-block;">Criar Minha Página de Links</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Incremento de visualizações (1 por sessão)
if (!isset($_SESSION['viewed_profile_' . $user['id']])) {
    incrementUserViews($user['id']);
    $_SESSION['viewed_profile_' . $user['id']] = true;
}

$links = getLinksByTag($tag);
$avatarUrl = getUserAvatarUrl($user['avatar_filename'] ?? '');
$theme = $user['theme'] ?? 'glass';
$username = $user['username'];
$userTitle = $user['title'] ?? '';
$userBio = $user['bio'] ?? '';
$isVerified = (bool)($user['is_verified'] ?? 1);
$publicUrl = BASE_URL . "/profile.php?tag=" . urlencode($tag);

// Helper para converter URL do YouTube em Embed
function getYoutubeEmbedUrl(string $url): ?string {
    if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $matches)) {
        return "https://www.youtube.com/embed/" . $matches[1];
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($username); ?> | LinkFree</title>
    <meta name="description" content="<?php echo htmlspecialchars($userBio ?: 'Acesse todos os links e redes sociais de ' . $username); ?>">
    
    <!-- OpenGraph & Social Cards -->
    <meta property="og:title" content="<?php echo htmlspecialchars($username); ?> | LinkFree">
    <meta property="og:description" content="<?php echo htmlspecialchars($userBio ?: 'Acesse todos os meus canais oficiais e links'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($avatarUrl); ?>">
    <meta property="og:url" content="<?php echo $publicUrl; ?>">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Anti-cache -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
    <meta http-equiv="Pragma" content="no-cache"/>
    <meta http-equiv="Expires" content="0"/>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">

    <style>
        body.theme-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 15px 60px 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .public-profile-card {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        /* Top Action Buttons (Share & QR) */
        .profile-top-actions {
            position: absolute;
            top: 0;
            right: 0;
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .btn-action-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: inherit;
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .btn-action-circle:hover {
            transform: scale(1.08);
            background: rgba(255, 255, 255, 0.2);
        }

        .profile-avatar-large {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .profile-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .profile-name {
            font-size: 1.55rem;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
        }

        .profile-subtitle {
            font-size: 0.92rem;
            opacity: 0.85;
            text-align: center;
            margin-bottom: 10px;
        }

        .profile-description {
            font-size: 0.88rem;
            opacity: 0.8;
            text-align: center;
            max-width: 420px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .social-strip-row {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .social-circle-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: inherit;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }

        .social-circle-link:hover {
            transform: translateY(-3px) scale(1.05);
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        /* Social Brand Hovers */
        .social-circle-link.ig:hover { background: #E4405F; border-color: #E4405F; }
        .social-circle-link.wa:hover { background: #25D366; border-color: #25D366; }
        .social-circle-link.yt:hover { background: #FF0000; border-color: #FF0000; }
        .social-circle-link.tk:hover { background: #000000; border-color: #ff0050; }
        .social-circle-link.gh:hover { background: #24292e; border-color: #ffffff; }
        .social-circle-link.li:hover { background: #0A66C2; border-color: #0A66C2; }

        /* Public Links Cards */
        .public-links-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .public-block-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            border-radius: 14px;
            text-decoration: none;
            color: inherit;
            font-size: 0.98rem;
            font-weight: 600;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .public-block-card:hover {
            transform: translateY(-2px);
        }

        .public-block-card i.main-icon {
            font-size: 1.35rem;
            width: 28px;
            text-align: center;
            flex-shrink: 0;
        }

        .public-block-card .block-title {
            flex: 1;
            text-align: left;
        }

        .public-block-card .arrow-icon {
            font-size: 0.85rem;
            opacity: 0.5;
            transition: transform 0.2s;
        }

        .public-block-card:hover .arrow-icon {
            transform: translateX(4px);
            opacity: 1;
        }

        /* Special PIX Box */
        .pix-interactive-box {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            width: 100% !important;
            padding: 16px 18px !important;
            border-radius: 14px;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .pix-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
            width: 100%;
        }

        .pix-key-display {
            background: rgba(0, 0, 0, 0.3);
            padding: 10px 14px;
            border-radius: 8px;
            font-family: 'Roboto Mono', monospace;
            font-size: 0.88rem;
            word-break: break-all;
            margin-bottom: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border: 1px dashed rgba(255, 255, 255, 0.2);
            width: 100%;
            box-sizing: border-box;
        }

        .pix-key-display span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .btn-copy-pix {
            background: #10b981;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-copy-pix:hover { background: #059669; }

        /* Embed Players */
        .video-embed-container {
            width: 100%;
            border-radius: 14px;
            overflow: hidden;
            aspect-ratio: 16 / 9;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            margin-bottom: 4px;
        }
        .video-embed-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* QR Code Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px);
        }
        .modal-overlay.open { display: flex; }

        .modal-card {
            background: #1e293b;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 30px;
            max-width: 380px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.6);
        }

        .toast-msg {
            position: fixed;
            bottom: 25px;
            background: #10b981;
            color: #fff;
            padding: 12px 24px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.9rem;
            z-index: 10000;
            display: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body class="theme-wrapper theme-<?php echo htmlspecialchars($theme); ?>">

    <div class="public-profile-card">
        
        <!-- Top Action Buttons -->
        <div class="profile-top-actions">
            <button type="button" class="btn-action-circle" onclick="compartilharPerfil()" title="Compartilhar Perfil">
                <i class="fas fa-share-nodes"></i>
            </button>
            <button type="button" class="btn-action-circle" onclick="abrirQrModal()" title="Exibir QR Code">
                <i class="fas fa-qrcode"></i>
            </button>
        </div>

        <!-- Avatar -->
        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" 
             alt="Avatar de <?php echo htmlspecialchars($username); ?>" 
             class="profile-avatar-large preview-avatar">

        <!-- Name & Badge -->
        <div class="profile-header-title">
            <h1 class="profile-name"><?php echo htmlspecialchars($username); ?></h1>
            <?php if ($isVerified): ?>
                <span class="badge-verified" title="Perfil Verificado">✓</span>
            <?php endif; ?>
        </div>

        <!-- Title / Specialization -->
        <?php if (!empty($userTitle)): ?>
            <div class="profile-subtitle"><?php echo htmlspecialchars($userTitle); ?></div>
        <?php else: ?>
            <div class="profile-subtitle">@<?php echo htmlspecialchars($tag); ?></div>
        <?php endif; ?>

        <!-- Bio -->
        <?php if (!empty($userBio)): ?>
            <p class="profile-description"><?php echo nl2br(htmlspecialchars($userBio)); ?></p>
        <?php endif; ?>

        <!-- Social Icons Row (if any) -->
        <?php 
        $hasSocials = !empty($user['social_instagram']) || !empty($user['social_whatsapp']) || 
                      !empty($user['social_youtube']) || !empty($user['social_tiktok']) || 
                      !empty($user['social_github']) || !empty($user['social_linkedin']);
        if ($hasSocials): 
        ?>
            <div class="social-strip-row">
                <?php if (!empty($user['social_instagram'])): ?>
                    <a href="https://instagram.com/<?php echo htmlspecialchars(ltrim($user['social_instagram'], '@')); ?>" target="_blank" rel="noopener" class="social-circle-link ig" title="Instagram"><i class="fab fa-instagram"></i></a>
                <?php endif; ?>
                <?php if (!empty($user['social_whatsapp'])): ?>
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $user['social_whatsapp']); ?>" target="_blank" rel="noopener" class="social-circle-link wa" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                <?php endif; ?>
                <?php if (!empty($user['social_youtube'])): ?>
                    <a href="https://youtube.com/<?php echo htmlspecialchars($user['social_youtube']); ?>" target="_blank" rel="noopener" class="social-circle-link yt" title="YouTube"><i class="fab fa-youtube"></i></a>
                <?php endif; ?>
                <?php if (!empty($user['social_tiktok'])): ?>
                    <a href="https://tiktok.com/@<?php echo htmlspecialchars(ltrim($user['social_tiktok'], '@')); ?>" target="_blank" rel="noopener" class="social-circle-link tk" title="TikTok"><i class="fab fa-tiktok"></i></a>
                <?php endif; ?>
                <?php if (!empty($user['social_github'])): ?>
                    <a href="https://github.com/<?php echo htmlspecialchars($user['social_github']); ?>" target="_blank" rel="noopener" class="social-circle-link gh" title="GitHub"><i class="fab fa-github"></i></a>
                <?php endif; ?>
                <?php if (!empty($user['social_linkedin'])): ?>
                    <a href="https://linkedin.com/in/<?php echo htmlspecialchars($user['social_linkedin']); ?>" target="_blank" rel="noopener" class="social-circle-link li" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Links List -->
        <div class="public-links-container">
            <?php if (!empty($links)): ?>
                <?php foreach ($links as $link): 
                    $lType = $link['type'] ?? 'link';
                    $lId = $link['id'];
                    $lTitle = htmlspecialchars($link['title']);
                    $lUrl = htmlspecialchars($link['url']);
                    $lIcon = htmlspecialchars($link['icon'] ?: 'fas fa-link');
                    $embedUrl = ($lType === 'youtube') ? getYoutubeEmbedUrl($link['url']) : null;
                ?>
                    
                    <?php if ($lType === 'youtube' && $embedUrl): ?>
                        <!-- YouTube Embed Block -->
                        <div class="video-embed-container">
                            <iframe src="<?php echo htmlspecialchars($embedUrl); ?>" title="<?php echo $lTitle; ?>" allowfullscreen></iframe>
                        </div>
                    <?php elseif ($lType === 'pix'): ?>
                        <!-- PIX Payment Block -->
                        <div class="preview-link-card type-pix pix-interactive-box">
                            <div class="pix-header">
                                <span style="font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-qrcode" style="color: #10b981;"></i> <?php echo $lTitle; ?>
                                </span>
                                <span style="font-size: 0.72rem; padding: 2px 8px; border-radius: 9999px; background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.3);">
                                    PIX Oficial
                                </span>
                            </div>
                            <div class="pix-key-display">
                                <span id="pix_key_<?php echo $lId; ?>"><?php echo htmlspecialchars($link['pix_key']); ?></span>
                                <button type="button" class="btn-copy-pix" onclick="copiarPix('<?php echo $lId; ?>')">
                                    <i class="fas fa-copy"></i> Copiar Chave
                                </button>
                            </div>
                        </div>
                    <?php elseif ($lType === 'whatsapp'): ?>
                        <!-- WhatsApp Direct Block -->
                        <a href="<?php echo $lUrl; ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="public-block-card preview-link-card type-whatsapp"
                           onclick="registrarClique(<?php echo $lId; ?>)">
                            <i class="fab fa-whatsapp main-icon" style="color: #25d366;"></i>
                            <span class="block-title"><?php echo $lTitle; ?></span>
                            <i class="fas fa-arrow-right arrow-icon"></i>
                        </a>
                    <?php else: ?>
                        <!-- Standard Link Block -->
                        <a href="<?php echo $lUrl; ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="public-block-card preview-link-card"
                           onclick="registrarClique(<?php echo $lId; ?>)">
                            <i class="<?php echo $lIcon; ?> main-icon"></i>
                            <span class="block-title"><?php echo $lTitle; ?></span>
                            <i class="fas fa-arrow-right arrow-icon"></i>
                        </a>
                    <?php endif; ?>

                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; opacity: 0.6; border: 1px dashed rgba(255,255,255,0.2); border-radius: 14px;">
                    <i class="fas fa-link" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    <p style="margin: 0;">Nenhum link adicionado ainda.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div style="margin-top: 50px; text-align: center;">
            <a href="<?php echo BASE_URL; ?>/login.php" style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; color: inherit; opacity: 0.8; text-decoration: none; padding: 8px 18px; border-radius: 9999px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
                <i class="fas fa-bolt" style="color: #00aeff;"></i> Crie seu LinkFree Grátis
            </a>
            <div style="margin-top: 15px; font-size: 0.75rem; opacity: 0.5;">
                <span>© <?php echo date('Y'); ?> 4U.IA.BR</span>
            </div>
        </div>

    </div>

    <!-- Modal QR Code -->
    <div class="modal-overlay" id="qrModal">
        <div class="modal-card">
            <h3 style="margin-bottom: 8px;">QR Code do Perfil</h3>
            <p style="font-size: 0.82rem; opacity: 0.8; margin-bottom: 18px;">
                Aponte a câmera para abrir este perfil diretamente no seu smartphone.
            </p>
            <div style="background: #ffffff; padding: 15px; border-radius: 12px; display: inline-block; margin-bottom: 20px;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($publicUrl); ?>" 
                     alt="QR Code" 
                     style="width: 200px; height: 200px; display: block;">
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-secondary" onclick="fecharQrModal()">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="copiarUrl()">
                    <i class="fas fa-copy"></i> Copiar Link
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-msg" id="toastBox">
        <i class="fas fa-check"></i> <span id="toastText">Copiado!</span>
    </div>

    <!-- Scripts Interativos -->
    <script>
    const PUBLIC_URL = '<?php echo $publicUrl; ?>';

    function showToast(text) {
        const box = document.getElementById('toastBox');
        const span = document.getElementById('toastText');
        span.textContent = text;
        box.style.display = 'block';
        setTimeout(() => { box.style.display = 'none'; }, 3000);
    }

    function registrarClique(linkId) {
        try {
            const fd = new FormData();
            fd.append('action', 'click');
            fd.append('link_id', linkId);
            navigator.sendBeacon('ajax_handler.php', fd);
        } catch (e) {}
    }

    function copiarPix(id) {
        const el = document.getElementById('pix_key_' + id);
        if (el) {
            navigator.clipboard.writeText(el.textContent.trim()).then(() => {
                showToast('Chave PIX copiada para a área de transferência!');
            }).catch(() => {
                prompt('Copie a chave PIX abaixo:', el.textContent.trim());
            });
        }
    }

    function abrirQrModal() {
        document.getElementById('qrModal').classList.add('open');
    }

    function fecharQrModal() {
        document.getElementById('qrModal').classList.remove('open');
    }

    function copiarUrl() {
        navigator.clipboard.writeText(PUBLIC_URL).then(() => {
            showToast('Link do perfil copiado com sucesso!');
            fecharQrModal();
        }).catch(() => {
            prompt('Copie o link abaixo:', PUBLIC_URL);
        });
    }

    function compartilharPerfil() {
        if (navigator.share) {
            navigator.share({
                title: '<?php echo addslashes($username); ?> | LinkFree',
                text: 'Confira minha página de links e redes sociais:',
                url: PUBLIC_URL
            }).catch(() => {});
        } else {
            copiarUrl();
        }
    }
    </script>
</body>
</html>
