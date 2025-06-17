<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$articleId = isset($input['article_id']) ? intval($input['article_id']) : 0;

if ($articleId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid article ID']);
    exit;
}

try {
    $sessionId = session_id();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // 重複チェック（同一セッション・同一記事で5分以内は重複とみなす）
    $checkSql = "SELECT id FROM ai_experience_logs 
                 WHERE session_id = ? AND ai_service_id = (
                     SELECT ai_service_id FROM ai_articles WHERE id = ?
                 ) AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("si", $sessionId, $articleId);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows == 0) {
        // 閲覧数を更新
        $updateSql = "UPDATE ai_articles SET view_count = view_count + 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $articleId);
        $updateStmt->execute();
        
        echo json_encode(['success' => true, 'counted' => true]);
    } else {
        echo json_encode(['success' => true, 'counted' => false, 'message' => 'Already counted recently']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
