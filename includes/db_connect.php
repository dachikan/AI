<?php
// データベース接続情報
    $host = "mysql213.phy.lolipop.lan";
    $user = "LAA1337491";
    $password = "kami2004";
    $database = "LAA1337491-nsk";
// 関数が既に定義されているかチェック
$functions_defined = false;

// 接続エラーをキャッチするためのtry-catch
try {
    // MySQLi接続
    $conn = new mysqli($host, $user, $password, $database);

    // 接続エラーチェック
    if ($conn->connect_error) {
        throw new Exception("データベース接続エラー: " . $conn->connect_error);
    }

    // 文字セットをUTF-8に設定
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // エラーメッセージを表示
    echo '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;">';
    echo '<h3>データベース接続エラー</h3>';
    echo '<p>' . $e->getMessage() . '</p>';
    echo '<p>サーバー情報: ' . $host . ', ユーザー: ' . $user . ', データベース: ' . $database . '</p>';
    echo '<p>config/db_connect.phpの接続情報を確認してください。</p>';
    echo '</div>';
    
    // エラーログに記録
    error_log("DB接続エラー: " . $e->getMessage());
    
    // ダミーデータを使用するモードに設定
    $conn = null;
}

/**
* カテゴリごとの試行結果数を取得
*/
function getCategoryTrialCounts() {
    global $conn;
    
    $sql = "SELECT c.id as category_id, COUNT(r.id) as count 
            FROM AIPromptCategories c
            JOIN AIPromptTemplates t ON c.id = t.category_id
            JOIN AITrialResults r ON t.id = r.template_id
            GROUP BY c.id";
    
    $result = $conn->query($sql);
    $counts = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $counts[] = $row;
        }
    }
    
    return $counts;
}

/**
* AIタイプごとの試行結果数を取得
*/
function getAITypeTrialCounts() {
    global $conn;
    
    $sql = "SELECT a.id as ai_type_id, COUNT(r.id) as count 
            FROM AITypes a
            JOIN AITrialResults r ON a.id = r.ai_type_id
            GROUP BY a.id";
    
    $result = $conn->query($sql);
    $counts = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $counts[] = $row;
        }
    }
    
    return $counts;
}
// 以下の関数は一度だけ定義する
if (!function_exists('getCategories')) {
    /**
     * カテゴリ一覧を取得
     */
    function getCategories() {
        global $conn;
        
        // 接続がない場合はダミーデータを返す
        if ($conn === null) {
            return [['id' => 1, 'name' => 'サンプルカテゴリ']];
        }
        
        $sql = "SELECT * FROM AIPromptCategories ORDER BY name ASC";
        $result = $conn->query($sql);
        
        $categories = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        }
        
        return $categories;
    }

    /**
     * AIタイプ一覧を取得
     */
    function getAITypes() {
        global $conn;
        
        // 接続がない場合はダミーデータを返す
        if ($conn === null) {
            return [
                ['id' => 1, 'name' => 'ChatGPT', 'group' => 'テキスト生成AI'],
                ['id' => 2, 'name' => 'DALL-E', 'group' => '画像生成AI'],
                ['id' => 3, 'name' => 'Suno', 'group' => '音声・音楽生成AI']
            ];
        }
        
        $sql = "SELECT * FROM AITypes ORDER BY name ASC";
        $result = $conn->query($sql);
        
        $aiTypes = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $aiTypes[] = $row;
            }
        }
        
        return $aiTypes;
    }

    /**
     * プロンプト数を取得
     */
    function getPromptCount() {
        global $conn;
        
        // 接続がない場合は0を返す
        if ($conn === null) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM AIPromptTemplates";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['count'];
        }
        
        return 0;
    }

    /**
     * カテゴリごとのプロンプト数を取得
     */
    function getPromptCountByCategory() {
        global $conn;
        
        // 接続がない場合はダミーデータを返す
        if ($conn === null) {
            return [['id' => 1, 'name' => 'サンプルカテゴリ', 'count' => 0]];
        }
        
        $sql = "SELECT c.id, c.name, COUNT(t.id) as count 
                FROM AIPromptCategories c
                LEFT JOIN AIPromptTemplates t ON c.id = t.category_id
                GROUP BY c.id
                ORDER BY c.name ASC";
        
        $result = $conn->query($sql);
        
        $counts = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $counts[] = $row;
            }
        }
        
        return $counts;
    }

    /**
     * AIタイプごとのプロンプト数を取得
     */
    function getPromptCountByAIType() {
        global $conn;
        
        // 接続がない場合はダミーデータを返す
        if ($conn === null) {
            return [
                ['id' => 1, 'name' => 'ChatGPT', 'count' => 0],
                ['id' => 2, 'name' => 'DALL-E', 'count' => 0],
                ['id' => 3, 'name' => 'Suno', 'count' => 0]
            ];
        }
        
        $sql = "SELECT a.id, a.name, COUNT(t.id) as count 
                FROM AITypes a
                LEFT JOIN AIPromptTemplates t ON a.id = t.ai_type_id
                GROUP BY a.id
                ORDER BY a.name ASC";
        
        $result = $conn->query($sql);
        
        $counts = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $counts[] = $row;
            }
        }
        
        return $counts;
    }

    /**
     * 指定されたカテゴリとAIタイプに該当するテンプレート数を取得
     */
    function getTemplateCount($categoryId, $aiTypeId) {
        global $conn;
        
        // 接続がない場合は0を返す
        if ($conn === null) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM AIPromptTemplates 
                WHERE category_id = ? AND ai_type_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $categoryId, $aiTypeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] ?? 0;
    }

    /**
     * 指定されたカテゴリとAIタイプに該当するテンプレート一覧を取得
     */
    function getTemplates($categoryId, $aiTypeId) {
        global $conn;
        
        // 接続がない場合は空の配列を返す
        if ($conn === null) {
            return [];
        }
        
        $sql = "SELECT * FROM AIPromptTemplates 
                WHERE category_id = ? AND ai_type_id = ? 
                ORDER BY name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $categoryId, $aiTypeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $templates = [];
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
        
        return $templates;
    }

    /**
     * 指定されたIDのテンプレートを取得
     */
    function getTemplateById($templateId) {
        global $conn;
        
        // 接続がない場合はnullを返す
        if ($conn === null) {
            return null;
        }
        
        $sql = "SELECT * FROM AIPromptTemplates WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $templateId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    /**
     * テンプレートから変数を抽出
     */
    function extractTemplateVariables($content) {
        preg_match_all('/\{([^}]+)\}/', $content, $matches);
        return $matches[1] ?? [];
    }

    /**
     * テンプレートを保存
     */
    function saveTemplate($name, $content, $categoryId, $aiTypeId, $templateId = null) {
        global $conn;
        
        // 接続がない場合はfalseを返す
        if ($conn === null) {
            return false;
        }
        
        if ($templateId) {
            // 更新
            $sql = "UPDATE AIPromptTemplates 
                    SET name = ?, content = ?, category_id = ?, ai_type_id = ?, updated_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiii", $name, $content, $categoryId, $aiTypeId, $templateId);
        } else {
            // 新規作成
            $sql = "INSERT INTO AIPromptTemplates (name, content, category_id, ai_type_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $name, $content, $categoryId, $aiTypeId);
        }
        
        return $stmt->execute();
    }

    /**
     * テンプレートを削除
     */
    function deleteTemplate($templateId) {
        global $conn;
        
        // 接続がない場合はfalseを返す
        if ($conn === null) {
            return false;
        }
        
        $sql = "DELETE FROM AIPromptTemplates WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $templateId);
        
        return $stmt->execute();
    }

    /**
     * 試行結果を保存
     */
    function saveTrialResult($templateId, $aiTypeId, $prompt, $result, $userId = null) {
        global $conn;
        
        // 接続がない場合はfalseを返す
        if ($conn === null) {
            return false;
        }
        
        $sql = "INSERT INTO AITrialResults (template_id, ai_type_id, prompt, result, user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissi", $templateId, $aiTypeId, $prompt, $result, $userId);
        
        return $stmt->execute();
    }

    /**
     * エスケープ処理
     */
    function escape($string) {
        global $conn;
        
        // 接続がない場合はそのまま返す
        if ($conn === null) {
            return $string;
        }
        
        return $conn->real_escape_string($string);
    }

    /**
     * SQLインジェクション対策済みのクエリ実行
     */
    function safeQuery($sql, $params = []) {
        global $conn;
        
        // 接続がない場合はnullを返す
        if ($conn === null) {
            return null;
        }
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($params)) {
            $types = '';
            $bindParams = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
                
                $bindParams[] = $param;
            }
            
            $bindValues = array_merge([$types], $bindParams);
            call_user_func_array([$stmt, 'bind_param'], $bindValues);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
}
if (!function_exists('getAIServices')) {
    /**
     * AIサービス一覧を取得（絞り込み・並べ替え対応）
     */
    function getAIServices($filters = [], $orderBy = 'ai_service', $orderDir = 'ASC', $limit = null) {
        global $conn;
        
        if ($conn === null) {
            return [];
        }
        
        $sql = "SELECT * FROM AIInfo WHERE is_active = 1";
        $params = [];
        $types = '';
        
        // 絞り込み条件
        if (!empty($filters['recommended'])) {
            $sql .= " AND popularity_score >= 80";
        }
        
        if (!empty($filters['free'])) {
            $sql .= " AND free_tier_available = 1";
        } elseif (!empty($filters['paid'])) {
            $sql .= " AND free_tier_available = 0";
        }
        
        if (!empty($filters['ai_type_id'])) {
            $sql .= " AND ai_type_id = ?";
            $params[] = $filters['ai_type_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (ai_service LIKE ? OR description LIKE ? OR company_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        // 並べ替え
        $allowedOrderBy = ['ai_service', 'popularity_score', 'release_date', 'last_updated_info'];
        if (in_array($orderBy, $allowedOrderBy)) {
            $sql .= " ORDER BY " . $orderBy . " " . ($orderDir === 'DESC' ? 'DESC' : 'ASC');
        }
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        $services = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $services[] = $row;
            }
        }
        
        return $services;
    }
}

if (!function_exists('getAIServiceById')) {
    /**
     * AIサービス詳細を取得
     */
    function getAIServiceById($id) {
        global $conn;
        
        if ($conn === null) {
            return null;
        }
        
        $sql = "SELECT * FROM AIInfo WHERE id = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
}

if (!function_exists('getPopularityRanking')) {
    /**
     * 人気ランキングを取得
     */
    function getPopularityRanking($limit = 10) {
        global $conn;
        
        if ($conn === null) {
            return [];
        }
        
        $sql = "SELECT * FROM AIInfo WHERE is_active = 1 ORDER BY popularity_score DESC, ai_service ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ranking = [];
        while ($row = $result->fetch_assoc()) {
            $ranking[] = $row;
        }
        
        return $ranking;
    }
}

if (!function_exists('getAIServicesForComparison')) {
    /**
     * 比較用データを取得
     */
    function getAIServicesForComparison($ids) {
        global $conn;
        
        if ($conn === null || empty($ids)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT * FROM AIInfo WHERE id IN ($placeholders) AND is_active = 1 ORDER BY popularity_score DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        
        return $services;
    }
}
?>
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
