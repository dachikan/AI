<?php
require_once 'db_connect_extended.php';
$pageTitle = 'AI情報システム - ホーム';
include 'includes/header.php';

$aiTypes = getAITypesFromInfo();
$topServices = getTopAIServices(6);
$totalServices = getAIServiceCount();
?>

<div class="row">
    <div class="col-12">
        <div class="jumbotron bg-primary text-white p-5 rounded mb-4">
            <h1 class="display-4">AI情報システム</h1>
            <p class="lead">様々なAIサービスの情報を比較・検索できるプラットフォーム</p>
            <p>現在 <strong><?php echo $totalServices; ?></strong> のAIサービスを掲載中</p>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-chart-bar me-2"></i>AIタイプ別サービス数</h2>
        <div class="row">
            <?php foreach ($aiTypes as $type): ?>
            <div class="col-md-4 col-lg-3 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?php echo htmlspecialchars($type['type_name']); ?></h5>
                        <p class="card-text">
                            <span class="badge bg-primary fs-6"><?php echo $type['service_count']; ?> サービス</span>
                        </p>
                        <a href="AI_list.php?type=<?php echo $type['ai_type_id']; ?>" class="btn btn-outline-primary btn-sm">
                            詳細を見る
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-star me-2"></i>人気のAIサービス</h2>
        <div class="row">
            <?php foreach ($topServices as $service): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card ai-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <img src="images/<?php echo htmlspecialchars($service['ai_icon']); ?>" 
                                 alt="<?php echo htmlspecialchars($service['ai_service']); ?>" 
                                 class="ai-icon me-3"
                                 onerror="this.src='images/default-ai-icon.png'">
                            <div>
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($service['ai_service']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($service['company_name']); ?></small>
                            </div>
                        </div>
                        <p class="card-text"><?php echo mb_substr(htmlspecialchars($service['description']), 0, 100); ?>...</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="popularity-score"><?php echo $service['popularity_score']; ?>点</span>
                            <a href="AI_detail.php?id=<?php echo $service['id']; ?>" class="btn btn-primary btn-sm">
                                詳細を見る
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
