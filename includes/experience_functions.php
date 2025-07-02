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
 * note記事の情報を解析する関数
 * 
 * @param string $url note記事のURL
 * @return array 記事情報
 */
function analyzeNoteArticle($url) {
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    // URLの正規化
    $url = trim($url);
    if (strpos($url, 'http') !== 0) {
        $url = 'https://' . $url;
    }
    
    // noteのURLかチェック
    if (strpos($url, 'note.com') === false && strpos($url, 'note.mu') === false) {
        return [
            'success' => false,
            'error' => '無効なnoteのURLです'
        ];
    }
    
    // HTMLを取得
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: ' . $userAgent,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                'Connection: keep-alive',
            ],
            'timeout' => 30,
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    
    if ($html === false) {
        return [
            'success' => false,
            'error' => 'HTMLの取得に失敗しました'
        ];
    }
    
    // DOMDocumentでHTMLを解析
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // タイトルを取得
    $title = 'タイトル不明';
    $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
    if ($ogTitle->length > 0) {
        $title = trim($ogTitle->item(0)->nodeValue);
    } else {
        $titleTag = $xpath->query('//title');
        if ($titleTag->length > 0) {
            $title = trim(str_replace('｜note', '', $titleTag->item(0)->nodeValue));
        }
    }
    
    // サムネイル画像URLを取得
    $thumbnailUrl = null;
    $ogImage = $xpath->query('//meta[@property="og:image"]/@content');
    if ($ogImage->length > 0) {
        $thumbnailUrl = $ogImage->item(0)->nodeValue;
        // 相対URLを絶対URLに変換
        if (strpos($thumbnailUrl, '//') === 0) {
            $thumbnailUrl = 'https:' . $thumbnailUrl;
        } elseif (strpos($thumbnailUrl, '/') === 0) {
            $thumbnailUrl = 'https://note.com' . $thumbnailUrl;
        }
    }
    
    // ユーザー名を取得
    $username = '著者不明';
    $ogSiteName = $xpath->query('//meta[@property="og:site_name"]/@content');
    if ($ogSiteName->length > 0) {
        $username = trim($ogSiteName->item(0)->nodeValue);
    } else {
        // URLからユーザー名を抽出
        if (preg_match('/note\.com\/([^\/]+)\//', $url, $matches)) {
            $username = $matches[1];
        }
    }
    
    // 概要を取得
    $summary = '';
    $ogDescription = $xpath->query('//meta[@property="og:description"]/@content');
    if ($ogDescription->length > 0) {
        $summary = trim($ogDescription->item(0)->nodeValue);
        // 長すぎる場合は切り詰める
        if (mb_strlen($summary) > 150) {
            $summary = mb_substr($summary, 0, 150) . '...';
        }
    }
    
    return [
        'success' => true,
        'title' => $title,
        'thumbnail_url' => $thumbnailUrl,
        'username' => $username,
        'summary' => $summary,
    ];
}


/**
 * noteユーザーの情報を取得（アバターなど） (JSONデータ抽出方式)
 */
function getNoteUserInfo($username) {
    try {
        $url = 'https://note.com/' . rawurlencode($username);
        $html = file_get_contents($url);
        if ($html === false || empty($html)) {
            return ['success' => false, 'avatar_url' => ''];
        }

        preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/', $html, $matches);
        if (empty($matches[1])) {
            return ['success' => false, 'avatar_url' => ''];
        }

        $jsonData = json_decode($matches[1]);
        if (json_last_error() !== JSON_ERROR_NONE) {
             return ['success' => false, 'avatar_url' => ''];
        }

        // ユーザーページのJSON構造からアバター画像を取得
        $avatarUrl = $jsonData->props->pageProps->page->user->profileImageUrl ?? '';

        return ['success' => true, 'avatar_url' => $avatarUrl];

    } catch (Exception $e) {
        return ['success' => false, 'avatar_url' => ''];
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
    // ★★★SQL文を変更★★★
    $sql = "INSERT INTO ai_articles (user_id, ai_service_id, url, title, summary, thumbnail_url, article_type, experience_log_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'new_post', ?, 'pending')";
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
    // ★★★SQL文を変更★★★
    $sql = "INSERT INTO ai_articles (user_id, ai_service_id, url, title, summary, thumbnail_url, article_type, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'existing_post', 'verified')";
    $stmt = $conn->prepare($sql);
    // ★★★bind_paramを変更★★★
    $stmt->bind_param("iissssi", $userId, $aiServiceId, $noteUrl, $analysis['title'], $analysis['summary'], $analysis['thumbnail_url']);
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
    
// ★★★ここから変更★★★
    // 新規ユーザーのアバター情報を取得
    $userInfo = getNoteUserInfo($noteUsername);
    $avatarUrl = $userInfo['success'] ? $userInfo['avatar_url'] : '';

    // 新規ユーザーを作成
    $sql = "INSERT INTO ai_users (note_username, email, avatar_url, auth_type) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $noteUsername, $email, $avatarUrl, $authType);
    // ★★★ここまで変更★★★
    
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
