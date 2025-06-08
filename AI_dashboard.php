<?php
require_once 'includes/db_connect.php';
require_once 'includes/icon-helpers.php';

$pageTitle = 'AI サービス ダッシュボード';

// 統計データを取得
$totalServices = count(getAIServices());
$popularServices = getPopularityRanking(5);
$freeServices = count(getAIServices(['free' => 1]));
$recommendedServices = count(getAIServices(['recommended' => 1]));

// AIタイプ別統計
$aiTypeStats = [
    1 => ['name' => 'テキスト生成AI', 'icon' => 'fas fa-pen', 'count' => count(getAIServices(['ai_type_id' => 1]))],
    2 => ['name' => '画像生成AI', 'icon' => 'fas fa-image', 'count' => count(getAIServices(['ai_type_id' => 2]))],
    3 => ['name' => '音声・音楽生成AI', 'icon' => 'fas fa-music', 'count' => count(getAIServices(['ai_type_id' => 3]))],
    4 => ['name' => 'チャットボット', 'icon' => 'fas fa-comments', 'count' => count(getAIServices(['ai_type_id' => 4]))],
    5 => ['name' => 'コード生成AI', 'icon' => 'fas fa-code', 'count' => count(getAIServices(['ai_type_id' => 5]))]
];

include 'includes/header.php';
?>

<!-- ヒーローセクション -->
<div class="hero-section text-center">
    <div class="container">
        <h1 class="display-4 mb-3"><i class="fas fa-robot"></i> AI サービス総合ポータル</h1>
        <p class="lead mb-4"><?= $totalServices ?>種類のAIサービスを比較・検索・ランキングで探そう</p>
        
        <!-- 検索フォーム -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <form action="AI_search.php" method="GET" class="d-flex gap-2">
                    <input type="text" class="form-control form-control-lg" name="q" 
                           placeholder="ChatGPT、DALL-E、Midjourney...">
                    <button type="submit" class="btn btn-light btn-lg">
                        <i class="fas fa-search"></i> 検索
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- 統計カード -->
    <div class="row mb-5">
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-robot fa-3x text-primary mb-3"></i>
                    <h3><?= $totalServices ?></h3>
                    <p class="text-muted">総AIサービス数</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-gift fa-3x text-success mb-3"></i>
                    <h3><?= $freeServices ?></h3>
                    <p class="text-muted">無料プランあり</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-star fa-3x text-warning mb-3"></i>
                    <h3><?= $recommendedServices ?></h3>
                    <p class="text-muted">おすすめサービス</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-code fa-3x text-info mb-3"></i>
                    <h3><?= count(array_filter(getAIServices(), function($s) { return $s['api_available']; })) ?></h3>
                    <p class="text-muted">API対応</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- 左カラム：カテゴリ別一覧 -->
        <div class="col-md-6">
            <h3 class="mb-4"><i class="fas fa-th-large"></i> カテゴリ別一覧</h3>
            <div class="row">
                <?php foreach ($aiTypeStats as $typeId => $type): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100" style="cursor: pointer;" 
                             onclick="location.href='AI_list.php?ai_type=<?= $typeId ?>'">
                            <div class="card-body text-center">
                                <i class="<?= $type['icon'] ?> fa-2x text-primary mb-2"></i>
                                <h6><?= htmlspecialchars($type['name']) ?></h6>
                                <h4 class="text-primary"><?= $type['count'] ?></h4>
                                <small class="text-muted">サービス</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- クイックアクション -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-bolt"></i> クイックアクション</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="AI_list.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> 全サービス一覧
                        </a>
                        <a href="AI_ranking.php" class="btn btn-outline-success">
                            <i class="fas fa-trophy"></i> 人気ランキング
                        </a>
                        <a href="AI_comparison.php" class="btn btn-outline-info">
                            <i class="fas fa-balance-scale"></i> サービス比較
                        </a>
                        <a href="AI_list.php?free=1" class="btn btn-outline-warning">
                            <i class="fas fa-gift"></i> 無料サービスのみ
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右カラム：人気サービス -->
        <div class="col-md-6">
            <h3 class="mb-4"><i class="fas fa-fire"></i> 人気TOP5</h3>
            <?php foreach ($popularServices as $index => $service): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <span class="badge bg-primary fs-6"><?= $index + 1 ?></span>
                            </div>
                            <!-- 改善されたアイコン表示 -->
                            <div class="position-relative me-3">
                                <?= renderAIIcon(
                                    getIconWithFallback($service['ai_icon'], $service['ai_service']), 
                                    $service['ai_service'], 
                                    'ai-icon-ranking'
                                ) ?>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?= htmlspecialchars($service['ai_service']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($service['company_name']) ?></small>
                                <div class="rating-stars">
                                    <?php 
                                    $rating = $service['popularity_score'] / 20;
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star<?= $i <= $rating ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                    <small class="text-muted ms-1"><?= number_format($rating, 1) ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="mb-2">
                                    <?php if ($service['free_tier_available']): ?>
                                        <span class="badge badge-free">無料</span>
                                    <?php endif; ?>
                                    <?php if ($service['popularity_score'] >= 80): ?>
                                        <span class="badge badge-recommended">おすすめ</span>
                                    <?php endif; ?>
                                </div>
                                <a href="AI_detail.php?id=<?= $service['id'] ?>" class="btn btn-primary btn-sm">
                                    詳細を見る
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="text-center">
                <a href="AI_ranking.php" class="btn btn-outline-primary">
                    <i class="fas fa-trophy"></i> 完全ランキングを見る
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
