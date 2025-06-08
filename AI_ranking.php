<?php
require_once 'includes/db_connect.php';

$pageTitle = 'AI 人気ランキング';

// ランキングデータを取得
$popularityRanking = getPopularityRanking(20);

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="text-center mb-5">
        <h1><i class="fas fa-trophy text-warning"></i> AI 人気ランキング</h1>
        <p class="text-muted">人気度スコア順にAIサービスをランキング表示</p>
    </div>

    <!-- ランキング一覧 -->
    <div class="row">
        <?php foreach ($popularityRanking as $index => $service): ?>
            <?php 
            $rank = $index + 1;
            $rankClass = '';
            $rankIcon = '';
            if ($rank == 1) {
                $rankClass = 'rank-1';
                $rankIcon = '<i class="fas fa-crown text-warning"></i>';
            } elseif ($rank == 2) {
                $rankClass = 'rank-2';
                $rankIcon = '<i class="fas fa-medal text-secondary"></i>';
            } elseif ($rank == 3) {
                $rankClass = 'rank-3';
                $rankIcon = '<i class="fas fa-medal" style="color: #cd7f32;"></i>';
            }
            ?>
            <div class="col-12 mb-3">
                <div class="card ranking-item <?= $rankClass ?>">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <!-- ランク表示 -->
                            <div class="col-md-1 text-center">
                                <div class="rank-display">
                                    <?php if ($rank <= 3): ?>
                                        <div class="fs-1"><?= $rankIcon ?></div>
                                        <div class="fw-bold"><?= $rank ?>位</div>
                                    <?php else: ?>
                                        <div class="fs-1 fw-bold text-primary"><?= $rank ?></div>
                                        <div class="small text-muted">位</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- AIサービス情報 -->
                            <div class="col-md-7">
                                <div class="d-flex align-items-center">
                                    <img src="icons/<?= htmlspecialchars($service['ai_icon']) ?>" 
                                         alt="<?= htmlspecialchars($service['ai_service']) ?>" 
                                         class="ai-icon me-3"
                                         onerror="this.src='icons/default-icon.png'">
                                    <div>
                                        <h4 class="mb-1"><?= htmlspecialchars($service['ai_service']) ?></h4>
                                        <p class="text-muted mb-1"><?= htmlspecialchars($service['company_name']) ?></p>
                                        <p class="mb-0 small"><?= htmlspecialchars(mb_substr($service['description'], 0, 100)) ?>...</p>
                                        
                                        <!-- バッジ -->
                                        <div class="mt-2">
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
                                    </div>
                                </div>
                            </div>

                            <!-- スコア表示 -->
                            <div class="col-md-2 text-center">
                                <div class="mb-2">
                                    <h3 class="mb-0 text-primary"><?= $service['popularity_score'] ?>点</h3>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-gradient" 
                                             style="width: <?= $service['popularity_score'] ?>%"></div>
                                    </div>
                                </div>
                                <div class="rating-stars">
                                    <?php 
                                    $rating = $service['popularity_score'] / 20;
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star<?= $i <= $rating ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="text-muted"><?= number_format($rating, 1) ?></small>
                            </div>

                            <!-- アクションボタン -->
                            <div class="col-md-2 text-end">
                                <div class="d-grid gap-2">
                                    <a href="AI_detail.php?id=<?= $service['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-info-circle"></i> 詳細
                                    </a>
                                    <button class="btn btn-outline-secondary btn-sm compare-btn" 
                                            data-service-id="<?= $service['id'] ?>"
                                            onclick="addToComparison(<?= $service['id'] ?>)">
                                        <i class="fas fa-plus"></i> 比較追加
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 統計情報 -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> ランキング統計</h3>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h4><?= count($popularityRanking) ?></h4>
                            <p class="text-muted">総サービス数</p>
                        </div>
                        <div class="col-md-3">
                            <h4><?= number_format(array_sum(array_column($popularityRanking, 'popularity_score')) / count($popularityRanking), 1) ?>点</h4>
                            <p class="text-muted">平均スコア</p>
                        </div>
                        <div class="col-md-3">
                            <h4><?= count(array_filter($popularityRanking, function($s) { return $s['free_tier_available']; })) ?></h4>
                            <p class="text-muted">無料プランあり</p>
                        </div>
                        <div class="col-md-3">
                            <h4><?= count(array_filter($popularityRanking, function($s) { return $s['api_available']; })) ?></h4>
                            <p class="text-muted">API対応</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ナビゲーション -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="AI_list.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list"></i> 一覧表示
                </a>
                <a href="AI_comparison.php" class="btn btn-primary">
                    <i class="fas fa-balance-scale"></i> 比較ページ
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
