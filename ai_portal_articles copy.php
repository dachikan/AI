<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db_connect.php';

$pageTitle = 'AI活用体験記事一覧 - AI活用体験ポータル';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ページネーション設定
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// フィルター・ソート設定
$aiServiceId = isset($_GET['ai_service']) ? intval($_GET['ai_service']) : 0;
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;
$verifiedOnly = isset($_GET['verified']) ? $_GET['verified'] === '1' : false;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// 記事一覧を取得
$sql = "SELECT 
    a.id,
    a.url,
    a.title,
    a.summary,
    a.article_type,
    a.status,
    a.is_verified,
    a.published_at,
    a.view_count,
    a.helpful_count,
    a.created_at,
    ai.ai_service,
    ai.id as ai_service_id,
    c.name as category_name,
    au.note_username,
    /* プレビューテキストを生成 */
    COALESCE(
        SUBSTRING(a.summary, 1, 120),
        SUBSTRING(a.title, 1, 120),
        'AI活用体験記事'
    ) as preview_text
FROM ai_articles a
JOIN AIInfo ai ON a.ai_service_id = ai.id
LEFT JOIN ai_users au ON a.user_id = au.id
LEFT JOIN AIPromptCategories c ON a.category_id = c.id
WHERE a.status = 'verified'"; // 認証済みの記事のみ表示

$params = [];
$types = '';

// 確認済みフィルター
if ($verifiedOnly) {
    $sql .= " AND a.is_verified = 1";
}

// AIサービスフィルター
if ($aiServiceId > 0) {
    $sql .= " AND a.ai_service_id = ?";
    $params[] = $aiServiceId;
    $types .= 'i';
}

// カテゴリフィルター
if ($categoryId > 0) {
    $sql .= " AND a.category_id = ?";
    $params[] = $categoryId;
    $types .= 'i';
}

// 検索フィルター
if (!empty($searchTerm)) {
    $sql .= " AND (a.title LIKE ? OR a.summary LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= 'ss';
}

// ソート
switch ($sortBy) {
    case 'views':
        $sql .= " ORDER BY a.view_count DESC, a.created_at DESC";
        break;
    case 'helpful':
        $sql .= " ORDER BY a.helpful_count DESC, a.created_at DESC";
        break;
    case 'verified':
        $sql .= " ORDER BY a.is_verified DESC, a.created_at DESC";
        break;
    case 'published':
        $sql .= " ORDER BY a.published_at DESC, a.created_at DESC";
        break;
    default: // 'created_at'
        $sql .= " ORDER BY a.created_at DESC";
}

$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 総記事数を取得
$countSql = "SELECT COUNT(*) as total FROM ai_articles a 
             JOIN AIInfo ai ON a.ai_service_id = ai.id 
             WHERE a.status = 'verified'";
$countParams = [];
$countTypes = '';

if ($verifiedOnly) {
    $countSql .= " AND a.is_verified = 1";
}

if ($aiServiceId > 0) {
    $countSql .= " AND a.ai_service_id = ?";
    $countParams[] = $aiServiceId;
    $countTypes .= 'i';
}

if ($categoryId > 0) {
    $countSql .= " AND a.category_id = ?";
    $countParams[] = $categoryId;
    $countTypes .= 'i';
}

if (!empty($searchTerm)) {
    $countSql .= " AND (a.title LIKE ? OR a.summary LIKE ?)";
    $countParams[] = $searchPattern;
    $countParams[] = $searchPattern;
    $countTypes .= 'ss';
}

if (!empty($countParams)) {
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($countTypes, ...$countParams);
    $countStmt->execute();
    $totalArticles = $countStmt->get_result()->fetch_assoc()['total'];
} else {
    $totalArticles = $conn->query($countSql)->fetch_assoc()['total'];
}

$totalPages = ceil($totalArticles / $limit);

// AIサービス一覧（フィルター用）
$aiServices = getAIServices();

// カテゴリ一覧
$categories = getPromptCategories();

include 'includes/header.php';
?>

<style>
    .portal-container {
        background-color: #fafafa;
        min-height: 100vh;
    }

    .portal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 0;
        margin-bottom: 30px;
    }

    .portal-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .portal-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    .filter-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 25px;
    }

    .sort-tabs {
        display: flex;
        gap: 0;
        background: #f8f9fa;
        border-radius: 8px;
        padding: 4px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .sort-tab {
        flex: 1;
        min-width: 100px;
        padding: 10px 16px;
        background: transparent;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        color: #666;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
        font-size: 0.9rem;
    }

    .sort-tab.active {
        background: white;
        color: #333;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .sort-tab:hover:not(.active) {
        background: rgba(255,255,255,0.5);
    }

    .articles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .article-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        cursor: pointer;
        height: fit-content;
        position: relative;
    }

    .article-card.verified {
        border-left: 4px solid #28a745;
    }

    .verified-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #28a745;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        z-index: 2;
    }

    .article-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .article-thumbnail {
        width: 100%;
        height: 140px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        position: relative;
        overflow: hidden;
    }

    .thumbnail-content {
        text-align: center;
        z-index: 1;
    }

    .ai-service-icon {
        font-size: 2rem;
        margin-bottom: 8px;
    }

    .article-content {
        padding: 20px;
    }

    .article-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .ai-service-badge {
        background: #e3f2fd;
        color: #1976d2;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .category-badge {
        background: #f3e5f5;
        color: #7b1fa2;
        padding: 4px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 500;
        margin-left: 8px;
    }

    .article-date {
        color: #999;
        font-size: 0.8rem;
    }

    .article-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 10px;
        line-height: 1.4;
        color: #333;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .article-preview {
        color: #666;
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 15px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .article-stats {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }

    .stats-left {
        display: flex;
        gap: 15px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 4px;
        color: #666;
        font-size: 0.85rem;
    }

    .note-link {
        background: #00d4aa;
        color: white;
        padding: 4px 8px;
        border-radius: 8px;
        font-size: 0.75rem;
        text-decoration: none;
        font-weight: 500;
    }

    .note-link:hover {
        background: #00b894;
        color: white;
    }

    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .results-count {
        color: #666;
        font-size: 0.95rem;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #666;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 20px;
        color: #ddd;
    }

    @media (max-width: 768px) {
        .portal-title {
            font-size: 2rem;
        }
        
        .articles-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .sort-tabs {
            flex-direction: column;
            gap: 4px;
        }
        
        .sort-tab {
            flex: none;
        }
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
    </div>

    <div class="container pb-5">
        <!-- フィルター・検索セクション -->
        <div class="filter-section">
            <!-- ソートタブ -->
            <div class="sort-tabs">
                <button class="sort-tab <?= $sortBy === 'created_at' ? 'active' : '' ?>" 
                        onclick="changeSort('created_at')">
                    <i class="fas fa-clock"></i> 新着順
                </button>
                <button class="sort-tab <?= $sortBy === 'verified' ? 'active' : '' ?>" 
                        onclick="changeSort('verified')">
                    <i class="fas fa-check-circle"></i> 確認済み
                </button>
                <button class="sort-tab <?= $sortBy === 'views' ? 'active' : '' ?>" 
                        onclick="changeSort('views')">
                    <i class="fas fa-eye"></i> 閲覧順
                </button>
                <button class="sort-tab <?= $sortBy === 'helpful' ? 'active' : '' ?>" 
                        onclick="changeSort('helpful')">
                    <i class="fas fa-thumbs-up"></i> 参考順
                </button>
                <button class="sort-tab <?= $sortBy === 'published' ? 'active' : '' ?>" 
                        onclick="changeSort('published')">
                    <i class="fas fa-calendar"></i> 公開順
                </button>
            </div>

            <!-- フィルター -->
            <form method="GET" class="row g-3">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fas fa-search"></i> キーワード検索
                    </label>
                    <input type="text" class="form-control" name="search" 
                           value="<?= htmlspecialchars($searchTerm) ?>" 
                           placeholder="タイトル・内容で検索">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fas fa-robot"></i> AIサービス
                    </label>
                    <select name="ai_service" class="form-select">
                        <option value="0">すべてのAIサービス</option>
                        <?php foreach ($aiServices as $service): ?>
                        <option value="<?= $service['id'] ?>" <?= $aiServiceId == $service['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($service['ai_service']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">
                        <i class="fas fa-tags"></i> カテゴリ
                    </label>
                    <select name="category" class="form-select">
                        <option value="0">すべて</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $categoryId == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">
                        <i class="fas fa-filter"></i> 品質
                    </label>
                    <select name="verified" class="form-select">
                        <option value="">すべて</option>
                        <option value="1" <?= $verifiedOnly ? 'selected' : '' ?>>確認済みのみ</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> 検索
                    </button>
                    <a href="ai_portal_articles.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- 結果ヘッダー -->
        <div class="results-header">
            <div class="results-count">
                <strong><?= number_format($totalArticles) ?>件</strong>のAI活用体験記事
                <?php if ($verifiedOnly): ?>
                <span class="badge bg-success ms-2">確認済みのみ</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 記事グリッド -->
        <?php if (empty($articles)): ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <h3>記事が見つかりませんでした</h3>
            <p>検索条件を変更するか、新しい体験記事を投稿してみてください。</p>
            <a href="ai_experience_new.php" class="btn btn-primary mt-3">
                <i class="fas fa-plus"></i> 体験記事を投稿する
            </a>
        </div>
        <?php else: ?>
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
            <div class="article-card <?= $article['is_verified'] ? 'verified' : '' ?>" 
                 onclick="viewArticle('<?= htmlspecialchars($article['url']) ?>', <?= $article['id'] ?>)">
                
                <?php if ($article['is_verified']): ?>
                <div class="verified-badge">
                    <i class="fas fa-check-circle"></i> 確認済み
                </div>
                <?php endif; ?>
                
                <div class="article-thumbnail">
                    <div class="thumbnail-content">
                        <div class="ai-service-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div><?= htmlspecialchars($article['ai_service']) ?></div>
                    </div>
                </div>
                
                <div class="article-content">
                    <div class="article-meta">
                        <div>
                            <span class="ai-service-badge">
                                <?= htmlspecialchars($article['ai_service']) ?>
                            </span>
                            <?php if ($article['category_name']): ?>
                            <span class="category-badge">
                                <?= htmlspecialchars($article['category_name']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <span class="article-date">
                            <?= date('n月j日', strtotime($article['created_at'])) ?>
                        </span>
                    </div>
                    
                    <h3 class="article-title">
                        <?= htmlspecialchars($article['title'] ?: 'AI活用体験記事') ?>
                    </h3>
                    
                    <div class="article-preview">
                        <?= htmlspecialchars($article['preview_text']) ?>
                        <?php if (strlen($article['preview_text']) >= 120): ?>...<?php endif; ?>
                    </div>
                    
                    <div class="article-stats">
                        <div class="stats-left">
                            <div class="stat-item">
                                <i class="fas fa-eye"></i>
                                <span><?= number_format($article['view_count']) ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-thumbs-up"></i>
                                <span><?= number_format($article['helpful_count']) ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-calendar"></i>
                                <span><?= $article['article_type'] === 'new_post' ? '新規' : '既存' ?></span>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($article['url']) ?>" 
                           class="note-link" 
                           target="_blank" 
                           onclick="event.stopPropagation()">
                            <i class="fas fa-external-link-alt"></i> note
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ページネーション -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="記事ページネーション" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&ai_service=<?= $aiServiceId ?>&category=<?= $categoryId ?>&sort=<?= $sortBy ?>&search=<?= urlencode($searchTerm) ?>&verified=<?= $verifiedOnly ? '1' : '' ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&ai_service=<?= $aiServiceId ?>&category=<?= $categoryId ?>&sort=<?= $sortBy ?>&search=<?= urlencode($searchTerm) ?>&verified=<?= $verifiedOnly ? '1' : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endforeach; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&ai_service=<?= $aiServiceId ?>&category=<?= $categoryId ?>&sort=<?= $sortBy ?>&search=<?= urlencode($searchTerm) ?>&verified=<?= $verifiedOnly ? '1' : '' ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    function changeSort(sortType) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortType);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    function viewArticle(noteUrl, articleId) {
        // 閲覧数をカウント（Ajax）
        fetch('api/count_view.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                article_id: articleId
            })
        }).catch(err => console.log('View count failed:', err));
        
        // note記事を新しいタブで開く
        window.open(noteUrl, '_blank');
    }
</script>

<?php include 'includes/footer.php'; ?>
