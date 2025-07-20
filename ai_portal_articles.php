<?php
require_once 'includes/db_connect.php';

$pageTitle = 'AI活用体験記事一覧 - AI活用体験ポータル';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ページネーション設定
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 35;
$offset = ($page - 1) * $limit;

// フィルター設定
$articleType = isset($_GET['article_type']) ? trim($_GET['article_type']) : '';
$verifiedOnly = isset($_GET['verified']) ? $_GET['verified'] === '1' : false;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$showDebug = isset($_GET['debug']) ? $_GET['debug'] === '1' : false;

// デバッグ情報の初期化
$debugInfo = '';

// デバッグ情報をさらに強化
if ($showDebug) {
    $debugInfo .= "<div style='background: #fff3cd; padding: 10px; margin: 10px; border: 1px solid #ffeaa7;'>";
    $debugInfo .= "<h5>🔍 詳細デバッグ - フィルター適用前後の比較:</h5>";
    
    // フィルター適用前の件数
    $beforeFilterSql = "SELECT COUNT(*) as count FROM ai_articles a LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id WHERE a.status = 'verified'";
    $beforeResult = $conn->query($beforeFilterSql);
    if ($beforeResult) {
        $beforeCount = $beforeResult->fetch_assoc()['count'];
        $debugInfo .= "フィルター適用前の総件数: " . $beforeCount . "件<br>";
    }
    
    // 記事タイプフィルターのみ適用
    if (!empty($articleType)) {
        $typeOnlySql = "SELECT COUNT(*) as count FROM ai_articles a LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id WHERE a.status = 'verified' AND a.article_type = ?";
        $typeOnlyStmt = $conn->prepare($typeOnlySql);
        $typeOnlyStmt->bind_param('s', $articleType);
        $typeOnlyStmt->execute();
        $typeOnlyCount = $typeOnlyStmt->get_result()->fetch_assoc()['count'];
        $debugInfo .= "記事タイプ '{$articleType}' のみフィルター: " . $typeOnlyCount . "件<br>";
        
        // AIサービスとのJOINで失われる記事があるかチェック
        $noJoinSql = "SELECT COUNT(*) as count FROM ai_articles a WHERE a.status = 'verified' AND a.article_type = ?";
        $noJoinStmt = $conn->prepare($noJoinSql);
        $noJoinStmt->bind_param('s', $articleType);
        $noJoinStmt->execute();
        $noJoinCount = $noJoinStmt->get_result()->fetch_assoc()['count'];
        $debugInfo .= "JOIN前の記事タイプ '{$articleType}' 件数: " . $noJoinCount . "件<br>";
        
        if ($noJoinCount != $typeOnlyCount) {
            $debugInfo .= "<strong style='color: red;'>⚠️ JOINで失われている記事があります！</strong><br>";
        }
    }
    
    // 検索条件の影響をチェック
    if (!empty($searchTerm)) {
        $debugInfo .= "検索キーワード: '" . htmlspecialchars($searchTerm) . "'<br>";
        
        if (!empty($articleType)) {
            $searchTestSql = "SELECT COUNT(*) as count FROM ai_articles a LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id 
                             WHERE a.status = 'verified' AND a.article_type = ? AND (a.title LIKE ? OR a.summary LIKE ?)";
            $searchTestStmt = $conn->prepare($searchTestSql);
            $searchPattern = '%' . $searchTerm . '%';
            $searchTestStmt->bind_param('sss', $articleType, $searchPattern, $searchPattern);
            $searchTestStmt->execute();
            $searchTestCount = $searchTestStmt->get_result()->fetch_assoc()['count'];
            $debugInfo .= "記事タイプ + 検索条件: " . $searchTestCount . "件<br>";
        }
    }
    
    $debugInfo .= "</div>";
}

// WHERE条件の構築（1回のみ）
$whereConditions = [];
$params = [];
$types = '';

// 基本条件（必須）
// $whereConditions[] = "a.status = 'verified'";

// 確認済みフィルター
// if ($verifiedOnly) {
//     $whereConditions[] = "a.is_verified = 1";
// }

// 記事タイプフィルター（記事の種類）
if (!empty($articleType)) {
    $whereConditions[] = "a.article_type = ?";
    $params[] = $articleType;
    $types .= 's';
}

// 検索フィルターの条件を緩和
if (!empty($searchTerm)) {
    // より柔軟な検索条件に変更
    $whereConditions[] = "(a.title LIKE ? OR a.summary LIKE ? OR ai.ai_service LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= 'sss';
}

// メインクエリを構築
$sql = "SELECT 
    a.id, a.url, a.title, a.summary, a.article_type, a.status, a.is_verified,
    a.published_at, a.view_count, a.helpful_count, a.created_at,
    a.thumbnail_url,
    COALESCE(ai.ai_service, 'Unknown AI') as ai_service, 
    COALESCE(ai.id, 0) as ai_service_id,
    c.name as category_name,
    COALESCE(au.note_username, 'Unknown User') as note_username,
    au.avatar_url,
    COALESCE(SUBSTRING(a.summary, 1, 120), SUBSTRING(a.title, 1, 120), 'AI活用体験記事') as preview_text
FROM ai_articles a
LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id
LEFT JOIN ai_users au ON a.user_id = au.id
LEFT JOIN AIPromptCategories c ON a.category_id = c.id";

// WHERE条件を追加
if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

$sql .= " ORDER BY a.id DESC LIMIT ? OFFSET ?";

$mainParams = $params;
$mainParams[] = $limit;
$mainParams[] = $offset;
$mainTypes = $types . 'ii';

if ($showDebug) {
    $debugInfo .= "<div style='background: #e8f4f8; padding: 10px; margin: 10px; border: 1px solid #bee5eb;'>";
    $debugInfo .= "<h5>実行SQL:</h5>";
    $debugInfo .= "<pre>" . htmlspecialchars($sql) . "</pre>";
    $debugInfo .= "パラメータ: " . print_r($mainParams, true) . "<br>";
    $debugInfo .= "</div>";
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Prepare failed: ' . $conn->error);
}

if (!empty($mainParams)) {
    if (!$stmt->bind_param($mainTypes, ...$mainParams)) {
        die('Bind param failed: ' . $stmt->error);
    }
}

if (!$stmt->execute()) {
    die('Execute failed: ' . $stmt->error);
}

$result = $stmt->get_result();
$articles = $result->fetch_all(MYSQLI_ASSOC);

// 総記事数を取得
$countSql = "SELECT COUNT(*) as total FROM ai_articles a 
             LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id
             WHERE a.status = 'verified'";

$countParams = [];
$countTypes = '';

if ($verifiedOnly) {
    $countSql .= " AND a.is_verified = 1";
}

if (!empty($articleType)) {
    $countSql .= " AND a.article_type = ?";
    $countParams[] = $articleType;
    $countTypes .= 's';
}

// if (!empty($searchTerm)) {
//     $countSql .= " AND (a.title LIKE ? OR a.summary LIKE ?)";
//     $countParams[] = $searchPattern;
//     $countParams[] = $searchPattern;
//     $countTypes .= 'ss';
// }

if (!empty($countParams)) {
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($countTypes, ...$countParams);
    $countStmt->execute();
    $totalArticles = $countStmt->get_result()->fetch_assoc()['total'];
} else {
    $countResult = $conn->query($countSql);
    $totalArticles = $countResult->fetch_assoc()['total'];
}

// データベース整合性チェック
if ($showDebug) {
    $debugInfo .= "<div style='background: #f8d7da; padding: 10px; margin: 10px; border: 1px solid #f5c6cb;'>";
    $debugInfo .= "<h5>🔧 データベース整合性チェック:</h5>";
    
    $integrityCheck = "SELECT 
        COUNT(*) as total_articles,
        COUNT(a.ai_service_id) as articles_with_ai_service_id,
        COUNT(ai.id) as articles_with_valid_ai_service
        FROM ai_articles a 
        LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id 
        WHERE a.status = 'verified'";
    
    $integrityResult = $conn->query($integrityCheck);
    if ($integrityResult) {
        $integrity = $integrityResult->fetch_assoc();
        $debugInfo .= "総記事数: {$integrity['total_articles']}<br>";
        $debugInfo .= "ai_service_idがある記事: {$integrity['articles_with_ai_service_id']}<br>";
        $debugInfo .= "有効なAIサービスがある記事: {$integrity['articles_with_valid_ai_service']}<br>";
        
        if ($integrity['total_articles'] != $integrity['articles_with_valid_ai_service']) {
            $debugInfo .= "<strong style='color: red;'>⚠️ AIサービス情報が不完全な記事があります</strong><br>";
        }
    }
    
    $debugInfo .= "</div>";
}


// 記事タイプの定義を一元化
function getArticleTypes() {
    return [
        'programming' => 'プログラミング',
        'shop' => 'ショップ運営',
        'economy' => '経済情報',
        'technical' => '技術知識',
        'scenario' => 'シナリオ',
        'writing' => '執筆',
        'profit' => '利益',
        'agriculture' => '農業',
        'drawing' => '作画・イラスト',
        'office' => '職場オフィス',
        'warry' => '心配事',
        'what' => 'AIとは'
    ];
}

// 記事タイプの日本語名を取得する関数を修正
function getArticleTypeLabel($articleType) {
    $types = getArticleTypes();
    return $types[$articleType] ?? $articleType;
}

// データベースから実際に使用されている記事タイプを取得
function getUsedArticleTypes() {
    global $conn;
    $sql = "SELECT DISTINCT article_type, COUNT(*) as count 
            FROM ai_articles 
            WHERE article_type IS NOT NULL AND article_type != ''
            GROUP BY article_type 
            ORDER BY count DESC";
    
    $result = $conn->query($sql);
    $usedTypes = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usedTypes[$row['article_type']] = $row['count'];
        }
    }
    
    return $usedTypes;
}

$totalPages = ceil($totalArticles / $limit);

// ページネーション用のURL生成関数
function buildPaginationUrl($targetPage, $currentParams) {
    $params = $currentParams;
    $params['page'] = $targetPage;
    return '?' . http_build_query($params);
}

// 現在のフィルター・ソートパラメータを保持（categoryを削除）
$currentParams = [];
if (!empty($articleType)) $currentParams['article_type'] = $articleType;
if ($verifiedOnly) $currentParams['verified'] = '1';
if (!empty($searchTerm)) $currentParams['search'] = $searchTerm;
if ($showDebug) $currentParams['debug'] = '1';

include 'includes/header.php';
?>

<style>
    .article-type-badge {
    display: inline-block;
    background-color: #e9ecef;
    color: #495057;
    font-size: 0.7rem;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 12px;
    margin-bottom: 4px;
    vertical-align: middle;
    }

    /* 記事タイプ別の色分け */
    .article-type-badge.programming { background-color: #d4edda; color: #155724; }
    .article-type-badge.shop { background-color: #d1ecf1; color: #0c5460; }
    .article-type-badge.economy { background-color: #fff3cd; color: #856404; }
    .article-type-badge.technical { background-color: #f8d7da; color: #721c24; }
    .article-type-badge.agriculture { background-color: #d4edda; color: #155724; }
    .article-type-badge.office { background-color: #e2e3e5; color: #383d41; }
    .article-type-badge.warry { background-color: #f5c6cb; color: #721c24; }
    .article-type-badge.writing { background-color: #cce5ff; color: #004085; }
    .article-type-badge.profit { background-color: #fff3cd; color: #856404; }
    .article-type-badge.drawing { background-color: #e7e3ff; color: #5a2d82; }
    .article-type-badge.scenario { background-color: #ffe6cc; color: #8b4513; }
    .article-type-badge.what { background-color: #f0f0f0; color: #333; }
    /* === note風グリッドレイアウト用CSS === */
    .articles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 24px;
    }

    .article-card {
        background-color: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.2s ease-in-out;
        cursor: pointer;
        display: flex;
        flex-direction: column;
    }
    .article-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    }

    .card-thumbnail-wrapper {
        position: relative;
    }

    .card-verified-badge {
        position: absolute;
        top: 8px;
        left: 8px;
        background-color: #28a745;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        z-index: 2;
    }

    .card-thumbnail {
        width: 100%;
        padding-top: 56.25%; /* アスペクト比 16:9 */
        background-size: cover;
        background-position: center;
        background-color: #f0f0f0; /* 画像がない場合の背景色 */
        border-bottom: 1px solid #eee;
    }

    .card-content {
        padding: 12px;
        display: flex;
        flex-direction: column;
        flex-grow: 1; /* 高さを揃えるため */
    }

    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        line-height: 1.5;
        color: #333;
        margin: 0 0 8px 0;
        flex-grow: 1; /* タイトルエリアの高さを可変に */
        display: -webkit-box;
        -webkit-line-clamp: 3; /* 3行までに制限 */
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 8px;
        border-top: 1px solid #f5f5f5;
        margin-top: auto; /* フッターをカード下部に固定 */
    }

    .author-info {
        display: flex;
        align-items: center;
        font-size: 0.75rem;
        color: #555;
        overflow: hidden; /* はみ出し防止 */
    }

    .author-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        margin-right: 8px;
        object-fit: cover;
        background-color: #ddd; /* アバターがない場合の背景色 */
    }

    .author-name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .card-stats {
        display: flex;
        align-items: center;
        font-size: 0.8rem;
        color: #777;
        flex-shrink: 0; /* 縮まないようにする */
    }
    .card-stats i {
        margin-right: 4px;
    }

    /* ページネーション用CSS */
    .pagination {
        justify-content: center;
        margin-top: 2rem;
    }

    .pagination .page-link {
        color: #007bff;
        border: 1px solid #dee2e6;
        padding: 0.5rem 0.75rem;
        margin: 0 2px;
        border-radius: 4px;
        text-decoration: none;
        transition: all 0.2s ease-in-out;
    }

    .pagination .page-link:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
    }

    .pagination .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
        color: white;
    }

    .pagination .page-item.disabled .page-link {
        color: #6c757d;
        background-color: #fff;
        border-color: #dee2e6;
        cursor: not-allowed;
    }

    .pagination-info {
        text-align: center;
        margin-top: 1rem;
        color: #6c757d;
        font-size: 0.9rem;
    }
    .card-summary {
        font-size: 0.8rem;
        color: #666;
        margin: 0 0 8px 0;
        display: -webkit-box;
        -webkit-line-clamp: 2; /* 2行までに制限 */
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    /* ポータルヘッダーにデバッグボタンを追加 */
    .portal-header {
        position: relative;
    }

    .debug-toggle {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 8px 12px;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        font-size: 0.9rem;
        text-decoration: none;
    }

    .debug-toggle:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        text-decoration: none;
    }

    .debug-toggle.active {
        background: rgba(255, 255, 255, 0.9);
        color: #333;
    }

    /* メインフィルターのスタイル調整 */
    .main-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
        align-items: flex-end;
    }

    .article-type-filter {
        display: flex;
        flex-direction: column;
        min-width: 200px;
    }

    .article-type-filter label {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 5px;
        color: #495057;
    }

    .article-type-filter select {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background-color: white;
        font-size: 0.9rem;
        transition: border-color 0.2s ease-in-out;
    }

    .article-type-filter select:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .search-form {
        display: flex;
        gap: 10px;
        align-items: flex-end;
        flex: 1;
        min-width: 300px;
    }

    .search-input-group {
        flex: 1;
        min-width: 200px;
    }

    .search-input-group label {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 5px;
        color: #495057;
        display: block;
    }

    .search-input-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 0.9rem;
        transition: border-color 0.2s ease-in-out;
    }

    .search-input-group input:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .action-btn {
        background: #fff;
        border: 1px solid #dee2e6;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        text-decoration: none;
        color: #495057;
        font-size: 0.9rem;
        white-space: nowrap;
    }

    .action-btn:hover {
        background: #e9ecef;
        border-color: #adb5bd;
        color: #495057;
        text-decoration: none;
    }

    .action-btn.primary {
        background: #007bff;
        border-color: #007bff;
        color: white;
    }

    .action-btn.primary:hover {
        background: #0056b3;
        border-color: #0056b3;
        color: white;
    }

    .search-btn {
        background: #007bff;
        border: 1px solid #007bff;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        font-size: 0.9rem;
        white-space: nowrap;
    }

    .search-btn:hover {
        background: #0056b3;
        border-color: #0056b3;
    }

    /* レスポンシブ対応 */
    @media (max-width: 768px) {
        .main-filters {
            flex-direction: column;
            align-items: stretch;
        }
        
        .article-type-filter {
            min-width: 100%;
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .search-form {
            min-width: 100%;
        }
        
        .search-input-group {
            min-width: 100%;
        }

        .debug-toggle {
            position: static;
            margin: 10px auto;
            display: block;
            width: fit-content;
        }
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        color: #dee2e6;
    }

    .empty-state h3 {
        margin-bottom: 15px;
        color: #495057;
    }
</style>

<div class="portal-container">
    <!-- ポータルヘッダー -->
    <div class="portal-header">
        <div class="container">
            <h1 class="portal-title">
                <i class="fas fa-robot"></i> AI活用体験ポータル
            </h1>
            <p class="portal-subtitle">
                実際にAIを使った体験談と活用事例を発見・共有するプラットフォーム
            </p>
        </div>
        <!-- デバッグボタン -->
        <a href="?<?= http_build_query(array_merge($currentParams, ['debug' => $showDebug ? '0' : '1'])) ?>" 
           class="debug-toggle <?= $showDebug ? 'active' : '' ?>">
            🐛 デバッグ
        </a>
    </div>

    <div class="container pb-5">
        <!-- デバッグ情報表示 -->
        <?php if ($showDebug): ?>
            <?= $debugInfo ?>
        <?php endif; ?>

        <!-- メインフィルターとアクション -->
        <div class="main-filters">
            <!-- 記事の種類フィルター（メイン機能） -->
            <!-- 記事の種類フィルター（メイン機能） -->
            <div class="article-type-filter">
                <label for="main-article-type">
                    <i class="fas fa-filter"></i> 記事の種類
                </label>
                <select id="main-article-type" onchange="applyMainFilter()">
                    <option value="">すべての種類</option>
                    <?php 
                    $articleTypes = getArticleTypes();
                    $usedTypes = getUsedArticleTypes();
                    
                    foreach ($articleTypes as $value => $label): 
                        $count = isset($usedTypes[$value]) ? $usedTypes[$value] : 0;
                        $selected = $articleType === $value ? 'selected' : '';
                    ?>
                        <option value="<?= $value ?>" <?= $selected ?>>
                            <?= htmlspecialchars($label) ?> (<?= $count ?>件)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- アクションボタン -->
            <div class="action-buttons">
                <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#exampleModal">
                    <i class="fas fa-table"></i> 既存記事を登録
                </button>
                <a href="ai_experience_new.php" class="action-btn primary">
                    <i class="fas fa-plus"></i> 体験記事を書く
                </a>
            </div>
            
            <!-- 検索フォーム -->
            <form method="GET" class="search-form">
                <input type="hidden" name="article_type" value="<?= htmlspecialchars($articleType) ?>">
                <?php if ($showDebug): ?>
                <input type="hidden" name="debug" value="1">
                <?php endif; ?>
                
                <div class="search-input-group">
                    <label for="search">
                        <i class="fas fa-search"></i> キーワード検索
                    </label>
                    <input type="text" id="search" name="search"
                           value="<?= htmlspecialchars($searchTerm) ?>"
                           placeholder="タイトル・内容で検索">
                </div>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> 検索
                </button>
            </form>
        </div>

        <!-- 結果ヘッダー -->
        <div class="results-header">
            <div class="results-count">
                <strong><?= number_format($totalArticles) ?>件</strong>のAI活用体験記事
                <?php if ($verifiedOnly): ?>
                <span class="badge bg-success ms-2">確認済みのみ</span>
                <?php endif; ?>
                <?php if (!empty($articleType)): ?>
                <span class="badge bg-info ms-2">記事種類: <?= htmlspecialchars($articleType) ?></span>
                <?php endif; ?>
                <?php if ($totalPages > 1): ?>
                <span class="ms-2 text-muted">
                    (<?= $page ?>/<?= $totalPages ?>ページ)
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 記事グリッド -->
        <?php if (empty($articles)): ?>
            <div class="empty-state">
                <i class="fas fa-newspaper"></i>
                <h3>記事が見つかりませんでした</h3>
                <p>検索条件を変更してください。</p>
            </div>
        <?php else: ?>
            <div class="articles-grid">
                <?php foreach ($articles as $article): ?>
                    <div class="article-card" onclick="viewArticle('<?= htmlspecialchars($article['url']) ?>', <?= $article['id'] ?>)">
                        <div class="card-thumbnail-wrapper">
                            <?php if ($article['is_verified']): ?>
                                <div class="card-verified-badge"><i class="fas fa-check"></i></div>
                            <?php endif; ?>
                            <div class="card-thumbnail" style="background-image: url('<?= htmlspecialchars($article['thumbnail_url'] ?: '/path/to/default/image.png') ?>');">
                            </div>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">
                                <!-- 記事タイプバッジを追加 -->
                                <?php if (!empty($article['article_type'])): ?>
                                    <span class="article-type-badge <?= $article['article_type'] ?>">
                                        <?= htmlspecialchars(getArticleTypeLabel($article['article_type'])) ?>
                                    </span><br>
                                <?php endif; ?>
                                <?= $article['id'].".".htmlspecialchars($article['title']) ?>
                            </h3>
                            <p class="card-summary">
                                <span class="author-name"><b><?= htmlspecialchars($article['note_username']) ?></b></span>
                                <?= htmlspecialchars($article['preview_text']) ?>
                            </p>
                            <div class="card-footer">
                                <div class="author-info">
                                    <img src="<?= htmlspecialchars($article['avatar_url'] ?: '/path/to/default/avatar.png') ?>" alt="<?= htmlspecialchars($article['note_username']) ?>" class="author-avatar">
                                    <span class="author-name"><?= htmlspecialchars($article['note_username']) ?></span>
                                </div>
                                <div class="card-stats">
                                    <i class="fas fa-thumbs-up"></i>
                                    <span><?= number_format($article['helpful_count']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ページネーション -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination">
                        <!-- 前のページ -->
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($page - 1, $currentParams) ?>" aria-label="前のページ">
                                    <i class="fas fa-chevron-left"></i> 前
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">
                                    <i class="fas fa-chevron-left"></i> 前
                                </span>
                            </li>
                        <?php endif; ?>

                        <!-- ページ番号 -->
                        <?php
                        // ページネーションの表示範囲を計算
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        // 最初のページを表示
                        if ($start > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl(1, $currentParams) ?>">1</a>
                            </li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- 現在のページ周辺のページ番号 -->
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <?php if ($i == $page): ?>
                                    <span class="page-link"><?= $i ?></span>
                                <?php else: ?>
                                    <a class="page-link" href="<?= buildPaginationUrl($i, $currentParams) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>

                        <!-- 最後のページを表示 -->
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($totalPages, $currentParams) ?>"><?= $totalPages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- 次のページ -->
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($page + 1, $currentParams) ?>" aria-label="次のページ">
                                    次 <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">
                                    次 <i class="fas fa-chevron-right"></i>
                                </span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>

                <!-- ページネーション情報 -->
                <div class="pagination-info">
                    <?php
                    $startItem = ($page - 1) * $limit + 1;
                    $endItem = min($page * $limit, $totalArticles);
                    ?>
                    <?= number_format($startItem) ?>-<?= number_format($endItem) ?>件目 / 全<?= number_format($totalArticles) ?>件
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    function applyMainFilter() {
        const url = new URL(window.location);
        const articleType = document.getElementById('main-article-type').value;
        
        if (articleType) {
            url.searchParams.set('article_type', articleType);
        } else {
            url.searchParams.delete('article_type');
        }
        
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    function viewArticle(noteUrl, articleId) {
        fetch('api/count_view.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                article_id: articleId
            })
        }).catch(err => console.log('View count failed:', err));
        
        window.open(noteUrl, '_blank');
    }
</script>

<?php include 'includes/footer.php'; ?>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="container py-5">
                <div class="form-container shadow-sm">
                    <h1 class="text-center mb-4">既存記事を登録</h1>
                    <form action="note_article_info.php" method="POST">
                        <div class="mb-3">
                            <label for="article_url" class="form-label">Note記事URL</label>
                            <input type="url" class="form-control" id="article_url" name="url"
                                placeholder="https://note.com/ユーザid/n/記事id/"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="ai_service_id" class="form-label">AIサービスを選択</label>
                            <select class="form-select" id="ai_service_id" name="ai_service_id" required>
                                <?php
                                    $ai_services = getAIServices();
                                    foreach ($ai_services as $service): 
                                ?>
                                    <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['ai_service']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- 記事タイプ選択を追加 -->
                        <div class="mb-3">
                            <label for="modal_article_type" class="form-label">記事の種類</label>
                            <select class="form-select" id="modal_article_type" name="article_type" required>
                                <option value="">選択してください</option>
                                <?php 
                                $articleTypes = getArticleTypes();
                                foreach ($articleTypes as $value => $label): 
                                ?>
                                    <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">記事情報を取得して保存</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>