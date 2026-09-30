<?php
// config.php - ConexÃ£o SQLite e ConfiguraÃ§Ãµes Globais
// LOCAL: /raiz_do_projeto/config.php

// 1. Definição da URL Base
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? '4u.ia.br';

// Detecta o diretÃ³rio base do projeto (onde estÃ¡ o config.php)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$configDir = str_replace('\\', '/', dirname(__FILE__));
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

// Calcula o caminho relativo do config.php a partir da raiz do servidor
$basePath = str_replace($docRoot, '', $configDir);
$basePath = rtrim($basePath, '/');

define('BASE_URL', $protocol . $host . $basePath);

// 2. Configurações de Avatar e Google OAuth
define('AVATAR_UPLOAD_DIR', __DIR__ . '/avatars/'); 
define('AVATAR_URL_ROOT_PATH', BASE_URL . '/avatars/');
define('DEFAULT_AVATAR_FILENAME', 'avatar-padrao.png');
define('GOOGLE_CLIENT_ID', '569266864432-pd09jbb5no9ekdhdr018fj643nopp817.apps.googleusercontent.com');

// 3. Conexão SQLite (Banco na raiz)
$dbFile = __DIR__ . '/database.db';

try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 4. Criação Automática de Tabelas
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        tag TEXT NOT NULL UNIQUE,
        email TEXT DEFAULT NULL,
        google_id TEXT DEFAULT NULL,
        bio TEXT DEFAULT NULL,
        avatar_filename TEXT DEFAULT NULL,
        security_question_id INTEGER,
        security_answer_hash TEXT,
        background_type TEXT DEFAULT 'default',
        background_value TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migração transparente de colunas para bancos existentes
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('email', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT DEFAULT NULL");
    }
    if (!in_array('google_id', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_id TEXT DEFAULT NULL");
    }
    if (!in_array('bio', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        url TEXT NOT NULL,
        icon TEXT,
        position INTEGER DEFAULT 0,
        clicks INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

} catch (PDOException $e) {
    die("Erro Crítico ao criar/conectar banco de dados: " . $e->getMessage());
}

function getDbConnection() {
    global $pdo;
    return $pdo;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
