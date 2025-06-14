<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

try {
    // 認証済み記事を取得
    $sql = "SELECT a.*, u.note_username, ai.ai_service as ai_service_name 
            FROM ai_articles a 
            JOIN ai_users u ON a.user_id = u.id 
            LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id 
            WHERE a.status = 'verified' 
            ORDER BY a.created_at DESC 
            LIMIT 50";
    
    $result = $conn->query($sql);
    $articles = $result->fetch_all(MYSQLI_ASSOC);
    
    // 閲覧数を更新（簡易実装）
    foreach ($articles as &$article) {
        $updateSql = "UPDATE ai_articles SET view_count = view_count + 1 WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("i", $article['id']);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'articles' => $articles
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'エラーが発生しました: ' . $e->getMessage()
    ]);
}
?>
