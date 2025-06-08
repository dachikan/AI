<?php
require_once 'includes/db_connect.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$pageTitle = $query ? "「{$query}」の検索結果" : 'AI サービス検索';

$searchResults = [];
if ($query) {
    $searchResults = getAIServices(['search' => $query], 'popularity_score', 'DESC');
}

include 'includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4"><i class="fas fa-search"></i> AI サービス検索</h1>
    
    <!-- 検索フォーム -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" class="form-control form-control-lg" name="q" 
                           value="<?= htmlspecialchars($query) ?>" 
                           placeholder="AIサービス名、会社名、説明で検索...">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-search"></i> 検索
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($query): ?>
        <!-- 検索結果 -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>「<?= htmlspecialchars($query) ?>」の検索結果</h3>
            <p class="mb-0 text-muted"><?= count($searchResults) ?>件見つかりました</p>
        </div>

        <?php if (!empty($searchResults)): ?>
            <div class="row">
                <?php foreach ($searchResults as $service): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card ai-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <img src="icons/<?= htmlspecialchars($service['ai_icon']) ?>" 
                                         alt="<?= htmlspecialchars($service['ai_service']) ?>" 
                                         class="ai-icon me-3"
                                         onerror="this.src='icons/default-icon.png'">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">
                                            <?= highlightSearchTerm(htmlspecialchars($service['ai_service']), $query) ?>
                                        </h5>
                                        <small class="text-muted">
                                            <?= highlightSearchTerm(htmlspecialchars($service['company_name']), $query) ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <p class="card-text small text-muted mb-3">
                                    <?= highlightSearchTerm(htmlspecialchars(mb_substr($service['description'], 0, 100)), $query) ?>...
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="rating-stars">
                                        <?php 
                                        $rating = $service['popularity_score'] / 20;
                                        for ($i = 1; $i <= 5; $i++): 
                                        ?>
                                            <i class="fas fa-star<?= $i <= $rating ? '' : '-o' ?>"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted ms-1"><?= number_format($rating, 1) ?></small>
                                    </div>
                                    <small class="text-muted"><?= $service['popularity_score'] ?>点</small>
                                </div>
                                
                                <div class="mb-3">
                                    <?php if ($service['free_tier_available']): ?>
                                        <span class="badge badge-free me-1">無料</span>
                                    <?php endif; ?>
                                    <?php if ($service['popularity_score'] >= 80): ?>
                                        <span class="badge badge-recommended me-1">おすすめ</span>
                                    <?php endif; ?>
                                    <?php if ($service['api_available']): ?>
                                        <span class="badge bg-info me-1">API</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="AI_detail.php?id=<?= $service['id'] ?>" class="btn btn-primary">
                                        詳細を見る
                                    </a>
                                    <button class="btn btn-outline-secondary btn-sm compare-btn" 
                                            data-service-id="<?= $service['id'] ?>"
                                            onclick="addToComparison(<?= $service['id'] ?>)">
                                        <i class="fas fa-plus"></i> 比較に追加
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>「<?= htmlspecialchars($query) ?>」に該当するAIサービスが見つかりませんでした</h4>
                <p class="text-muted">別のキーワードで検索してみてください。</p>
                
                <!-- 検索候補 -->
                <div class="mt-4">
                    <h5>検索候補:</h5>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="AI_search.php?q=ChatGPT" class="btn btn-outline-primary btn-sm">ChatGPT</a>
                        <a href="AI_search.php?q=画像生成" class="btn btn-outline-primary btn-sm">画像生成</a>
                        <a href="AI_search.php?q=無料" class="btn btn-outline-primary btn-sm">無料</a>
                        <a href="AI_search.php?q=API" class="btn btn-outline-primary btn-sm">API</a>
                        <a href="AI_search.php?q=OpenAI" class="btn btn-outline-primary btn-sm">OpenAI</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- 検索前の状態 -->
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h4>AIサービスを検索</h4>
            <p class="text-muted">サービス名、会社名、説明文から検索できます</p>
            
            <!-- 人気検索キーワード -->
            <div class="mt-4">
                <h5>人気検索キーワード:</h5>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="AI_search.php?q=ChatGPT" class="btn btn-outline-primary">ChatGPT</a>
                    <a href="AI_search.php?q=画像生成" class="btn btn-outline-primary">画像生成</a>
                    <a href="AI_search.php?q=無料" class="btn btn-outline-primary">無料</a>
                    <a href="AI_search.php?q=API" class="btn btn-outline-primary">API</a>
                    <a href="AI_search.php?q=OpenAI" class="btn btn-outline-primary">OpenAI</a>
                    <a href="AI_search.php?q=Google" class="btn btn-outline-primary">Google</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// 検索語をハイライトする関数
function highlightSearchTerm($text, $term) {
    if (empty($term)) return $text;
    return preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark>$1</mark>', $text);
}

include 'includes/footer.php';
?>
