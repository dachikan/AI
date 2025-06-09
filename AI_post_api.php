<?php
/**
 * 投稿処理用API
 */
require_once 'includes/db_connect.php';
require_once 'includes/post_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 入力検証
    $requiredFields = ['ai_service_id', 'template_type', 'usage_purpose', 'user_review', 'user_rating'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("必須フィールド '{$field}' が不足しています");
        }
    }
    
    $aiServiceId = intval($input['ai_service_id']);
    $templateType = $input['template_type'];
    
    // AIサービス情報を取得
    $serviceData = getAIServiceById($aiServiceId);
    if (!$serviceData) {
        throw new Exception('指定されたAIサービスが見つかりません');
    }
    
    // テンプレートを取得
    $template = getPostTemplate($aiServiceId, $templateType);
    if (!$template) {
        throw new Exception('指定されたテンプレートが見つかりません');
    }
    
    // テンプレート処理
    $userData = [
        'usage_purpose' => $input['usage_purpose'],
        'user_review' => $input['user_review'],
        'user_rating' => $input['user_rating'],
        'post_url' => $input['post_url'] ?? ''
    ];
    
    $generatedContent = processTemplate($template, $serviceData, $userData);
    
    // noteURL生成
    $noteUrl = generateNoteUrl($template, $generatedContent['title'], $generatedContent['content']);
    
    // 投稿履歴保存（オプション）
    if (isset($input['save_history']) && $input['save_history']) {
        savePostHistory($aiServiceId, $templateType, $userData, $generatedContent);
    }
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'data' => [
            'title' => $generatedContent['title'],
            'content' => $generatedContent['content'],
            'hashtags' => $generatedContent['hashtags'],
            'note_url' => $noteUrl,
            'service_name' => $serviceData['ai_service']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
