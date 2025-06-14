<?php

/**
 * AI体験を記録する
 */
function recordExperience($sessionId, $aiServiceId, $experienceData) {
    global $conn;
    
    $sql = "INSERT INTO ai_experience_logs (session_id, ai_service_id, experience_data) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $experienceJson = json_encode($experienceData);
    $stmt->bind_param("sis", $sessionId, $aiServiceId, $experienceJson);
    
    return $stmt->execute();
}

/**
 * note URLの妥当性をチェック
 */
function validateNoteUrl($url) {
    return preg_match('/^https:\/\/note\.com\/[^\/]+\/n\/[^\/]+$/', $url);
}

/**
 * note記事の内容を取得・解析
 */
function analyzeNoteArticle($url) {
    try {
        // User-Agentを設定してHTTPリクエスト
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Mozilla/5.0 (compatible; AI Experience Bot/1.0)',
                'timeout' => 10
            ]
        ]);
        
        $html = file_get_contents($url, false, $context);
        if ($html === false) {
            return ['success' => false, 'message' => '記事の取得に失敗しました'];
        }
        
        // HTMLからタイトルを抽出
        preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $titleMatches);
        $title = isset($titleMatches[1]) ? html_entity_decode(strip_tags($titleMatches[1])) : '';
        
        // noteのユーザー名を抽出
        preg_match('/note\.com\/([^\/]+)\//', $url, $usernameMatches);
        $username = isset($usernameMatches[1]) ? $usernameMatches[1] : '';
        
        // 記事の本文を簡易的に抽出（実際にはより詳細な解析が必要）
        $content = strip_tags($html);
        
        return [
            'success' => true,
            'title' => $title,
            'username' => $username,
            'content' => $content,
            'summary' => mb_substr($content, 0, 200) . '...'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '記事の解析中にエラーが発生しました: ' . $e->getMessage()];
    }
}

/**
 * 新規記事を処理
 */
function processNewArticle($sessionId, $noteUrl, $noteUsername, $email) {
    global $conn;
    
    // 記事の解析
    $analysis = analyzeNoteArticle($noteUrl);
    if (!$analysis['success']) {
        return $analysis;
    }
    
    // ユーザーの作成または取得
    $userId = createOrGetUser($noteUsername, $email, 'note_auth');
    if (!$userId) {
        return ['success' => false, 'message' => 'ユーザーの作成に失敗しました'];
    }
    
    // 体験ログを取得
    $experienceLog = getExperienceLogBySession($sessionId);
    if (!$experienceLog) {
        return ['success' => false, 'message' => '体験ログが見つかりません'];
    }
    
    // 記事を登録
    $sql = "INSERT INTO ai_articles (user_id, ai_service_id, url, title, summary, article_type, experience_log_id, status) 
            VALUES (?, ?, ?, ?, ?, 'new_post', ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssi", $userId, $experienceLog['ai_service_id'], $noteUrl, 
                      $analysis['title'], $analysis['summary'], $experienceLog['id']);
    
    if ($stmt->execute()) {
        $articleId = $conn->insert_id;
        
        // 体験ログにユーザーIDを更新
        updateExperienceLogUser($experienceLog['id'], $userId);
        
        return ['success' => true, 'article_id' => $articleId];
    } else {
        return ['success' => false, 'message' => '記事の登録に失敗しました'];
    }
}

/**
 * 既存記事を処理
 */
function processExistingArticle($noteUrl, $noteUsername, $email, $aiServiceId) {
    global $conn;
    
    // 記事の解析
    $analysis = analyzeNoteArticle($noteUrl);
    if (!$analysis['success']) {
        return $analysis;
    }
    
    // ユーザーの作成または取得
    $userId = createOrGetUser($noteUsername, $email, 'note_auth');
    if (!$userId) {
        return ['success' => false, 'message' => 'ユーザーの作成に失敗しました'];
    }
    
    // 記事を登録（既存記事は即座にverifiedステータス）
    $sql = "INSERT INTO ai_articles (user_id, ai_service_id, url, title, summary, article_type, status) 
            VALUES (?, ?, ?, ?, ?, 'existing_post', 'verified')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $userId, $aiServiceId, $noteUrl, $analysis['title'], $analysis['summary']);
    
    if ($stmt->execute()) {
        return ['success' => true, 'article_id' => $conn->insert_id];
    } else {
        return ['success' => false, 'message' => '記事の登録に失敗しました'];
    }
}

/**
 * ユーザーを作成または取得
 */
function createOrGetUser($noteUsername, $email, $authType) {
    global $conn;
    
    // 既存ユーザーをチェック
    $sql = "SELECT id FROM ai_users WHERE note_username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $noteUsername);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // 新規ユーザーを作成
    $sql = "INSERT INTO ai_users (note_username, email, auth_type) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $noteUsername, $email, $authType);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * セッションIDで体験ログを取得
 */
function getExperienceLogBySession($sessionId) {
    global $conn;
    
    $sql = "SELECT * FROM ai_experience_logs WHERE session_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * 体験ログにユーザーIDを更新
 */
function updateExperienceLogUser($logId, $userId) {
    global $conn;
    
    $sql = "UPDATE ai_experience_logs SET user_id = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $logId);
    
    return $stmt->execute();
}

/**
 * 記事IDで記事を取得
 */
function getArticleById($articleId) {
    global $conn;
    
    $sql = "SELECT a.*, u.note_username, ai.ai_service as ai_service_name 
            FROM ai_articles a 
            JOIN ai_users u ON a.user_id = u.id 
            LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id 
            WHERE a.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $articleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * AIサービス一覧を取得
 */
function getAIServices() {
    global $conn;
    
    $sql = "SELECT id, ai_service FROM AIInfo ORDER BY ai_service";
    $result = $conn->query($sql);
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>
