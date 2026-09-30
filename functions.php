<?php
// functions.php - FunÃ§Ãµes principais do sistema
// LOCAL: /raiz_do_projeto/functions.php

require_once __DIR__ . '/config.php';

const SECURITY_QUESTIONS = [
    1 => "Qual o nome completo da sua mÃ£e?",
    2 => "Qual o nome do seu primeiro animal de estimaÃ§Ã£o?",
    3 => "Qual o nome da cidade onde vocÃª nasceu?",
    4 => "Qual era o modelo do seu primeiro carro?",
    5 => "Qual o nome do seu melhor amigo(a) de infÃ¢ncia?"
];

// --- AutenticaÃ§Ã£o ---

function registerUser(string $username, string $password, string $tag, int $securityQuestionId, string $securityAnswer): bool|string {
    $pdo = getDbConnection();
    $username = trim($username);
    $tag = trim(strtolower($tag));
    
    if (empty($username) || empty($tag)) return "Preencha todos os campos.";
    if (strlen($password) < 8) return "Senha muito curta (mÃ­nimo 8 caracteres).";
    if (!preg_match('/^[a-z0-9-]+$/', $tag)) return "Tag invÃ¡lida. Use apenas letras minÃºsculas, nÃºmeros e hÃ­fen.";
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR tag = :t");
        $stmt->execute([':u' => $username, ':t' => $tag]);
        if ($stmt->fetch()) return "Nome de usuÃ¡rio ou Tag jÃ¡ estÃ£o em uso.";

        $passHash = password_hash($password, PASSWORD_DEFAULT);
        $ansHash = password_hash(strtolower(trim($securityAnswer)), PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, password, tag, security_question_id, security_answer_hash) 
                VALUES (:u, :p, :t, :q, :a)";
        $stmtInsert = $pdo->prepare($sql);
        return $stmtInsert->execute([
            ':u' => $username, ':p' => $passHash, ':t' => $tag, 
            ':q' => $securityQuestionId, ':a' => $ansHash
        ]);
    } catch (PDOException $e) {
        return "Erro no banco de dados: " . $e->getMessage();
    }
}

function loginUser(string $username, string $password): bool|string {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_tag'] = $user['tag'];
            $_SESSION['avatar_filename'] = $user['avatar_filename'];
            return true;
        }
        return "Usuário ou senha incorretos.";
    } catch (PDOException $e) {
        return "Erro ao tentar logar.";
    }
}

function authenticateWithGoogle(string $credential = '', ?string $accessToken = null): bool|string {
    $email = '';
    $name = '';
    $picture = '';
    $googleId = '';

    if (!empty($credential)) {
        $parts = explode('.', $credential);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if ($payload && !empty($payload['email'])) {
                $email = strtolower(trim($payload['email']));
                $name = trim($payload['name'] ?? '');
                $picture = trim($payload['picture'] ?? '');
                $googleId = trim($payload['sub'] ?? '');
            }
        }
    }

    if (!$email && !empty($accessToken)) {
        $opts = [
            'http' => [
                'header' => "Authorization: Bearer " . $accessToken . "\r\nUser-Agent: 4U-LinkFree\r\n",
                'timeout' => 5
            ]
        ];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false, $ctx);
        if ($res) {
            $info = json_decode($res, true);
            if (!empty($info['email'])) {
                $email = strtolower(trim($info['email']));
                $name = trim($info['name'] ?? $name);
                $picture = trim($info['picture'] ?? $picture);
                $googleId = trim($info['sub'] ?? $googleId);
            }
        }
    }

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "E-mail do Google inválido ou não autorizado.";
    }

    $pdo = getDbConnection();

    try {
        // 1. Verificar se usuário já existe por google_id OU email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = :gid OR email = :email OR username = :u");
        $stmt->execute([':gid' => $googleId, ':email' => $email, ':u' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $updateAvatar = empty($user['avatar_filename']) ? $picture : $user['avatar_filename'];
            $stmtUp = $pdo->prepare("UPDATE users SET google_id = COALESCE(NULLIF(google_id, ''), :gid), email = :email, avatar_filename = :av WHERE id = :id");
            $stmtUp->execute([
                ':gid' => $googleId,
                ':email' => $email,
                ':av' => $updateAvatar,
                ':id' => $user['id']
            ]);
            
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_tag'] = $user['tag'];
            $_SESSION['avatar_filename'] = $updateAvatar;
            return true;
        }

        // 2. Se for novo usuário, criar conta automaticamente
        $baseTag = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0]));
        if (empty($baseTag)) $baseTag = 'user';
        $tag = $baseTag;
        $counter = 1;
        
        while (true) {
            $check = $pdo->prepare("SELECT id FROM users WHERE tag = :t");
            $check->execute([':t' => $tag]);
            if (!$check->fetch()) break;
            $tag = $baseTag . $counter;
            $counter++;
        }

        $username = $name ?: explode('@', $email)[0];
        $checkU = $pdo->prepare("SELECT id FROM users WHERE username = :u");
        $checkU->execute([':u' => $username]);
        if ($checkU->fetch()) {
            $username = $username . ' (' . $tag . ')';
        }

        $randomPassHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, password, tag, email, google_id, avatar_filename) 
                VALUES (:u, :p, :t, :email, :gid, :av)";
        $stmtIns = $pdo->prepare($sql);
        $ok = $stmtIns->execute([
            ':u' => $username,
            ':p' => $randomPassHash,
            ':t' => $tag,
            ':email' => $email,
            ':gid' => $googleId,
            ':av' => $picture
        ]);

        if ($ok) {
            $newId = $pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newId;
            $_SESSION['username'] = $username;
            $_SESSION['user_tag'] = $tag;
            $_SESSION['avatar_filename'] = $picture;
            return true;
        }

        return "Erro ao criar conta com o Google.";
    } catch (PDOException $e) {
        return "Erro no banco de dados: " . $e->getMessage();
    }
}

function getUserAvatarUrl(?string $avatarFilename): string {
    if (empty($avatarFilename)) {
        return AVATAR_URL_ROOT_PATH . DEFAULT_AVATAR_FILENAME;
    }
    if (str_starts_with($avatarFilename, 'http://') || str_starts_with($avatarFilename, 'https://')) {
        return $avatarFilename;
    }
    return AVATAR_URL_ROOT_PATH . $avatarFilename;
}

function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
function logoutUser(): void { $_SESSION = []; session_destroy(); }
function getCurrentUserId(): ?int { return $_SESSION['user_id'] ?? null; }
function getCurrentUserTag(): ?string { return $_SESSION['user_tag'] ?? null; }

// --- CRUD de Links ---

function addLink(int $userId, string $title, string $url, ?string $icon = null): array|string {
    $pdo = getDbConnection();
    $url = trim($url);
    
    // Detecção automática de ícone se não foi informado ou está vazio
    if (empty($icon)) {
        $icon = detectIconFromUrl($url);
    }
    
    try {
        $sql = "INSERT INTO links (user_id, title, url, icon) VALUES (:uid, :title, :url, :icon)";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([':uid' => $userId, ':title' => trim($title), ':url' => $url, ':icon' => $icon]);

        if ($success) {
            return ['id' => $pdo->lastInsertId(), 'title' => $title, 'url' => $url, 'icon' => $icon];
        }
        return "Erro ao salvar link.";
    } catch (PDOException $e) { return "Erro DB: " . $e->getMessage(); }
}

function getUserLinks(int $userId): array {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM links WHERE user_id = :uid ORDER BY id DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) { return []; }
}

function updateLink(int $linkId, int $userId, string $title, string $url, ?string $icon = null): array|string {
    $pdo = getDbConnection();
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM links WHERE id = :lid AND user_id = :uid");
        $stmtCheck->execute([':lid' => $linkId, ':uid' => $userId]);
        if (!$stmtCheck->fetch()) return "Link nÃ£o encontrado.";

        $sql = "UPDATE links SET title = :title, url = :url, icon = :icon WHERE id = :lid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':title' => trim($title), ':url' => trim($url), ':icon' => $icon, ':lid' => $linkId]);
        return ['id' => $linkId, 'title' => $title, 'url' => $url, 'icon' => $icon];
    } catch (PDOException $e) { return "Erro ao atualizar."; }
}

function deleteLink(int $linkId, int $userId): bool {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = :lid AND user_id = :uid");
        $stmt->execute([':lid' => $linkId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) { return false; }
}

// --- Perfil e Upload ---

function getUserByTag(string $tag): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, username, tag, avatar_filename FROM users WHERE tag = :tag");
    $stmt->execute([':tag' => strtolower(trim($tag))]);
    $res = $stmt->fetch();
    return $res ?: null;
}

function getLinksByTag(string $tag): ?array {
    $user = getUserByTag($tag);
    if (!$user) return null;
    return getUserLinks($user['id']);
}

function updateUserProfile(int $userId, string $newUsername, string $newTag): bool|string {
    $pdo = getDbConnection();
    $newTag = strtolower(trim($newTag));
    
    if (!preg_match('/^[a-z0-9-]+$/', $newTag)) return "Tag invÃ¡lida. Use apenas letras minÃºsculas, nÃºmeros e hÃ­fen.";
    
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (tag = :t OR username = :u) AND id != :uid");
        $stmtCheck->execute([':t' => $newTag, ':u' => $newUsername, ':uid' => $userId]);
        if ($stmtCheck->fetch()) return "Tag ou UsuÃ¡rio jÃ¡ em uso.";

        $sql = "UPDATE users SET username = :u, tag = :t WHERE id = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $newUsername, ':t' => $newTag, ':uid' => $userId]);
        
        $_SESSION['username'] = $newUsername;
        $_SESSION['user_tag'] = $newTag;
        return true;
    } catch (PDOException $e) { return "Erro ao atualizar perfil."; }
}

// --- Detecção Automática de Ícone por URL ---

function detectIconFromUrl(string $url): string {
    $url = strtolower($url);
    
    $icons = [
        // Redes Sociais
        'instagram.com'     => 'fab fa-instagram',
        'facebook.com'      => 'fab fa-facebook',
        'fb.com'            => 'fab fa-facebook',
        'twitter.com'       => 'fab fa-x-twitter',
        'x.com'             => 'fab fa-x-twitter',
        'linkedin.com'      => 'fab fa-linkedin',
        'youtube.com'       => 'fab fa-youtube',
        'youtu.be'          => 'fab fa-youtube',
        'tiktok.com'        => 'fab fa-tiktok',
        'pinterest.com'     => 'fab fa-pinterest',
        'snapchat.com'      => 'fab fa-snapchat',
        'reddit.com'        => 'fab fa-reddit',
        'tumblr.com'        => 'fab fa-tumblr',
        'threads.net'       => 'fab fa-threads',
        'mastodon'          => 'fab fa-mastodon',
        'bluesky'           => 'fab fa-bluesky',
        
        // Mensageiros
        'whatsapp.com'      => 'fab fa-whatsapp',
        'wa.me'             => 'fab fa-whatsapp',
        'api.whatsapp'      => 'fab fa-whatsapp',
        'telegram.org'      => 'fab fa-telegram',
        't.me'              => 'fab fa-telegram',
        'discord.gg'        => 'fab fa-discord',
        'discord.com'       => 'fab fa-discord',
        'messenger.com'     => 'fab fa-facebook-messenger',
        
        // Streaming/Gaming
        'twitch.tv'         => 'fab fa-twitch',
        'spotify.com'       => 'fab fa-spotify',
        'soundcloud.com'    => 'fab fa-soundcloud',
        'deezer.com'        => 'fab fa-deezer',
        'apple.com/music'   => 'fab fa-apple',
        'music.apple'       => 'fab fa-apple',
        'steam'             => 'fab fa-steam',
        'playstation'       => 'fab fa-playstation',
        'xbox.com'          => 'fab fa-xbox',
        'kick.com'          => 'fas fa-k',
        
        // Dev/Portfolio
        'github.com'        => 'fab fa-github',
        'gitlab.com'        => 'fab fa-gitlab',
        'bitbucket.org'     => 'fab fa-bitbucket',
        'codepen.io'        => 'fab fa-codepen',
        'stackoverflow'     => 'fab fa-stack-overflow',
        'dev.to'            => 'fab fa-dev',
        'medium.com'        => 'fab fa-medium',
        'behance.net'       => 'fab fa-behance',
        'dribbble.com'      => 'fab fa-dribbble',
        'figma.com'         => 'fab fa-figma',
        'notion.so'         => 'fas fa-n',
        
        // E-commerce/Negócios
        'amazon'            => 'fab fa-amazon',
        'shopee'            => 'fas fa-shopping-bag',
        'mercadolivre'      => 'fas fa-shopping-cart',
        'shopify'           => 'fab fa-shopify',
        'etsy.com'          => 'fab fa-etsy',
        'paypal.com'        => 'fab fa-paypal',
        'patreon.com'       => 'fab fa-patreon',
        'ko-fi.com'         => 'fas fa-coffee',
        'buymeacoffee'      => 'fas fa-mug-hot',
        'pix'               => 'fas fa-qrcode',
        
        // Outros
        'google.com'        => 'fab fa-google',
        'drive.google'      => 'fab fa-google-drive',
        'docs.google'       => 'fas fa-file-alt',
        'maps.google'       => 'fas fa-map-marker-alt',
        'mailto:'           => 'fas fa-envelope',
        'tel:'              => 'fas fa-phone',
        'wordpress'         => 'fab fa-wordpress',
        'blogger'           => 'fab fa-blogger',
        'wix.com'           => 'fab fa-wix',
        'dropbox.com'       => 'fab fa-dropbox',
        'onedrive'          => 'fab fa-microsoft',
        'linktr.ee'         => 'fas fa-tree',
        'bio.link'          => 'fas fa-link',
    ];
    
    foreach ($icons as $domain => $icon) {
        if (strpos($url, $domain) !== false) {
            return $icon;
        }
    }
    
    return 'fas fa-link'; // Ícone padrão
}

function handleAvatarUpload(int $userId, array $fileData): array {
    // Verifica erro de upload
    if ($fileData['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor (php.ini).',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulÃ¡rio.',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporÃ¡ria nÃ£o encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensÃ£o PHP.'
        ];
        $msg = $errorMessages[$fileData['error']] ?? 'Erro desconhecido no upload.';
        return ['success' => false, 'message' => $msg];
    }
    
    // ValidaÃ§Ã£o de extensÃ£o
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'Formato nÃ£o permitido. Use: ' . implode(', ', $allowed)];
    }
    
    // ValidaÃ§Ã£o de tamanho (2MB)
    if ($fileData['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Arquivo muito grande. MÃ¡ximo: 2MB.'];
    }
    
    // ValidaÃ§Ã£o de tipo MIME real
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileData['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($mimeType, $allowedMimes)) {
        return ['success' => false, 'message' => 'Tipo de arquivo invÃ¡lido.'];
    }

    // Cria pasta avatars se nÃ£o existir
    if (!is_dir(AVATAR_UPLOAD_DIR)) {
        if (!@mkdir(AVATAR_UPLOAD_DIR, 0755, true)) {
            return ['success' => false, 'message' => 'NÃ£o foi possÃ­vel criar a pasta avatars.'];
        }
    }
    
    // Verifica se a pasta Ã© gravÃ¡vel
    if (!is_writable(AVATAR_UPLOAD_DIR)) {
        return ['success' => false, 'message' => 'Pasta avatars sem permissÃ£o de escrita.'];
    }

    // Gera nome Ãºnico
    $newName = "user_" . $userId . "_" . time() . "." . $ext;
    $dest = AVATAR_UPLOAD_DIR . $newName;

    // Move o arquivo
    if (move_uploaded_file($fileData['tmp_name'], $dest)) {
        // Atualiza no banco
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE users SET avatar_filename = ? WHERE id = ?");
        $stmt->execute([$newName, $userId]);
        
        // Atualiza sessÃ£o
        $_SESSION['avatar_filename'] = $newName;
        
        return ['success' => true, 'filename' => $newName];
    }
    
    return ['success' => false, 'message' => 'Falha ao mover arquivo. Verifique permissÃµes.'];
}
?>
