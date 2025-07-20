<?php
/**
 * アイコンファイルの存在確認ユーティリティ
 */
require_once 'includes/db_connect.php';

// 全AIサービスのアイコンをチェック
$services = getAIServices();
$missingIcons = [];
$existingIcons = [];

foreach ($services as $service) {
    $iconPath = 'icons/' . $service['ai_icon'];
    
    if (file_exists($iconPath) && filesize($iconPath) > 0) {
        $existingIcons[] = [
            'service' => $service['ai_service'],
            'icon' => $service['ai_icon'],
            'size' => filesize($iconPath)
        ];
    } else {
        $missingIcons[] = [
            'service' => $service['ai_service'],
            'icon' => $service['ai_icon']
        ];
    }
}

// HTMLヘッダー
include 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIアイコン存在確認</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <!-- 左ペイン：不足アイコン一覧 -->
            <div class="col-md-6">
                <h2>アイコン存在確認結果</h2>
                <div class="mb-3">
                    <a href="admin_articles.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 記事管理に戻る
                    </a>
                </div>
                <h3>存在するアイコン: <?= count($existingIcons) ?>個</h3>
                <h3>不足しているアイコン: <?= count($missingIcons) ?>個</h3>
                <?php
                if (!empty($missingIcons)) {
                    echo "<h4>不足しているアイコン一覧:</h4>";
                    echo "<ul>";
                    foreach ($missingIcons as $missing) {
                        echo "<li>{$missing['service']} - {$missing['icon']}</li>";
                    }
                    echo "</ul>";
                    echo "<p><strong>これらのサービスには default-icon.png が表示されます。</strong></p>";
                }
                // default-icon.pngの存在確認
                if (file_exists('icons/default-icon.png')) {
                    echo "<p style='color: green;'>✓ default-icon.png は存在します (" . filesize('icons/default-icon.png') . " bytes)</p>";
                } else {
                    echo "<p style='color: red;'>✗ default-icon.png が見つかりません</p>";
                }
                ?>
            </div>
            <!-- 右ペイン：AIサービス一覧（アイコン＋サービス名、5列表示） -->
            <div class="col-md-6">
                <h4>AIサービス一覧</h4>
                <div class="row">
                <?php foreach ($services as $i => $service): ?>
                    <div class="col-md-2 mb-3 text-center">
                        <img src="icons/<?= htmlspecialchars($service['ai_icon']) ?>" alt="<?= htmlspecialchars($service['ai_service']) ?>"
                             style="width:40px;height:40px;object-fit:contain;background:#f8f9fa;border-radius:8px;"
                             onerror="this.src='icons/default-icon.png'">
                        <div style="font-size:0.95em;"><?= htmlspecialchars($service['ai_service']) ?></div>
                    </div>
                    <?php if (($i + 1) % 5 === 0): ?>
                        </div><div class="row">
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>
</body>
</html>