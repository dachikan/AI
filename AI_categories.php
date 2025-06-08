<?php
require_once 'includes/db_connect.php';

$pageTitle = 'AI カテゴリ別一覧';

// AIタイプ別の表示
$aiTypes = [
    1 => ['name' => 'テキスト生成AI', 'icon' => 'fas fa-pen', 'description' => '文章作成、翻訳、要約などのテキスト処理'],
    2 => ['name' => '画像生成AI', 'icon' => 'fas fa-image', 'description' => 'イラスト、写真、アート作品の生成'],
    3 => ['name' => '音声・音楽生成AI', 'icon' => 'fas fa-music', 'description' => '音楽作成、音声合成、効果音生成'],
    4 => ['name' => 'チャットボット', 'icon' => 'fas fa-comments', 'description' => '対話型AI、カスタマーサポート'],
    5 => ['name' => 'コード生成AI', 'icon' => 'fas fa-code', 'description' => 'プログラミング支援、コード自動生成']
];

$selectedType = isset($_GET['type']) ? intval($_GET['type']) : null;

include 'includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4"><i class="fas fa-th-large"></i> AI カテゴリ別一覧</h1>
    
    <?php if ($selectedType && isset($aiTypes[$selectedType])): ?>
        <!-- 特定カテゴリの詳細表示 -->
        <?php 
        $services = getAIServices(['ai_type_id' => $selectedType], 'popularity_score', 'DESC');
        $categoryInfo = $aiTypes[$selectedType];
        ?>
        
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="<?= $categoryInfo['icon'] ?> fa-3x text-primary mb-3"></i>
                <h2><?= htmlspecialchars($categoryInfo['name']) ?></h2>
                <p class="text-muted"><?= htmlspecialchars($categoryInfo['description']) ?></p>
                <p><strong><?= count($services) ?>個のサービス</strong>が見つかりました</p>
            </div>
        </div>

        <!-- サービス一覧 -->
        <div class="row">
            <?php foreach ($services as $service): ?>
                <div class="col-lg-4 col-md-6 mb-4">
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
                            
                            <p class="card-text small text-muted mb-3">
                                <?= htmlspecialchars(mb_substr($service['description'], 0, 100)) ?>...
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
        
        <?php if (empty($services)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>該当するAIサービスが見つかりませんでした</h4>
            </div>
        <?php endif; ?>

        <!-- ナビゲーション -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between">
                    <a href="AI_categories.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> カテゴリ一覧に戻る
                    </a>
                    <div>
                        <a href="AI_comparison.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-balance-scale"></i> 比較ページ
                        </a>
                        <a href="AI_ranking.php" class="btn btn-outline-success">
                            <i class="fas fa-trophy"></i> ランキング
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- カテゴリ一覧表示 -->
        <div class="row">
            <?php foreach ($aiTypes as $typeId => $type): ?>
                <?php $serviceCount = count(getAIServices(['ai_type_id' => $typeId])); ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100" style="cursor: pointer;" onclick="location.href='AI_categories.php?type=<?= $typeId ?>'">
                        <div class="card-body text-center">
                            <i class="<?= $type['icon'] ?> fa-4x text-primary mb-3"></i>
                            <h4><?= htmlspecialchars($type['name']) ?></h4>
                            <p class="text-muted"><?= htmlspecialchars($type['description']) ?></p>
                            <h3 class="text-primary"><?= $serviceCount ?></h3>
                            <p class="text-muted">サービス</p>
                            <div class="mt-3">
                                <span class="btn btn-primary">詳細を見る</span>
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
                        <h3><i class="fas fa-chart-pie"></i> カテゴリ別統計</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($aiTypes as $typeId => $type): ?>
                                <?php 
                                $services = getAIServices(['ai_type_id' => $typeId]);
                                $freeCount = count(getAIServices(['ai_type_id' => $typeId, 'free' => 1]));
                                $avgScore = !empty($services) ? array_sum(array_column($services, 'popularity_score')) / count($services) : 0;
                                ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6><i class="<?= $type['icon'] ?> me-2"></i><?= htmlspecialchars($type['name']) ?></h6>
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <strong><?= count($services) ?></strong>
                                                    <br><small>総数</small>
                                                </div>
                                                <div class="col-4">
                                                    <strong><?= $freeCount ?></strong>
                                                    <br><small>無料</small>
                                                </div>
                                                <div class="col-4">
                                                    <strong><?= number_format($avgScore, 1) ?></strong>
                                                    <br><small>平均点</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
