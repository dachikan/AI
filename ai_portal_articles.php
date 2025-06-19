<?php
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
    a.id, a.url, a.title, a.summary, a.article_type, a.status, a.is_verified,
    a.published_at, a.view_count, a.helpful_count, a.created_at,
    a.thumbnail_url, -- ★追加
    ai.ai_service, ai.id as ai_service_id,
    c.name as category_name,
    au.note_username,
    au.avatar_url, -- ★追加
    COALESCE(SUBSTRING(a.summary, 1, 120), SUBSTRING(a.title, 1, 120), 'AI活用体験記事') as preview_text
FROM ai_articles a
JOIN AIInfo ai ON a.ai_service_id = ai.id
JOIN ai_users au ON a.user_id = au.id -- ★LEFT JOIN から JOIN に変更
LEFT JOIN AIPromptCategories c ON a.category_id = c.id
WHERE a.status = 'verified'";

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
        margin-right: 6px;
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
                
                <!-- <div class="col-md-2">
                    <label class="form-label">
                        <i class="fas fa-filter"></i> 品質
                    </label>
                    <select name="verified" class="form-select">
                        <option value="">すべて</option>
                        <option value="1" <?= $verifiedOnly ? 'selected' : '' ?>>確認済みのみ</option>
                    </select>
                </div> -->
                
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
                                <?= htmlspecialchars($article['title']) ?>
                            </h3>
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
            <?php if ($totalPages > 1): ?>
                <?php endif; ?>
        <?php endif; ?>

        <!-- ページネーション -->
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination
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
