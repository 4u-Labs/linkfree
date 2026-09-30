<?php
// dashboard.php - Painel de Controle LinkFree Pro com Live Mobile Preview
// LOCAL: /raiz_do_projeto/dashboard.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) { 
    header("Location: " . BASE_URL . "/login.php"); 
    exit; 
}

$pageTitle = "Meu Painel";
$userId = getCurrentUserId();
$user = getUserById($userId);

if (!$user) {
    logoutUser();
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

$links = getUserLinks($userId);
$userTag = $user['tag'];
$username = $user['username'];
$userTitle = $user['title'] ?? '';
$userBio = $user['bio'] ?? '';
$userTheme = $user['theme'] ?? 'glass';
$userViews = (int)($user['views'] ?? 0);
$totalClicks = array_sum(array_column($links, 'clicks'));

$msg = $err = '';

// Processamento de Formulários Tradicionais (Upload de Avatar e Salvar Perfil)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $res = handleAvatarUpload($userId, $_FILES['avatar']);
            if ($res['success']) {
                $msg = "Foto de perfil atualizada com sucesso!";
                $user = getUserById($userId);
            } else {
                $err = "Erro no upload: " . $res['message'];
            }
        } else {
            $err = "Selecione uma imagem válida para enviar.";
        }
    }
    
    elseif ($action === 'update_profile') {
        $res = updateUserProfileExtended($userId, $_POST);
        if ($res === true) {
            $msg = "Perfil e tema atualizados com sucesso!";
            $user = getUserById($userId);
            $userTag = $user['tag'];
            $username = $user['username'];
            $userTitle = $user['title'] ?? '';
            $userBio = $user['bio'] ?? '';
            $userTheme = $user['theme'] ?? 'glass';
        } else {
            $err = $res;
        }
    }
}

$avatarUrl = getUserAvatarUrl($user['avatar_filename'] ?? '');
$publicUrl = BASE_URL . "/profile.php?tag=" . urlencode($userTag);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Link Free Pro</title>
    
    <!-- Anti-cache -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
    <meta http-equiv="Pragma" content="no-cache"/>
    <meta http-equiv="Expires" content="0"/>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Roboto+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        .topbar-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        .public-url-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 9999px;
            font-size: 0.88rem;
        }
        .btn-copy-url {
            background: rgba(0, 174, 255, 0.15);
            color: var(--primary-color);
            border: 1px solid rgba(0, 174, 255, 0.3);
            border-radius: 9999px;
            padding: 4px 10px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-copy-url:hover {
            background: var(--primary-color);
            color: #fff;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        .modal-overlay.open {
            display: flex;
        }
        .modal-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }
        .toast {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: #10b981;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            z-index: 10000;
            display: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body>
    
    <!-- HEADER -->
    <header class="main-header" style="position:fixed; top:0; width:100%; z-index:1000;">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center; height:75px;">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="logo" style="text-decoration:none;">< Link Free /></a>
            <nav>
                <ul style="display:flex; gap:20px; list-style:none; align-items:center;">
                    <li><a href="<?php echo BASE_URL; ?>/dashboard.php" style="color:var(--primary-color); font-weight:600;">Painel</a></li>
                    <li><a href="<?php echo $publicUrl; ?>" target="_blank" style="color:var(--text-color);"><i class="fas fa-arrow-up-right-from-square"></i> Ver Perfil</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout.php" style="color:var(--text-muted);">Sair</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- MAIN CONTENT -->
    <main class="container" style="padding-top: 20px;">

        <!-- Topbar Controls & Quick Metrics -->
        <div class="topbar-wrapper">
            <div>
                <h2 style="margin:0; text-align:left; font-size:1.8rem;">Painel de Controle</h2>
                <div class="public-url-box" style="margin-top:8px;">
                    <i class="fas fa-link" style="color: var(--primary-color);"></i>
                    <span id="labelPublicUrl"><?php echo $publicUrl; ?></span>
                    <button type="button" class="btn-copy-url" onclick="copiarLinkPublico()">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                    <a href="<?php echo $publicUrl; ?>" target="_blank" class="btn-copy-url" style="text-decoration:none;">
                        <i class="fas fa-external-link-alt"></i> Abrir
                    </a>
                </div>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div class="stat-pill" title="Visualizações totais da sua página">
                    <i class="fas fa-eye"></i>
                    <span><strong><?php echo number_format($userViews, 0, ',', '.'); ?></strong> visitas</span>
                </div>
                <div class="stat-pill" title="Cliques totais recebidos em todos os links">
                    <i class="fas fa-arrow-pointer"></i>
                    <span><strong><?php echo number_format($totalClicks, 0, ',', '.'); ?></strong> cliques</span>
                </div>
                <button type="button" class="btn btn-secondary" onclick="abrirModalQR()" style="padding: 9px 16px; font-size: 0.88rem; gap: 8px; display: inline-flex; align-items: center;">
                    <i class="fas fa-qrcode"></i> QR Code
                </button>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="success-message" style="margin-bottom: 20px;">
                ✓ <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="error-message" style="margin-bottom: 20px;">
                ⚠ <?php echo htmlspecialchars($err); ?>
            </div>
        <?php endif; ?>

        <!-- SPLIT SCREEN LAYOUT -->
        <div class="dashboard-split">
            
            <!-- COLUNA ESQUERDA: EDITOR E CONTROLES -->
            <div class="editor-column">
                
                <!-- CARD 1: ADICIONAR NOVO BLOCO (LINK, PIX, WHATSAPP, VÍDEO) -->
                <div class="auth-form" style="max-width: 100%;">
                    <h3 style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-plus-circle" style="color: var(--accent-color);"></i> Adicionar Novo Bloco
                    </h3>
                    
                    <!-- Block Type Tabs -->
                    <div class="block-type-tabs">
                        <button type="button" class="tab-btn active" data-tab="link" onclick="switchBlockTab('link', this)">
                            <i class="fas fa-link"></i> Link Padrão
                        </button>
                        <button type="button" class="tab-btn" data-tab="pix" onclick="switchBlockTab('pix', this)">
                            <i class="fas fa-qrcode"></i> Chave PIX
                        </button>
                        <button type="button" class="tab-btn" data-tab="whatsapp" onclick="switchBlockTab('whatsapp', this)">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </button>
                        <button type="button" class="tab-btn" data-tab="youtube" onclick="switchBlockTab('youtube', this)">
                            <i class="fab fa-youtube"></i> Vídeo / Música
                        </button>
                    </div>

                    <!-- Dynamic Form for Adding Blocks -->
                    <form id="add-block-form">
                        <input type="hidden" name="action" value="add_link">
                        <input type="hidden" name="type" id="blockTypeInput" value="link">
                        
                        <!-- Standard Link & Embed Group -->
                        <div id="groupStandardFields">
                            <div class="form-group">
                                <label id="labelBlockTitle">Título do Link:</label>
                                <input type="text" name="title" id="inputBlockTitle" required placeholder="Ex: Meu Instagram, Portfólio, Canal">
                            </div>
                            <div class="form-group">
                                <label id="labelBlockUrl">URL de Destino:</label>
                                <input type="url" name="url" id="inputBlockUrl" required placeholder="https://exemplo.com.br/seu-link">
                            </div>
                            <div class="form-group" id="groupIconField">
                                <label>Ícone (opcional):</label>
                                <input type="text" name="icon" id="inputBlockIcon" placeholder="Deixe em branco para detecção automática">
                                <small style="color:var(--text-muted); display:block; margin-top:4px;">
                                    💡 O ícone da rede social é detectado automaticamente pela URL informada!
                                </small>
                            </div>
                        </div>

                        <!-- Special PIX Fields -->
                        <div id="groupPixFields" style="display: none;">
                            <div class="form-group">
                                <label>Chave PIX:</label>
                                <input type="text" name="pix_key" id="inputPixKey" placeholder="Ex: seu-cpf, seu@email.com, telefone ou chave aleatória">
                            </div>
                            <div class="form-group">
                                <label>Tipo de Chave:</label>
                                <select name="pix_type" id="inputPixType" style="width: 100%; padding: 12px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 6px;">
                                    <option value="aleatoria">Chave Aleatória (EVP)</option>
                                    <option value="cpf">CPF / CNPJ</option>
                                    <option value="email">E-mail</option>
                                    <option value="telefone">Telefone / Celular</option>
                                </select>
                            </div>
                        </div>

                        <!-- Special WhatsApp Fields -->
                        <div id="groupWhatsappFields" style="display: none;">
                            <div class="form-group">
                                <label>Número do WhatsApp (com DDD):</label>
                                <input type="tel" name="whatsapp_phone" id="inputWaPhone" placeholder="Ex: 51988887777">
                            </div>
                            <div class="form-group">
                                <label>Mensagem Pré-configurada (opcional):</label>
                                <input type="text" name="whatsapp_msg" id="inputWaMsg" placeholder="Ex: Olá! Vim pelo seu LinkFree e gostaria de mais informações.">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="btnSubmitBlock" style="width: 100%; margin-top: 10px;">
                            <i class="fas fa-check"></i> Adicionar Bloco
                        </button>
                    </form>
                </div>

                <!-- CARD 2: MEU PERFIL & APARÊNCIA -->
                <div class="auth-form" style="max-width: 100%;">
                    <h3 style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-gear" style="color: var(--primary-color);"></i> Personalização do Perfil
                    </h3>

                    <!-- Foto de Avatar Customizada -->
                    <form method="POST" enctype="multipart/form-data" class="avatar-upload-wrapper">
                        <input type="hidden" name="action" value="upload_avatar">
                        
                        <!-- Avatar Clicável com Badge de Câmera -->
                        <div class="avatar-preview-box" onclick="document.getElementById('avatarFileInput').click()" title="Clique para escolher uma nova foto">
                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" 
                                 id="dashboardAvatarImg"
                                 class="avatar-img"
                                 alt="Avatar do Usuário">
                            <div class="avatar-overlay-badge">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>

                        <!-- Controles e Botões Estilizados -->
                        <div class="avatar-actions-box">
                            <div class="avatar-title-label">Foto de Perfil</div>
                            
                            <div class="avatar-buttons-row">
                                <!-- Botão Estilizado Bonito -->
                                <label for="avatarFileInput" class="btn-upload-picker">
                                    <i class="fas fa-image"></i> Escolher Imagem
                                </label>
                                
                                <input type="file" 
                                       id="avatarFileInput" 
                                       name="avatar" 
                                       accept="image/png, image/jpeg, image/jpg, image/webp" 
                                       style="display: none;" 
                                       onchange="handleAvatarFileSelected(this)">
                                
                                <button type="submit" id="btnSaveAvatar" class="btn-upload-save" disabled>
                                    <i class="fas fa-cloud-arrow-up"></i> Salvar Nova Foto
                                </button>
                            </div>

                            <div class="avatar-file-name" id="avatarFileChosen">
                                <i class="fas fa-circle-info"></i> JPG, PNG ou WEBP (máx. 2MB)
                            </div>
                        </div>
                    </form>

                    <hr style="border-color: var(--border-color); margin: 20px 0;">

                    <!-- Informações do Perfil -->
                    <form method="POST" id="profile-edit-form">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="theme" id="selectedThemeInput" value="<?php echo htmlspecialchars($userTheme); ?>">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>Nome de Exibição:</label>
                                <input type="text" name="username" id="inputProfileName" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Tag Única (URL):</label>
                                <input type="text" name="tag" id="inputProfileTag" value="<?php echo htmlspecialchars($userTag); ?>" required pattern="[a-z0-9-]+" title="Apenas letras minúsculas, números e hífen">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Título / Cargo Profissional:</label>
                            <input type="text" name="title" id="inputProfileTitle" value="<?php echo htmlspecialchars($userTitle); ?>" placeholder="Ex: Engenheiro de Software • SaaS Founder">
                        </div>

                        <div class="form-group">
                            <label>Biografia Curta (Bio):</label>
                            <textarea name="bio" id="inputProfileBio" rows="2" style="width: 100%; padding: 12px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 6px; font-family: var(--font-primary);" placeholder="Uma frase curta que descreve quem você é ou o que faz..."><?php echo htmlspecialchars($userBio); ?></textarea>
                        </div>

                        <!-- Seletor de Tema Visual -->
                        <div class="form-group">
                            <label><i class="fas fa-palette"></i> Escolha o Tema do seu Perfil:</label>
                            <div class="theme-picker-grid">
                                <div class="theme-card-option <?php echo ($userTheme === 'glass') ? 'active' : ''; ?>" data-theme="glass" onclick="selectTheme('glass', this)">
                                    <div class="theme-preview-dot" style="background: linear-gradient(135deg, #0a0f1f, #00aeff);"></div>
                                    <div class="theme-card-name">Glass Dark</div>
                                </div>
                                <div class="theme-card-option <?php echo ($userTheme === 'cyberpunk') ? 'active' : ''; ?>" data-theme="cyberpunk" onclick="selectTheme('cyberpunk', this)">
                                    <div class="theme-preview-dot" style="background: #030706; border-color: #00ff88;"></div>
                                    <div class="theme-card-name">Cyberpunk</div>
                                </div>
                                <div class="theme-card-option <?php echo ($userTheme === 'minimal') ? 'active' : ''; ?>" data-theme="minimal" onclick="selectTheme('minimal', this)">
                                    <div class="theme-preview-dot" style="background: #ffffff; border-color: #cbd5e1;"></div>
                                    <div class="theme-card-name">Minimal</div>
                                </div>
                                <div class="theme-card-option <?php echo ($userTheme === 'sunset') ? 'active' : ''; ?>" data-theme="sunset" onclick="selectTheme('sunset', this)">
                                    <div class="theme-preview-dot" style="background: linear-gradient(135deg, #be185d, #f97316);"></div>
                                    <div class="theme-card-name">Sunset</div>
                                </div>
                                <div class="theme-card-option <?php echo ($userTheme === 'velvet') ? 'active' : ''; ?>" data-theme="velvet" onclick="selectTheme('velvet', this)">
                                    <div class="theme-preview-dot" style="background: #09090b; border-color: #eab308;"></div>
                                    <div class="theme-card-name">Velvet Gold</div>
                                </div>
                            </div>
                        </div>

                        <!-- Redes Sociais no Topo -->
                        <div class="form-group" style="margin-top: 20px;">
                            <label><i class="fas fa-share-nodes"></i> Redes Sociais no Topo (Ícones Rápidos):</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px;">
                                <input type="text" name="social_instagram" id="inputSocialIg" value="<?php echo htmlspecialchars($user['social_instagram'] ?? ''); ?>" placeholder="Instagram (ex: seunome)">
                                <input type="text" name="social_whatsapp" id="inputSocialWa" value="<?php echo htmlspecialchars($user['social_whatsapp'] ?? ''); ?>" placeholder="WhatsApp (ex: 51988887777)">
                                <input type="text" name="social_youtube" id="inputSocialYt" value="<?php echo htmlspecialchars($user['social_youtube'] ?? ''); ?>" placeholder="YouTube (canal ou @)">
                                <input type="text" name="social_tiktok" id="inputSocialTk" value="<?php echo htmlspecialchars($user['social_tiktok'] ?? ''); ?>" placeholder="TikTok (@seunome)">
                                <input type="text" name="social_github" id="inputSocialGh" value="<?php echo htmlspecialchars($user['social_github'] ?? ''); ?>" placeholder="GitHub (usuário)">
                                <input type="text" name="social_linkedin" id="inputSocialLi" value="<?php echo htmlspecialchars($user['social_linkedin'] ?? ''); ?>" placeholder="LinkedIn (in/seunome)">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-floppy-disk"></i> Salvar Alterações do Perfil
                        </button>
                    </form>
                </div>

                <!-- CARD 3: LISTA DE LINKS ATIVOS -->
                <div class="auth-form" style="max-width: 100%;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="margin: 0;"><i class="fas fa-list-check" style="color: var(--primary-color);"></i> Seus Blocos Ativos</h3>
                        <span style="font-size: 0.85rem; color: var(--text-muted);"><span id="linksCount"><?php echo count($links); ?></span> blocos</span>
                    </div>

                    <div id="link-items-container">
                        <?php if (empty($links)): ?>
                            <div class="no-links-box" id="emptyLinksMsg" style="text-align:center; color: var(--text-muted); padding: 30px; border: 1px dashed var(--border-color); border-radius: 10px;">
                                <i class="fas fa-link" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                <p style="margin: 0;">Você ainda não possui links cadastrados. Adicione o seu primeiro bloco acima!</p>
                            </div>
                        <?php else: ?>
                            <ul id="link-list" style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($links as $link): 
                                    $lType = $link['type'] ?? 'link';
                                    $badgeText = ($lType === 'pix') ? '⚡ PIX' : (($lType === 'whatsapp') ? '💬 WhatsApp' : (($lType === 'youtube') ? '🎬 YouTube' : '🔗 Link'));
                                    $badgeColor = ($lType === 'pix') ? '#10b981' : (($lType === 'whatsapp') ? '#25d366' : (($lType === 'youtube') ? '#ef4444' : 'var(--primary-color)'));
                                ?>
                                    <li class="link-item-row" id="link_row_<?php echo $link['id']; ?>" style="background: var(--card-bg); border: 1px solid var(--border-color); padding: 14px 18px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                                        <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0;">
                                            <div style="font-size: 1.4rem; color: <?php echo $badgeColor; ?>; width: 34px; text-align: center; flex-shrink: 0;">
                                                <i class="<?php echo htmlspecialchars($link['icon'] ?: 'fas fa-link'); ?>"></i>
                                            </div>
                                            <div style="min-width: 0;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <strong style="color: var(--text-color); font-size: 1rem;"><?php echo htmlspecialchars($link['title']); ?></strong>
                                                    <span style="font-size: 0.72rem; padding: 2px 7px; border-radius: 9999px; background: <?php echo $badgeColor; ?>22; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeColor; ?>44;">
                                                        <?php echo $badgeText; ?>
                                                    </span>
                                                </div>
                                                <span style="font-size: 0.8rem; color: var(--text-muted); display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?php echo ($lType === 'pix') ? 'Chave: ' . htmlspecialchars($link['pix_key']) : htmlspecialchars($link['url']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
                                            <span style="font-size: 0.82rem; color: var(--text-muted);" title="Cliques neste link">
                                                <i class="fas fa-arrow-pointer"></i> <?php echo (int)($link['clicks'] ?? 0); ?>
                                            </span>
                                            <button type="button" class="btn btn-secondary" onclick="deletarLink(<?php echo $link['id']; ?>)" style="color: #ff4d4d; border-color: rgba(255, 77, 77, 0.4); padding: 7px 12px; font-size: 0.85rem;" title="Excluir este bloco">
                                                <i class="fas fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- COLUNA DIREITA: LIVE SMARTPHONE MOCKUP -->
            <div class="preview-column">
                <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; width: 340px;">
                    <span style="font-size: 0.84rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">
                        <i class="fas fa-mobile-screen"></i> Prévia em Tempo Real
                    </span>
                    <button type="button" class="btn-copy-url" onclick="recarregarPreview()" title="Atualizar prévia">
                        <i class="fas fa-rotate"></i>
                    </button>
                </div>

                <div class="phone-mockup-wrapper">
                    <!-- Dynamic Island Notch -->
                    <div class="phone-mockup-island"></div>

                    <!-- Inner Phone Screen -->
                    <div class="phone-screen theme-<?php echo htmlspecialchars($userTheme); ?>" id="livePhoneScreen">
                        
                        <!-- Avatar -->
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" 
                             alt="Avatar" 
                             class="preview-avatar" 
                             id="prevAvatar">

                        <!-- Name & Verified Badge -->
                        <div class="preview-name-box">
                            <span class="preview-name" id="prevName"><?php echo htmlspecialchars($username); ?></span>
                            <span class="badge-verified" title="Perfil Verificado">✓</span>
                        </div>

                        <!-- Title / Specialty -->
                        <div class="preview-title" id="prevTitle"><?php echo htmlspecialchars($userTitle ?: '@' . $userTag); ?></div>

                        <!-- Bio -->
                        <div class="preview-bio" id="prevBio"><?php echo htmlspecialchars($userBio ?: 'Bem-vindo(a) ao meu espaço digital!'); ?></div>

                        <!-- Social Icons Row -->
                        <div class="preview-socials" id="prevSocials">
                            <!-- Populated dynamically by JS -->
                        </div>

                        <!-- Links List -->
                        <div class="preview-links-list" id="prevLinksList">
                            <!-- Populated dynamically by JS -->
                        </div>

                        <!-- Footer LinkFree -->
                        <div style="margin-top: auto; padding-top: 25px; text-align: center; font-size: 0.7rem; opacity: 0.6;">
                            <span>⚡ Feito com LinkFree</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- Modal QR Code -->
    <div class="modal-overlay" id="modalQRCode">
        <div class="modal-card">
            <h3 style="margin-bottom: 15px; color: #fff;">QR Code do seu LinkFree</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">
                Aponte a câmera para abrir seu perfil ou baixe para seus cartões de visita e materiais impressos.
            </p>
            <div style="background: #fff; padding: 15px; border-radius: 12px; display: inline-block; margin-bottom: 20px;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($publicUrl); ?>" 
                     alt="QR Code" 
                     id="imgQrCode"
                     style="width: 200px; height: 200px; display: block;">
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-secondary" onclick="fecharModalQR()">Fechar</button>
                <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?php echo urlencode($publicUrl); ?>" 
                   download="qrcode_<?php echo htmlspecialchars($userTag); ?>.png" 
                   target="_blank"
                   class="btn btn-primary">
                    <i class="fas fa-download"></i> Baixar Imagem
                </a>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toastNotification">
        <i class="fas fa-check"></i> <span id="toastMsg">Mensagem</span>
    </div>

    <footer class="footer-clean" style="margin-top: 40px;">
        <span>© <?php echo date('Y'); ?> 4U.IA.BR . LinkFree Pro</span>
    </footer>

    <!-- INTERACTIVE DASHBOARD SCRIPT -->
    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    let currentTheme = '<?php echo htmlspecialchars($userTheme); ?>';

    // Toast Helper
    function showToast(msg) {
        const toast = document.getElementById('toastNotification');
        const text = document.getElementById('toastMsg');
        if (!toast || !text) return;
        text.textContent = msg;
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    // Copiar link público
    function copiarLinkPublico() {
        const url = document.getElementById('labelPublicUrl').textContent;
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link do perfil copiado com sucesso!');
        }).catch(() => {
            prompt('Copie o link abaixo:', url);
        });
    }

    // Seleção e Preview de Imagem de Avatar
    function handleAvatarFileSelected(input) {
        const chosenSpan = document.getElementById('avatarFileChosen');
        const saveBtn = document.getElementById('btnSaveAvatar');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            if (file.size > 2 * 1024 * 1024) {
                alert('A imagem é muito grande! Selecione uma foto de até 2MB.');
                input.value = '';
                chosenSpan.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-circle-exclamation"></i> Arquivo excede 2MB</span>';
                saveBtn.disabled = true;
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('dashboardAvatarImg').src = e.target.result;
                const prevAv = document.getElementById('prevAvatar');
                if (prevAv) prevAv.src = e.target.result;
            };
            reader.readAsDataURL(file);

            chosenSpan.innerHTML = `<span style="color: #10b981; font-weight: 500;"><i class="fas fa-check-circle"></i> ${file.name}</span>`;
            saveBtn.disabled = false;
        } else {
            chosenSpan.innerHTML = '<i class="fas fa-circle-info"></i> JPG, PNG ou WEBP (máx. 2MB)';
            saveBtn.disabled = true;
        }
    }

    // Modal QR Code
    function abrirModalQR() {
        document.getElementById('modalQRCode').classList.add('open');
    }
    function fecharModalQR() {
        document.getElementById('modalQRCode').classList.remove('open');
    }

    // Troca de Abas do Bloco
    function switchBlockTab(type, el) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        if (el && el.classList) {
            el.classList.add('active');
        } else {
            const btn = document.querySelector(`.tab-btn[data-tab="${type}"]`);
            if (btn) btn.classList.add('active');
        }
        document.getElementById('blockTypeInput').value = type;

        const groupStandard = document.getElementById('groupStandardFields');
        const groupPix = document.getElementById('groupPixFields');
        const groupWa = document.getElementById('groupWhatsappFields');
        const labelTitle = document.getElementById('labelBlockTitle');
        const labelUrl = document.getElementById('labelBlockUrl');
        const inputTitle = document.getElementById('inputBlockTitle');
        const inputUrl = document.getElementById('inputBlockUrl');
        const groupIcon = document.getElementById('groupIconField');

        // Reset display
        groupStandard.style.display = 'block';
        groupPix.style.display = 'none';
        groupWa.style.display = 'none';
        groupIcon.style.display = 'block';
        inputTitle.required = true;
        inputUrl.required = true;

        if (type === 'link') {
            labelTitle.textContent = 'Título do Link:';
            labelUrl.textContent = 'URL de Destino:';
            inputTitle.placeholder = 'Ex: Meu Instagram, Portfólio, Canal';
            inputUrl.placeholder = 'https://exemplo.com.br/link';
        } 
        else if (type === 'pix') {
            labelTitle.textContent = 'Título do Bloco PIX:';
            inputTitle.placeholder = 'Ex: Apoie meu trabalho / Pague com PIX';
            labelUrl.textContent = 'URL Alternativa (opcional):';
            inputUrl.required = false;
            inputUrl.value = '#pix';
            inputUrl.placeholder = '#pix';
            groupPix.style.display = 'block';
            groupIcon.style.display = 'none';
        } 
        else if (type === 'whatsapp') {
            labelTitle.textContent = 'Texto do Botão:';
            inputTitle.placeholder = 'Ex: Fale Comigo no WhatsApp';
            labelUrl.textContent = 'Link gerado (automático):';
            inputUrl.required = false;
            groupWa.style.display = 'block';
            groupIcon.style.display = 'none';
        } 
        else if (type === 'youtube') {
            labelTitle.textContent = 'Título do Vídeo / Mídia:';
            inputTitle.placeholder = 'Ex: Assista meu último vídeo no YouTube';
            labelUrl.textContent = 'Link do Vídeo no YouTube:';
            inputUrl.placeholder = 'https://www.youtube.com/watch?v=...';
        }
    }

    // Seleção de Tema
    function selectTheme(themeName, el) {
        currentTheme = themeName;
        document.getElementById('selectedThemeInput').value = themeName;
        document.querySelectorAll('.theme-card-option').forEach(c => c.classList.remove('active'));
        if (el && el.classList) {
            el.classList.add('active');
        } else {
            const card = document.querySelector(`.theme-card-option[data-theme="${themeName}"]`);
            if (card) card.classList.add('active');
        }

        // Atualiza mockup
        const screen = document.getElementById('livePhoneScreen');
        if (screen) {
            screen.className = 'phone-screen theme-' + themeName;
        }
    }

    // Atualização em Tempo Real dos inputs no Mockup
    document.getElementById('inputProfileName').addEventListener('input', function() {
        document.getElementById('prevName').textContent = this.value || 'Seu Nome';
    });

    document.getElementById('inputProfileTitle').addEventListener('input', function() {
        document.getElementById('prevTitle').textContent = this.value || '@' + document.getElementById('inputProfileTag').value;
    });

    document.getElementById('inputProfileBio').addEventListener('input', function() {
        document.getElementById('prevBio').textContent = this.value || 'Sua biografia aqui...';
    });

    // Submissão AJAX do Formulário de Adicionar Bloco
    document.getElementById('add-block-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitBlock');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

        const formData = new FormData(this);
        const type = document.getElementById('blockTypeInput').value;

        if (type === 'whatsapp') {
            const phone = document.getElementById('inputWaPhone').value.replace(/[^0-9]/g, '');
            const msg = document.getElementById('inputWaMsg').value;
            formData.set('url', 'https://wa.me/' + phone + (msg ? '?text=' + encodeURIComponent(msg) : ''));
        }

        try {
            const res = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showToast('Bloco adicionado com sucesso!');
                this.reset();
                switchBlockTab('link');
                await recarregarPreview();
                setTimeout(() => {
                    window.location.reload();
                }, 350);
            } else {
                alert(data.message || 'Erro ao salvar bloco.');
            }
        } catch (err) {
            console.error('Erro:', err);
            alert('Erro de comunicação com o servidor.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    // Exclusão de Link
    async function deletarLink(id) {
        if (!confirm('Deseja realmente remover este bloco?')) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete_link');
            formData.append('link_id', id);

            const res = await fetch('ajax_handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                showToast('Bloco excluído com sucesso!');
                const row = document.getElementById('link_row_' + id);
                if (row) row.remove();
                await recarregarPreview();
            } else {
                alert(data.message || 'Erro ao excluir.');
            }
        } catch (e) {
            alert('Erro ao tentar excluir bloco.');
        }
    }

    // Carregar e Renderizar dados no Phone Mockup
    async function recarregarPreview() {
        try {
            const res = await fetch('ajax_handler.php?action=get_preview_data');
            const data = await res.json();
            if (!data.success) return;

            const u = data.user;
            const links = data.links;

            // Render Info
            document.getElementById('prevAvatar').src = u.avatar_url;
            document.getElementById('prevName').textContent = u.username;
            document.getElementById('prevTitle').textContent = u.title || ('@' + u.tag);
            document.getElementById('prevBio').textContent = u.bio || 'Bem-vindo(a) ao meu espaço digital!';
            document.getElementById('livePhoneScreen').className = 'phone-screen theme-' + u.theme;

            // Render Socials
            const socialsCont = document.getElementById('prevSocials');
            socialsCont.innerHTML = '';
            const s = u.socials;
            if (s.instagram) socialsCont.innerHTML += `<a href="https://instagram.com/${s.instagram}" target="_blank" class="preview-social-btn"><i class="fab fa-instagram"></i></a>`;
            if (s.whatsapp) socialsCont.innerHTML += `<a href="https://wa.me/${s.whatsapp.replace(/[^0-9]/g,'')}" target="_blank" class="preview-social-btn"><i class="fab fa-whatsapp"></i></a>`;
            if (s.youtube) socialsCont.innerHTML += `<a href="https://youtube.com/${s.youtube}" target="_blank" class="preview-social-btn"><i class="fab fa-youtube"></i></a>`;
            if (s.tiktok) socialsCont.innerHTML += `<a href="https://tiktok.com/@${s.tiktok}" target="_blank" class="preview-social-btn"><i class="fab fa-tiktok"></i></a>`;
            if (s.github) socialsCont.innerHTML += `<a href="https://github.com/${s.github}" target="_blank" class="preview-social-btn"><i class="fab fa-github"></i></a>`;
            if (s.linkedin) socialsCont.innerHTML += `<a href="https://linkedin.com/in/${s.linkedin}" target="_blank" class="preview-social-btn"><i class="fab fa-linkedin"></i></a>`;

            // Render Links
            const linksCont = document.getElementById('prevLinksList');
            linksCont.innerHTML = '';
            if (links.length === 0) {
                linksCont.innerHTML = '<div style="text-align:center; font-size:0.8rem; opacity:0.6; padding: 15px;">Nenhum link adicionado ainda</div>';
            } else {
                links.forEach(l => {
                    const lType = l.type || 'link';
                    const icon = l.icon || 'fas fa-link';
                    let extraClass = '';
                    if (lType === 'pix') extraClass = 'type-pix';
                    if (lType === 'whatsapp') extraClass = 'type-whatsapp';
                    if (lType === 'youtube') extraClass = 'type-youtube';

                    linksCont.innerHTML += `
                        <div class="preview-link-card ${extraClass}">
                            <i class="${icon}" style="font-size: 1.1rem; width: 22px; text-align: center;"></i>
                            <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${l.title}</span>
                            <i class="fas fa-chevron-right" style="font-size: 0.75rem; opacity: 0.5;"></i>
                        </div>
                    `;
                });
            }
        } catch (e) {
            console.error('Erro ao atualizar preview:', e);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        recarregarPreview();
    });
    </script>

</body>
</html>
