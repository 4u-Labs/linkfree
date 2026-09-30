<?php
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login necessÃ¡rio']);
    exit;
}

$userId = getCurrentUserId();
$action = $_POST['action'] ?? '';

if ($action === 'add_link') {
    $res = addLink($userId, $_POST['title'], $_POST['url'], $_POST['icon']);
    if (is_array($res)) echo json_encode(['success' => true, 'link' => $res]);
    else echo json_encode(['success' => false, 'message' => $res]);
} 
elseif ($action === 'delete_link') {
    $res = deleteLink($_POST['link_id'], $userId);
    echo json_encode(['success' => $res, 'message' => $res ? 'Deletado' : 'Erro']);
}
else {
    echo json_encode(['success' => false, 'message' => 'AÃ§Ã£o invÃ¡lida']);
}
?>