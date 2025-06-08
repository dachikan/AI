<?php
require_once 'includes/db_connect.php';

$pageTitle = 'AI サービス比較';

// 比較対象のIDを取得（URLパラメータまたはローカルストレージから）
$compareIds = [];
if (isset($_GET['ids'])) {
    $compareIds = array_map('intval', explode(',', $_GET['ids']));
} elseif (isset($_POST['compare_ids'])) {
    $compareIds = array_map('intval', $_POST['compare_ids']);
}

// 比較用データを取得
$compareServices = [];
if (!empty($compareIds)) {
    $compareServices = getAIServicesForComparison($compareIds);
}

// 全AIサービス一覧（選択用）
$allServices = getAIServices([], 'ai_service', 'ASC');

include 'includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4"><i class="fas fa-balance-scale"></i> AI サービス比較</h1>

    <!-- サービス選択セクション -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>比較するAIサービスを選択</h3>
        </div>
        <div class="card-body">
            <form method="POST" id="comparisonForm">
                <div class="row">
                    <div class="col-md-9">
                        <div class="row">
                            <?php foreach ($allServices as $service): ?>
                                <div class="col-md-3 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="compare_ids[]" 
                                               value="<?= $service['id'] ?>" id="service_<?= $service['id'] ?>"
                                               <?= in_array($service['id'], $compareIds) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="service_<?= $service['id'] ?>">
                                            <img src="icons/<?= htmlspecialchars($service['ai_icon']) ?>" 
                                                 alt="<?= htmlspecialchars($service['ai_service']) ?>" 
                                                 style="width: 20px; height: 20px; margin-right: 5px;"
                                                 onerror="this.src='icons/default-icon.png'">
                                            <?= htmlspecialchars($service['ai_service']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-balance-scale"></i> 比較する
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="loadFromLocalStorage()">
                                <i class="fas fa-download"></i> 保存済みから読込
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="clearComparison()">
                                <i class="fas fa-trash"></i> クリア
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($compareServices)): ?>
    <!-- 比較テーブル -->
    <div class="table-responsive">
        <table class="table table-bordered comparison-table">
            <thead>
                <tr>
                    <th style="width: 200px;">項目</th>
                    <?php foreach ($compareServices as $service): ?>
                        <th class="text-center">
                            <div class="p-3">
                                <img src="icons/<?= htmlspecialchars($service['ai_icon']) ?>" 
                                     alt="<?= htmlspecialchars($service['ai_service']) ?>" 
                                     class="ai-icon mb-2"
                                     onerror="this.src='icons/default-icon.png'">
                                <h5><?= htmlspecialchars($service['ai_service']) ?></h5>
                                <small class="text-muted"><?= htmlspecialchars($service['company_name']) ?></small>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <!-- 基本情報 -->
                <tr class="table-secondary">
                    <td><strong>基本情報</strong></td>
                    <?php foreach ($compareServices as $service): ?>
                        <td></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>概要</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td><?= htmlspecialchars(mb_substr($service['description'], 0, 150)) ?>...</td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>人気度スコア</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td class="text-center">
                            <strong class="text-primary"><?= $service['popularity_score'] ?>点</strong>
                            <div class="rating-stars mt-1">
                                <?php 
                                $rating = $service['popularity_score'] / 20;
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <i class="fas fa-star<?= $i <= $rating ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted"><?= number_format($rating, 1) ?></small>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 料金プラン -->
                <tr class="table-secondary">
                    <td><strong>料金プラン</strong></td>
                    <?php foreach ($compareServices as $service): ?>
                        <td></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>料金モデル</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td>
                            <?= htmlspecialchars($service['pricing_model']) ?>
                            <?php if ($service['free_tier_available']): ?>
                                <span class="badge badge-free ms-1">無料プランあり</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>登録要否</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td>
                            <?php if ($service['registration_required']): ?>
                                <span class="text-warning"><i class="fas fa-user-plus"></i> 必要</span>
                            <?php else: ?>
                                <span class="text-success"><i class="fas fa-check"></i> 不要</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 技術仕様 -->
                <tr class="table-secondary">
                    <td><strong>技術仕様</strong></td>
                    <?php foreach ($compareServices as $service): ?>
                        <td></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>モデル名</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td><?= htmlspecialchars($service['model_name']) ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>最大トークン</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td><?= $service['max_tokens'] ? number_format($service['max_tokens']) : '-' ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>入力形式</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td><?= htmlspecialchars($service['input_types']) ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>出力形式</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td><?= htmlspecialchars($service['output_types']) ?></td>
                    <?php endforeach; ?>
                </tr>

                <!-- 特徴 -->
                <tr class="table-secondary">
                    <td><strong>特徴</strong></td>
                    <?php foreach ($compareServices as $service): ?>
                        <td></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>強み</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td>
                            <?php if ($service['strengths']): ?>
                                <?php $strengths = array_slice(explode(',', $service['strengths']), 0, 3); ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($strengths as $strength): ?>
                                        <li><i class="fas fa-check text-success me-1"></i><?= htmlspecialchars(trim($strength)) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>制限事項</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td>
                            <?php if ($service['limitations']): ?>
                                <?php $limitations = array_slice(explode(',', $service['limitations']), 0, 2); ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($limitations as $limitation): ?>
                                        <li><i class="fas fa-exclamation-triangle text-warning me-1"></i><?= htmlspecialchars(trim($limitation)) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 対応言語 -->
                <tr class="table-secondary">
                    <td><strong>対応言語</strong></td>
                    <?php foreach ($compareServices as $service): ?>
                        <td></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>言語数</td>
                    <?php foreach ($compareServices as $service): ?>
                        <td>
                            <?php 
                            $languages = json_decode($service['supported_languages'], true) ?: [];
                            echo count($languages) . '言語';
                            ?>
                            <br>
                            <small class="text-muted">
                                <?= implode(', ', array_slice($languages, 0, 3)) ?>
                                <?= count($languages) > 3 ? '...' : '' ?>
                            </small>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- アクション -->
                <tr class="table-secondary">
                    <td><strong>アクション</strong></td>
                    <?php foreach ($compareServices as $service): ?>
                        <td>
                            <div class="d-grid gap-1">
                                <a href="AI_detail.php?id=<?= $service['id'] ?>" class="btn btn-primary btn-sm">詳細を見る</a>
                                <a href="<?= htmlspecialchars($service['official_url']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">公式サイト</a>
                                <?php if ($service['launch_url']): ?>
                                    <a href="<?= htmlspecialchars($service['launch_url']) ?>" target="_blank" class="btn btn-success btn-sm">今すぐ使う</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
        <h4>比較するAIサービスを選択してください</h4>
        <p class="text-muted">上記のチェックボックスから2つ以上のサービスを選択して「比較する」ボタンをクリックしてください。</p>
    </div>
    <?php endif; ?>

    <!-- ナビゲーション -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="AI_list.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> 一覧に戻る
                </a>
                <a href="AI_ranking.php" class="btn btn-outline-success">
                    <i class="fas fa-trophy"></i> ランキングを見る
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// ローカルストレージから比較リストを読み込む
function loadFromLocalStorage() {
    const compareList = JSON.parse(localStorage.getItem('compareList') || '[]');
    
    // 全てのチェックボックスをクリア
    document.querySelectorAll('input[name="compare_ids[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // 保存されたIDにチェックを入れる
    compareList.forEach(id => {
        const checkbox = document.getElementById('service_' + id);
        if (checkbox) {
            checkbox.checked = true;
        }
    });
    
    if (compareList.length > 0) {
        showToast('保存済みの比較リストを読み込みました');
    } else {
        showToast('保存済みの比較リストがありません');
    }
}

function clearComparison() {
    localStorage.removeItem('compareList');
    document.querySelectorAll('input[name="compare_ids[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    showToast('比較リストをクリアしました');
}
</script>

<?php include 'includes/footer.php'; ?>
