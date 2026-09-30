<?php
// functions.php - Funções principais do sistema LinkFree
// LOCAL: /raiz_do_projeto/functions.php

require_once __DIR__ . '/config.php';

const SECURITY_QUESTIONS = [
    1 => "Qual o nome completo da sua mãe?",
    2 => "Qual o nome do seu primeiro animal de estimação?",
    3 => "Qual o nome da cidade onde você nasceu?",
    4 => "Qual era o modelo do seu primeiro carro?",
    5 => "Qual o nome do seu melhor amigo(a) de infância?"
];

// --- Autenticação Tradicional ---

function registerUser(string $username, string $password, string $tag, int $securityQuestionId, string $securityAnswer): bool|string {
    $pdo = getDbConnection();
    $username = trim($username);
    $tag = trim(strtolower($tag));
    
    if (empty($username) || empty($tag)) return "Preencha todos os campos.";
    if (strlen($password) < 8) return "Senha muito curta (mínimo 8 caracteres).";
    if (!preg_match('/^[a-z0-9-]+$/', $tag)) return "Tag inválida. Use apenas letras minúsculas, números e hífen.";
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR tag = :t");
        $stmt->execute([':u' => $username, ':t' => $tag]);
        if ($stmt->fetch()) return "Nome de usuário ou Tag já estão em uso.";

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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u OR email = :u");
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

// --- Autenticação Google OAuth 2.0 ---

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

// --- Usuários & Perfis ---

function getUserById(int $userId): ?array {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $res = $stmt->fetch();
        return $res ?: null;
    } catch (PDOException $e) { return null; }
}

function getUserByTag(string $tag): ?array {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE tag = :tag");
        $stmt->execute([':tag' => strtolower(trim($tag))]);
        $res = $stmt->fetch();
        return $res ?: null;
    } catch (PDOException $e) { return null; }
}

function incrementUserViews(int $userId): void {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("UPDATE users SET views = COALESCE(views, 0) + 1 WHERE id = :id");
        $stmt->execute([':id' => $userId]);
    } catch (PDOException $e) {}
}

function incrementLinkClicks(int $linkId): void {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("UPDATE links SET clicks = COALESCE(clicks, 0) + 1 WHERE id = :id");
        $stmt->execute([':id' => $linkId]);
    } catch (PDOException $e) {}
}

function updateUserProfileExtended(int $userId, array $data): bool|string {
    $pdo = getDbConnection();
    $newTag = strtolower(trim($data['tag'] ?? ''));
    $newUsername = trim($data['username'] ?? '');
    $title = trim($data['title'] ?? '');
    $bio = trim($data['bio'] ?? '');
    $theme = trim($data['theme'] ?? 'glass');
    $socialIg = trim($data['social_instagram'] ?? '');
    $socialWa = trim($data['social_whatsapp'] ?? '');
    $socialYt = trim($data['social_youtube'] ?? '');
    $socialTk = trim($data['social_tiktok'] ?? '');
    $socialGh = trim($data['social_github'] ?? '');
    $socialLi = trim($data['social_linkedin'] ?? '');
    
    if (!preg_match('/^[a-z0-9-]+$/', $newTag)) {
        return "Tag inválida. Use apenas letras minúsculas, números e hífen.";
    }
    
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (tag = :t OR username = :u) AND id != :uid");
        $stmtCheck->execute([':t' => $newTag, ':u' => $newUsername, ':uid' => $userId]);
        if ($stmtCheck->fetch()) return "Tag ou Usuário já estão em uso por outra conta.";

        $sql = "UPDATE users SET 
                    username = :u, 
                    tag = :t, 
                    title = :title, 
                    bio = :bio, 
                    theme = :theme,
                    social_instagram = :ig,
                    social_whatsapp = :wa,
                    social_youtube = :yt,
                    social_tiktok = :tk,
                    social_github = :gh,
                    social_linkedin = :li
                WHERE id = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':u' => $newUsername, 
            ':t' => $newTag, 
            ':title' => $title,
            ':bio' => $bio,
            ':theme' => $theme,
            ':ig' => $socialIg,
            ':wa' => $socialWa,
            ':yt' => $socialYt,
            ':tk' => $socialTk,
            ':gh' => $socialGh,
            ':li' => $socialLi,
            ':uid' => $userId
        ]);
        
        $_SESSION['username'] = $newUsername;
        $_SESSION['user_tag'] = $newTag;
        return true;
    } catch (PDOException $e) { 
        return "Erro ao atualizar perfil: " . $e->getMessage(); 
    }
}

// --- CRUD de Links Avançado ---

function addLinkExtended(int $userId, array $data): array|string {
    $pdo = getDbConnection();
    $title = trim($data['title'] ?? '');
    $url = trim($data['url'] ?? '');
    $icon = trim($data['icon'] ?? '');
    $type = trim($data['type'] ?? 'link');
    $pixKey = trim($data['pix_key'] ?? '');
    $pixType = trim($data['pix_type'] ?? 'aleatoria');
    $whatsappMsg = trim($data['whatsapp_msg'] ?? '');

    if ($type === 'link' || $type === 'youtube' || $type === 'spotify') {
        if (empty($title) || empty($url)) return "Título e URL são obrigatórios.";
    } elseif ($type === 'pix') {
        if (empty($title)) $title = "Pague via PIX";
        if (empty($pixKey)) return "Informe a Chave PIX.";
        if (empty($icon)) $icon = 'fas fa-qrcode';
        $url = '#pix';
    } elseif ($type === 'whatsapp') {
        if (empty($title)) $title = "Fale Comigo no WhatsApp";
        $cleanPhone = preg_replace('/[^0-9]/', '', $url);
        if (empty($cleanPhone)) return "Informe o número de WhatsApp com DDD.";
        $url = "https://wa.me/" . $cleanPhone . ($whatsappMsg ? "?text=" . urlencode($whatsappMsg) : "");
        if (empty($icon)) $icon = 'fab fa-whatsapp';
    }

    if (empty($icon)) {
        $icon = detectIconFromUrl($url);
    }

    try {
        $sql = "INSERT INTO links (user_id, title, url, icon, type, pix_key, pix_type, whatsapp_msg) 
                VALUES (:uid, :title, :url, :icon, :type, :pkey, :ptype, :wmsg)";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            ':uid' => $userId, 
            ':title' => $title, 
            ':url' => $url, 
            ':icon' => $icon,
            ':type' => $type,
            ':pkey' => $pixKey,
            ':ptype' => $pixType,
            ':wmsg' => $whatsappMsg
        ]);

        if ($success) {
            return [
                'id' => $pdo->lastInsertId(), 
                'title' => $title, 
                'url' => $url, 
                'icon' => $icon,
                'type' => $type,
                'pix_key' => $pixKey,
                'pix_type' => $pixType,
                'clicks' => 0
            ];
        }
        return "Erro ao salvar link.";
    } catch (PDOException $e) { 
        return "Erro DB: " . $e->getMessage(); 
    }
}

function getUserLinks(int $userId): array {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM links WHERE user_id = :uid ORDER BY position ASC, id DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) { return []; }
}

function getLinksByTag(string $tag): ?array {
    $user = getUserByTag($tag);
    if (!$user) return null;
    return getUserLinks($user['id']);
}

function deleteLink(int $linkId, int $userId): bool {
    $pdo = getDbConnection();
    try {
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = :lid AND user_id = :uid");
        $stmt->execute([':lid' => $linkId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) { return false; }
}

// --- Detecção Automática de Ícone por URL ---

function detectIconFromUrl(string $url): string {
    $url = strtolower($url);
    
    $icons = [
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
        'reddit.com'        => 'fab fa-reddit',
        'threads.net'       => 'fab fa-threads',
        'bluesky'           => 'fab fa-bluesky',
        
        'whatsapp.com'      => 'fab fa-whatsapp',
        'wa.me'             => 'fab fa-whatsapp',
        'telegram.org'      => 'fab fa-telegram',
        't.me'              => 'fab fa-telegram',
        'discord.gg'        => 'fab fa-discord',
        'discord.com'       => 'fab fa-discord',
        
        'twitch.tv'         => 'fab fa-twitch',
        'spotify.com'       => 'fab fa-spotify',
        'soundcloud.com'    => 'fab fa-soundcloud',
        'deezer.com'        => 'fab fa-deezer',
        'apple.com'         => 'fab fa-apple',
        'github.com'        => 'fab fa-github',
        'gitlab.com'        => 'fab fa-gitlab',
        'behance.net'       => 'fab fa-behance',
        'dribbble.com'      => 'fab fa-dribbble',
        'figma.com'         => 'fab fa-figma',
        'notion.so'         => 'fas fa-n',
        'amazon'            => 'fab fa-amazon',
        'shopee'            => 'fas fa-shopping-bag',
        'mercadolivre'      => 'fas fa-shopping-cart',
        'paypal.com'        => 'fab fa-paypal',
        'pix'               => 'fas fa-qrcode',
        'google.com'        => 'fab fa-google',
        'drive.google'      => 'fab fa-google-drive',
        'mailto:'           => 'fas fa-envelope',
        'tel:'              => 'fas fa-phone'
    ];
    
    foreach ($icons as $domain => $icon) {
        if (strpos($url, $domain) !== false) {
            return $icon;
        }
    }
    
    return 'fas fa-link';
}

function handleAvatarUpload(int $userId, array $fileData): array {
    if ($fileData['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP.'
        ];
        $msg = $errorMessages[$fileData['error']] ?? 'Erro desconhecido no upload.';
        return ['success' => false, 'message' => $msg];
    }
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'Formato não permitido. Use: JPG, PNG, WEBP ou GIF'];
    }
    
    if ($fileData['size'] > 4 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Arquivo muito grande. Máximo permitido: 4MB.'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileData['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($mimeType, $allowedMimes)) {
        return ['success' => false, 'message' => 'Tipo de arquivo inválido.'];
    }

    if (!is_dir(AVATAR_UPLOAD_DIR)) {
        if (!@mkdir(AVATAR_UPLOAD_DIR, 0755, true)) {
            return ['success' => false, 'message' => 'Não foi possível criar a pasta avatars.'];
        }
    }
    
    if (!is_writable(AVATAR_UPLOAD_DIR)) {
        return ['success' => false, 'message' => 'Pasta avatars sem permissão de escrita.'];
    }

    $newName = "user_" . $userId . "_" . time() . "." . $ext;
    $dest = AVATAR_UPLOAD_DIR . $newName;

    if (move_uploaded_file($fileData['tmp_name'], $dest)) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE users SET avatar_filename = ? WHERE id = ?");
        $stmt->execute([$newName, $userId]);
        
        $_SESSION['avatar_filename'] = $newName;
        return ['success' => true, 'filename' => $newName];
    }
    
    return ['success' => false, 'message' => 'Falha ao mover arquivo. Verifique permissões do servidor.'];
}
?>
