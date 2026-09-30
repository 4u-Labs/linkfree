<?php
// ajax_handler.php - Endpoint AJAX para LinkFree
// LOCAL: /raiz_do_projeto/ajax_handler.php

require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=UTF-8');

$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
$action = $_POST['action'] ?? ($jsonBody['action'] ?? ($_GET['action'] ?? ''));

// Ações Públicas (Não requerem login)
if ($action === 'click') {
    $linkId = filter_var($_POST['link_id'] ?? ($jsonBody['link_id'] ?? 0), FILTER_VALIDATE_INT);
    if ($linkId) {
        incrementLinkClicks($linkId);
        echo json_encode(['success' => true]);
        exit;
    }
}

if ($action === 'view') {
    $userId = filter_var($_POST['user_id'] ?? ($jsonBody['user_id'] ?? 0), FILTER_VALIDATE_INT);
    if ($userId) {
        incrementUserViews($userId);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Ações que Requerem Login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login necessário']);
    exit;
}

$userId = getCurrentUserId();

if ($action === 'add_link') {
    $data = !empty($jsonBody) ? $jsonBody : $_POST;
    $res = addLinkExtended($userId, $data);
    if (is_array($res)) {
        echo json_encode(['success' => true, 'link' => $res]);
    } else {
        echo json_encode(['success' => false, 'message' => $res]);
    }
    exit;
}

if ($action === 'delete_link') {
    $linkId = filter_var($_POST['link_id'] ?? ($jsonBody['link_id'] ?? 0), FILTER_VALIDATE_INT);
    $res = deleteLink($linkId, $userId);
    echo json_encode(['success' => $res, 'message' => $res ? 'Link excluído com sucesso!' : 'Erro ao excluir link.']);
    exit;
}

if ($action === 'update_profile') {
    $data = !empty($jsonBody) ? $jsonBody : $_POST;
    $res = updateUserProfileExtended($userId, $data);
    if ($res === true) {
        echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => $res]);
    }
    exit;
}

if ($action === 'get_preview_data') {
    $user = getUserById($userId);
    $links = getUserLinks($userId);
    echo json_encode([
        'success' => true,
        'user' => [
            'username' => $user['username'],
            'tag' => $user['tag'],
            'title' => $user['title'] ?? '',
            'bio' => $user['bio'] ?? '',
            'theme' => $user['theme'] ?? 'glass',
            'avatar_url' => getUserAvatarUrl($user['avatar_filename'] ?? ''),
            'is_verified' => (bool)($user['is_verified'] ?? 1),
            'views' => (int)($user['views'] ?? 0),
            'socials' => [
                'instagram' => $user['social_instagram'] ?? '',
                'whatsapp' => $user['social_whatsapp'] ?? '',
                'youtube' => $user['social_youtube'] ?? '',
                'tiktok' => $user['social_tiktok'] ?? '',
                'github' => $user['social_github'] ?? '',
                'linkedin' => $user['social_linkedin'] ?? ''
            ]
        ],
        'links' => $links
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida']);
?>