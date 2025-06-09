<?php
/**
 * 投稿関連の関数
 */

/**
 * 指定されたAIサービスの投稿テンプレートを取得
 */
function getPostTemplates($aiServiceId) {
    global $conn;
    
    if ($conn === null) {
        return [];
    }
    
    $sql = "SELECT * FROM AInote_PostTemplate WHERE ai_service_id = ? AND is_active = 1 ORDER BY template_type";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $aiServiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    
    return $templates;
}

/**
 * 特定のテンプレートを取得
 */
function getPostTemplate($aiServiceId, $templateType) {
    global $conn;
    
    if ($conn === null) {
        return null;
    }
    
    $sql = "SELECT * FROM AInote_PostTemplate WHERE ai_service_id = ? AND template_type = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $aiServiceId, $templateType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * テンプレートの変数を実際の値に置換
 */
function processTemplate($template, $serviceData, $userData) {
    $title = $template['title_template'];
    $content = $template['content_template'];
    $hashtags = $template['hashtags'];
    
    // 基本的な置換
    $replacements = [
        '{AI_SERVICE_NAME}' => $serviceData['ai_service'],
        '{USAGE_PURPOSE}' => $userData['usage_purpose'],
        '{USER_REVIEW}' => $userData['user_review'],
        '{USER_RATING}' => $userData['user_rating'],
        '{OFFICIAL_URL}' => $serviceData['official_url'] ?? '',
        '{POST_URL}' => $userData['post_url'] ?? ''
    ];
    
    foreach ($replacements as $placeholder => $value) {
        $title = str_replace($placeholder, $value, $title);
        $content = str_replace($placeholder, $value, $content);
        $hashtags = str_replace($placeholder, $value, $hashtags);
    }
    
    // ハッシュタグを本文に挿入
    $content = str_replace('{HASHTAGS}', $hashtags, $content);
    
    return [
        'title' => $title,
        'content' => $content,
        'hashtags' => $hashtags
    ];
}

/**
 * noteの投稿URLを生成
 */
function generateNoteUrl($template, $title, $content) {
    if (empty($template['note_url_template'])) {
        return null;
    }
    
    $url = $template['note_url_template'];
    $url = str_replace('{TITLE}', urlencode($title), $url);
    $url = str_replace('{CONTENT}', urlencode($content), $url);
    
    return $url;
}

/**
 * 投稿履歴を保存（将来の機能拡張用）
 */
function savePostHistory($aiServiceId, $templateType, $userData, $generatedContent) {
    global $conn;
    
    if ($conn === null) {
        return false;
    }
    
    $sql = "INSERT INTO user_posts (ai_service_id, post_type, post_title, usage_purpose, user_review, user_rating, hashtags_used, session_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $sessionId = session_id();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $postType = $templateType === 'new_post' ? 'new_article' : 'existing_article';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssisss", 
        $aiServiceId, 
        $postType, 
        $generatedContent['title'],
        $userData['usage_purpose'],
        $userData['user_review'],
        $userData['user_rating'],
        $generatedContent['hashtags'],
        $sessionId,
        $ipAddress
    );
    
    return $stmt->execute();
}
?>
