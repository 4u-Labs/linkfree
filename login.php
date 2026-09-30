<?php
// login.php - Página de Login e Registro com Google OAuth 2.0
// LOCAL: /raiz_do_projeto/login.php

require_once __DIR__ . '/functions.php';

$pageTitle = "Login / Registro";
$loginError = '';
$registerError = '';
$registerSuccess = '';

// Handler de Autenticação com Google (JSON via fetch ou POST tradicional)
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['google_auth']) || (isset($jsonBody['action']) && $jsonBody['action'] === 'google_auth'))) {
    $credential = $_POST['credential'] ?? ($jsonBody['credential'] ?? '');
    $accessToken = $_POST['access_token'] ?? ($jsonBody['access_token'] ?? '');
    
    $res = authenticateWithGoogle($credential, $accessToken);
    if ($res === true) {
        if (!empty($jsonBody)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => BASE_URL . '/dashboard.php']);
            exit;
        }
        header("Location: " . BASE_URL . "/dashboard.php");
        exit;
    } else {
        if (!empty($jsonBody)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $res]);
            exit;
        }
        $loginError = $res;
    }
}

// Registro Tradicional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $tag = $_POST['tag'] ?? '';
    $qId = filter_input(INPUT_POST, 'security_question_id', FILTER_VALIDATE_INT);
    $answer = $_POST['security_answer'] ?? '';

    if ($_POST['password'] !== $_POST['password_confirm']) {
        $registerError = "As senhas não coincidem.";
    } else {
        $result = registerUser($username, $password, $tag, $qId, $answer);
        if ($result === true) $registerSuccess = "Conta criada com sucesso! Faça login.";
        else $registerError = $result;
    }
}

// Login Tradicional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $result = loginUser($username, $password);
    if ($result === true) {
        header("Location: " . BASE_URL . "/dashboard.php");
        exit;
    } else {
        $loginError = $result;
    }
}

if (isLoggedIn()) { 
    header("Location: " . BASE_URL . "/dashboard.php"); 
    exit; 
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Link Free</title>
    
    <!-- Anti-cache meta tags -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
    <meta http-equiv="Pragma" content="no-cache"/>
    <meta http-equiv="Expires" content="0"/>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    
    <!-- HEADER -->
    <header class="main-header" style="position:fixed; top:0; width:100%; z-index:1000;">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center; height:75px;">
            <a href="<?php echo BASE_URL; ?>/index.html" class="logo" style="text-decoration:none;">< Link Free /></a>
            <nav>
                <a href="<?php echo BASE_URL; ?>/index.html" style="color:var(--text-color);">Voltar ao Início</a>
            </nav>
        </div>
    </header>
    
    <!-- MAIN CONTENT -->
    <main class="container" style="padding-top: 25px;">
        
        <!-- Google Fast Login Hero -->
        <div class="google-auth-hero">
            <div class="google-hero-content">
                <div class="google-hero-text">
                    <h3><i class="fab fa-google" style="color: #4285F4;"></i> Conectar com Google</h3>
                    <p>Entre ou crie sua página de links instantaneamente em 1 clique, sem precisar de senha.</p>
                </div>
                <button type="button" id="btnGoogleAuth" onclick="handleGoogleSignIn()" class="btn-google-action">
                    <svg viewBox="0 0 24 24" width="22" height="22">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Entrar com Conta Google</span>
                </button>
            </div>
            
            <!-- Google One Tap Auto-Prompt Config -->
            <div id="g_id_onload"
                 data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                 data-callback="onGoogleAuthCallback"
                 data-auto_prompt="false">
            </div>

            <div class="auth-or-divider">
                <span>ou acesse com usuário e senha</span>
            </div>
        </div>

        <div class="auth-container" style="padding-top: 10px;">
            <div class="auth-form login-form">
                <h2>Entrar</h2>
                <?php if ($loginError): ?><p class="error-message"><?php echo htmlspecialchars($loginError); ?></p><?php endif; ?>
                <?php if ($registerSuccess): ?><p class="success-message"><?php echo htmlspecialchars($registerSuccess); ?></p><?php endif; ?>
                <form method="POST">
                    <div class="form-group"><label>Usuário:</label><input type="text" name="username" required></div>
                    <div class="form-group"><label>Senha:</label><input type="password" name="password" required></div>
                    <button type="submit" name="login" class="btn btn-primary">Entrar</button>
                </form>
            </div>

            <div class="auth-form register-form">
                <h2>Criar Conta Tradicional</h2>
                <?php if ($registerError): ?><p class="error-message"><?php echo htmlspecialchars($registerError); ?></p><?php endif; ?>
                <form method="POST">
                    <div class="form-group"><label>Usuário:</label><input type="text" name="username" required></div>
                    <div class="form-group">
                        <label>Sua Tag (URL pública):</label>
                        <input type="text" name="tag" pattern="[a-z0-9-]+" title="Apenas letras minúsculas, números e hífens" required>
                        <small><?php echo BASE_URL; ?>/profile.php?tag=<b>sua-tag</b></small>
                    </div>
                    <div class="form-group"><label>Senha:</label><input type="password" name="password" required></div>
                    <div class="form-group"><label>Confirmar Senha:</label><input type="password" name="password_confirm" required></div>
                    <div class="form-group">
                        <label>Pergunta de Segurança:</label>
                        <select name="security_question_id" required>
                            <?php foreach (SECURITY_QUESTIONS as $id => $q) echo "<option value='$id'>$q</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Resposta:</label><input type="text" name="security_answer" required></div>
                    <button type="submit" name="register" class="btn btn-secondary">Registrar</button>
                </form>
            </div>
        </div>
    </main>

    <footer class="main-footer">
        <p>&copy; <?php echo date('Y'); ?> 4U.IA.BR . Todos os direitos reservados.</p>
    </footer>

    <!-- Google OAuth Client Logic -->
    <script>
        const GOOGLE_CLIENT_ID = '<?php echo GOOGLE_CLIENT_ID; ?>';
        let googleTokenClient = null;

        function initGoogleClient() {
            if (typeof google !== 'undefined' && google.accounts && google.accounts.oauth2) {
                googleTokenClient = google.accounts.oauth2.initTokenClient({
                    client_id: GOOGLE_CLIENT_ID,
                    scope: 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email openid',
                    callback: async (resp) => {
                        if (resp && resp.access_token) {
                            await enviarAuthGoogle({ access_token: resp.access_token });
                        }
                    }
                });
            }
        }

        window.onGoogleAuthCallback = function(response) {
            if (response && response.credential) {
                enviarAuthGoogle({ credential: response.credential });
            }
        };

        function handleGoogleSignIn() {
            const btn = document.getElementById('btnGoogleAuth');
            if (btn) btn.disabled = true;

            if (typeof google === 'undefined' || !google.accounts) {
                alert('Aguardando carregamento da API do Google... Tente novamente em alguns segundos.');
                if (btn) btn.disabled = false;
                return;
            }

            if (!googleTokenClient) {
                initGoogleClient();
            }

            if (googleTokenClient) {
                googleTokenClient.requestAccessToken();
                if (btn) btn.disabled = false;
            } else if (google.accounts.id) {
                google.accounts.id.prompt();
                if (btn) btn.disabled = false;
            }
        }

        async function enviarAuthGoogle(payload) {
            const btn = document.getElementById('btnGoogleAuth');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Conectando...</span>';
            }

            try {
                const res = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'google_auth', ...payload })
                });

                const data = await res.json();
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Falha na autenticação com o Google.');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<span>Entrar com Conta Google</span>';
                    }
                }
            } catch (err) {
                console.error('Google Auth Error:', err);
                alert('Erro de conexão ao autenticar com o Google.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span>Entrar com Conta Google</span>';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initGoogleClient();
        });
    </script>
</body>
</html>
