<?php
require_once 'includes/db_connect.php';

$pageTitle = 'AI サービス一覧';

// フィルタとソートのパラメータを取得
$filters = [
    'recommended' => isset($_GET['recommended']) ? 1 : 0,
    'free' => isset($_GET['free']) ? 1 : 0,
    'paid' => isset($_GET['paid']) ? 1 : 0,
    'ai_type_id' => isset($_GET['ai_type']) ? intval($_GET['ai_type']) : null,
    'search' => isset($_GET['search']) ? trim($_GET['search']) : ''
];

$orderBy = isset($_GET['order']) ? $_GET['order'] : 'popularity_score';
$orderDir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

// AIサービス一覧を取得
$aiServices = getAIServices($filters, $orderBy, $orderDir);

// AIタイプ一覧
$aiTypes = [
    1 => 'テキスト生成AI',
    2 => '画像生成AI',
    3 => '音声・音楽生成AI',
    4 => 'チャットボット',
    5 => 'コード生成AI'
];

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-list"></i> AI サービス一覧</h1>
        <div>
            <a href="AI_comparison.php" class="btn btn-outline-primary">
                <i class="fas fa-balance-scale"></i> 比較ページ
            </a>
            <a href="AI_ranking.php" class="btn btn-outline-success">
                <i class="fas fa-trophy"></i> ランキング
            </a>
        </div>
    </div>

    <!-- フィルター・検索セクション -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">検索</label>
                    <input type="text" class="form-control" name="search" 
                           value="<?= htmlspecialchars($filters['search']) ?>" 
                           placeholder="サービス名・説明で検索">
                </div>
                <div class="col-md-2">
                    <label class="form-label">AIタイプ</label>
                    <select class="form-select" name="ai_type">
                        <option value="">すべて</option>
                        <?php foreach ($aiTypes as $typeId => $typeName): ?>
                            <option value="<?= $typeId ?>" <?= $filters['ai_type_id'] == $typeId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($typeName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">料金</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="free" 
                               <?= $filters['free'] ? 'checked' : '' ?>>
                        <label class="form-check-label">無料あり</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="paid" 
                               <?= $filters['paid'] ? 'checked' : '' ?>>
                        <label class="form-check-label">有料のみ</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">その他</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="recommended" 
                               <?= $filters['recommended'] ? 'checked' : '' ?>>
                        <label class="form-check-label">おすすめ</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">並べ替え</label>
                    <select class="form-select" name="order">
                        <option value="popularity_score" <?= $orderBy === 'popularity_score' ? 'selected' : '' ?>>人気順</option>
                        <option value="ai_service" <?= $orderBy === 'ai_service' ? 'selected' : '' ?>>名前順</option>
                        <option value="release_date" <?= $orderBy === 'release_date' ? 'selected' : '' ?>>リリース日順</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">検索</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 結果表示 -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="mb-0">検索結果: <strong><?= count($aiServices) ?>件</strong></p>
        <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="view" id="card-view" checked>
            <label class="btn btn-outline-secondary" for="card-view">
                <i class="fas fa-th"></i> カード
            </label>
            <input type="radio" class="btn-check" name="view" id="list-view">
            <label class="btn btn-outline-secondary" for="list-view">
                <i class="fas fa-list"></i> リスト
            </label>
        </div>
    </div>

    <!-- AIサービスカード一覧 -->
    <div class="row" id="services-container">
        <?php foreach ($aiServices as $service): ?>
            <div class="col-lg-4 col-md-6 mb-4 service-card">
                <div class="card ai-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <img src="icons/<?= htmlspecialchars($service['ai_icon']) ?>" 
                                 alt="<?= htmlspecialchars($service['ai_service']) ?>" 
                                 class="ai-icon me-3"
                                 onerror="this.src='icons/default-icon.png'">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1"><?= htmlspecialchars($service['ai_service']) ?></h5>
                                <small class="text-muted"><?= htmlspecialchars($service['company_name']) ?></small>
                            </div>
                        </div>
                        
                        <p class="card-text text-muted small mb-3">
                            <?= htmlspecialchars(mb_substr($service['description'], 0, 100)) ?>
                            <?= mb_strlen($service['description']) > 100 ? '...' : '' ?>
                        </p>
                        
                        <!-- 評価 -->
                        <div class="d-flex align-items-center mb-3">
                            <div class="rating-stars me-2">
                                <?php 
                                $rating = $service['popularity_score'] / 20;
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <i class="fas fa-star<?= $i <= $rating ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted"><?= number_format($rating, 1) ?> (<?= $service['popularity_score'] ?>点)</small>
                        </div>
                        
                        <!-- バッジ -->
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
                        
                        <!-- アクションボタン -->
                        <div class="d-grid gap-2">
                            <a href="AI_detail.php?id=<?= $service['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-info-circle"></i> 詳細を見る
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
    
    <?php if (empty($aiServices)): ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h4>該当するAIサービスが見つかりませんでした</h4>
            <p class="text-muted">検索条件を変更してお試しください。</p>
        </div>
    <?php endif; ?>
</div>

<script>
// ビュー切り替え
document.getElementById('list-view').addEventListener('change', function() {
    if (this.checked) {
        document.querySelectorAll('.service-card').forEach(card => {
            card.className = 'col-12 mb-3 service-card';
        });
    }
});

document.getElementById('card-view').addEventListener('change', function() {
    if (this.checked) {
        document.querySelectorAll('.service-card').forEach(card => {
            card.className = 'col-lg-4 col-md-6 mb-4 service-card';
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
