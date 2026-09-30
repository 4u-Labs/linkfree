<?php
// login.php - Autenticação Exclusiva com Google OAuth 2.0 (1-Click Login)
// LOCAL: /raiz_do_projeto/login.php

require_once __DIR__ . '/functions.php';

$pageTitle = "Entrar com Google";
$loginError = '';

// Se já estiver logado, redireciona diretamente ao painel
if (isLoggedIn()) { 
    header("Location: " . BASE_URL . "/dashboard.php"); 
    exit; 
}

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Roboto+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <style>
        body {
            background-color: var(--bg-color, #0a0f1f);
            color: var(--text-color, #e0e5f0);
            font-family: var(--font-primary, 'Poppins', sans-serif);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            background-image: radial-gradient(rgba(0, 174, 255, 0.08) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .login-page-header {
            width: 100%;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-sizing: border-box;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            background: rgba(10, 15, 31, 0.6);
        }

        .login-page-header .logo {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.3rem;
            font-weight: 700;
            color: #00aeff;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .login-page-header .btn-back {
            color: var(--text-muted, #94a3b8);
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }
        .login-page-header .btn-back:hover {
            color: #00aeff;
        }

        .login-center-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .google-exclusive-card {
            width: 100%;
            max-width: 480px;
            background: linear-gradient(135deg, rgba(26, 32, 53, 0.9) 0%, rgba(15, 23, 42, 0.98) 100%);
            border: 1px solid rgba(0, 174, 255, 0.3);
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6), 0 0 35px rgba(0, 174, 255, 0.12);
            backdrop-filter: blur(16px);
            text-align: center;
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        .google-exclusive-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4285F4 0%, #34A853 33%, #FBBC05 66%, #EA4335 100%);
        }

        .card-brand-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(0, 174, 255, 0.1);
            border: 1px solid rgba(0, 174, 255, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.8rem;
            color: #00aeff;
            box-shadow: 0 0 20px rgba(0, 174, 255, 0.2);
        }

        .google-exclusive-card h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 10px 0;
            letter-spacing: -0.5px;
        }

        .google-exclusive-card p.subtitle {
            font-size: 0.95rem;
            color: var(--text-muted, #94a3b8);
            line-height: 1.5;
            margin: 0 0 30px 0;
        }

        .btn-google-exclusive {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            background: #ffffff;
            color: #1f2937;
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25), 0 0 15px rgba(255, 255, 255, 0.1);
            font-family: inherit;
        }

        .btn-google-exclusive:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(66, 133, 244, 0.35);
        }

        .btn-google-exclusive:active {
            transform: translateY(0);
        }

        .btn-google-exclusive:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .security-benefits {
            margin-top: 32px;
            padding-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.88rem;
            color: #cbd5e1;
        }

        .benefit-item i {
            color: #10b981;
            font-size: 1rem;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }

        .login-alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }

        .exclusive-footer {
            padding: 20px;
            text-align: center;
            font-size: 0.82rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    
    <!-- HEADER -->
    <header class="login-page-header">
        <a href="<?php echo BASE_URL; ?>/index.html" class="logo">< Link Free /></a>
        <a href="<?php echo BASE_URL; ?>/index.html" class="btn-back">
            <i class="fas fa-arrow-left"></i> Voltar ao Início
        </a>
    </header>
    
    <!-- MAIN CONTENT -->
    <main class="login-center-wrapper">
        <div class="google-exclusive-card">
            
            <div class="card-brand-icon">
                <i class="fas fa-bolt"></i>
            </div>

            <h1>Entrar no LinkFree</h1>
            <p class="subtitle">
                Acesse seu painel e gerencie seus links, PIX e projetos com rapidez e segurança.
            </p>

            <?php if ($loginError): ?>
                <div class="login-alert-error">
                    <i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($loginError); ?>
                </div>
            <?php endif; ?>

            <div id="googleErrorBox" class="login-alert-error" style="display: none;"></div>

            <!-- Botão Principal Exclusivo com Google -->
            <button type="button" id="btnGoogleAuth" onclick="handleGoogleSignIn()" class="btn-google-exclusive">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span>Continuar com Google</span>
            </button>
            
            <!-- Google One Tap Auto-Prompt Config -->
            <div id="g_id_onload"
                 data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                 data-callback="onGoogleAuthCallback"
                 data-auto_prompt="true">
            </div>

            <!-- Vantagens do Login sem Senha -->
            <div class="security-benefits">
                <div class="benefit-item">
                    <i class="fas fa-check-circle"></i>
                    <span><b>Acesso em 1 clique:</b> sem formulários ou criação de senhas</span>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-shield-halved"></i>
                    <span><b>Segurança Google:</b> autenticação oficial e protegida</span>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-wand-magic-sparkles"></i>
                    <span><b>Perfil imediato:</b> nome, foto e link único configurados na hora</span>
                </div>
            </div>

        </div>
    </main>

    <footer class="exclusive-footer">
        <p>&copy; <?php echo date('Y'); ?> 4U.IA.BR &bull; LinkFree Pro &bull; Todos os direitos reservados.</p>
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

        function showError(msg) {
            const errBox = document.getElementById('googleErrorBox');
            if (errBox) {
                errBox.textContent = msg;
                errBox.style.display = 'block';
            } else {
                alert(msg);
            }
        }

        function handleGoogleSignIn() {
            const btn = document.getElementById('btnGoogleAuth');
            if (btn) btn.disabled = true;

            const errBox = document.getElementById('googleErrorBox');
            if (errBox) errBox.style.display = 'none';

            if (typeof google === 'undefined' || !google.accounts) {
                showError('Aguardando carregamento da API do Google... Tente novamente em alguns segundos.');
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
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Autenticando...</span>';
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
                    showError(data.message || 'Falha na autenticação com o Google.');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = `
                            <svg viewBox="0 0 24 24" width="24" height="24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            <span>Continuar com Google</span>
                        `;
                    }
                }
            } catch (err) {
                console.error('Google Auth Error:', err);
                showError('Erro de conexão ao autenticar com o Google.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = `
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span>Continuar com Google</span>
                    `;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initGoogleClient();
        });
    </script>
</body>
</html>
