<?php
require_once 'includes/db_connect.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$service = getAIServiceById($id);

if (!$service) {
    header('HTTP/1.0 404 Not Found');
    include 'includes/header.php';
    echo '<div class="container py-5 text-center">
            <h1>AIサービスが見つかりません</h1>
            <p><a href="AI_list.php" class="btn btn-primary">一覧に戻る</a></p>
          </div>';
    include 'includes/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($service['ai_service']) . ' - AI サービス詳細';

// 対応言語をJSONから配列に変換
$languages = json_decode($service['supported_languages'], true) ?: [];

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- サービスヘッダー -->
    <div class="card mb-4" style="background: linear-gradient(135deg, <?= htmlspecialchars($service['brand_color']) ?>20, <?= htmlspecialchars($service['brand_color']) ?>10);">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="icons/<?= htmlspecialchars($service['ai_icon']) ?>" 
                         alt="<?= htmlspecialchars($service['ai_service']) ?>" 
                         class="ai-icon-large"
                         onerror="this.src='icons/default-icon.png'">
                </div>
                <div class="col-md-7">
                    <h1 class="mb-2"><?= htmlspecialchars($service['ai_service']) ?></h1>
                    <p class="text-muted mb-2"><?= htmlspecialchars($service['company_name']) ?></p>
                    <div class="rating-stars mb-2">
                        <?php 
                        $rating = $service['popularity_score'] / 20;
                        for ($i = 1; $i <= 5; $i++): 
                        ?>
                            <i class="fas fa-star<?= $i <= $rating ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                        <span class="ms-2 text-muted"><?= number_format($rating, 1) ?> (人気度: <?= $service['popularity_score'] ?>点)</span>
                    </div>
                    <div>
                        <?php if ($service['free_tier_available']): ?>
                            <span class="badge badge-free me-2">無料プランあり</span>
                        <?php endif; ?>
                        <?php if ($service['popularity_score'] >= 80): ?>
                            <span class="badge badge-recommended me-2">おすすめ</span>
                        <?php endif; ?>
                        <?php if ($service['api_available']): ?>
                            <span class="badge bg-info me-2">API対応</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <div class="d-grid gap-2">
                        <a href="<?= htmlspecialchars($service['official_url']) ?>" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i> 公式サイト
                        </a>
                        <?php if ($service['launch_url']): ?>
                            <a href="<?= htmlspecialchars($service['launch_url']) ?>" target="_blank" class="btn btn-success">
                                <i class="fas fa-rocket"></i> 今すぐ使う
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary compare-btn" 
                                data-service-id="<?= $service['id'] ?>"
                                onclick="addToComparison(<?= $service['id'] ?>)">
                            <i class="fas fa-balance-scale"></i> 比較に追加
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- 左カラム：基本情報 -->
        <div class="col-md-8">
            <!-- 概要 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> 概要</h3>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($service['description'])) ?></p>
                </div>
            </div>

            <!-- 強み・特徴 -->
            <?php if ($service['strengths']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-star"></i> 強み・特徴</h3>
                </div>
                <div class="card-body">
                    <?php $strengths = explode(',', $service['strengths']); ?>
                    <ul class="list-unstyled">
                        <?php foreach ($strengths as $strength): ?>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <?= htmlspecialchars(trim($strength)) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- 制限事項 -->
            <?php if ($service['limitations']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> 制限事項</h3>
                </div>
                <div class="card-body">
                    <?php $limitations = explode(',', $service['limitations']); ?>
                    <ul class="list-unstyled">
                        <?php foreach ($limitations as $limitation): ?>
                            <li class="mb-2">
                                <i class="fas fa-minus-circle text-warning me-2"></i>
                                <?= htmlspecialchars(trim($limitation)) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- ユーザーアクション -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> ユーザーアクション</h3>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <button class="btn btn-outline-success btn-lg w-100 mb-2" onclick="likeService(<?= $service['id'] ?>)">
                                <i class="fas fa-thumbs-up"></i><br>
                                <span id="like-count-<?= $service['id'] ?>">0</span><br>
                                いいね
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-danger btn-lg w-100 mb-2" onclick="dislikeService(<?= $service['id'] ?>)">
                                <i class="fas fa-thumbs-down"></i><br>
                                <span id="dislike-count-<?= $service['id'] ?>">0</span><br>
                                だめね
                            </button>
                        </div>
                        <div class="col-md-4">
                            <a href="https://note.com/hashtag/<?= urlencode($service['ai_service']) ?>" 
                               target="_blank" class="btn btn-outline-primary btn-lg w-100 mb-2">
                                <i class="fas fa-edit"></i><br>
                                使ったよ<br>
                                (note記事)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右カラム：技術仕様 -->
        <div class="col-md-4">
            <!-- 基本仕様 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4><i class="fas fa-cog"></i> 基本仕様</h4>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <?php if ($service['model_name']): ?>
                        <tr>
                            <td><strong>モデル</strong></td>
                            <td><?= htmlspecialchars($service['model_name']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($service['max_tokens']): ?>
                        <tr>
                            <td><strong>最大トークン</strong></td>
                            <td><?= number_format($service['max_tokens']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>入力形式</strong></td>
                            <td><?= htmlspecialchars($service['input_types']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>出力形式</strong></td>
                            <td><?= htmlspecialchars($service['output_types']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>料金モデル</strong></td>
                            <td><?= htmlspecialchars($service['pricing_model']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>登録要否</strong></td>
                            <td><?= $service['registration_required'] ? '必要' : '不要' ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- 対応言語 -->
            <?php if (!empty($languages)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4><i class="fas fa-language"></i> 対応言語</h4>
                </div>
                <div class="card-body">
                    <?php foreach ($languages as $lang): ?>
                        <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($lang) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- リリース情報 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4><i class="fas fa-calendar"></i> リリース情報</h4>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <?php if ($service['version']): ?>
                        <tr>
                            <td><strong>バージョン</strong></td>
                            <td><?= htmlspecialchars($service['version']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($service['release_date']): ?>
                        <tr>
                            <td><strong>リリース日</strong></td>
                            <td><?= date('Y年m月d日', strtotime($service['release_date'])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>最終更新</strong></td>
                            <td><?= date('Y年m月d日', strtotime($service['last_updated_info'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- API情報 -->
            <?php if ($service['api_available']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4><i class="fas fa-code"></i> API情報</h4>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>API利用可能</strong></p>
                    <?php if ($service['api_url']): ?>
                        <a href="<?= htmlspecialchars($service['api_url']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-link"></i> API ドキュメント
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ナビゲーション -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="AI_list.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> 一覧に戻る
                </a>
                <div>
                    <a href="AI_comparison.php" class="btn btn-primary">
                        <i class="fas fa-balance-scale"></i> 比較ページ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function likeService(serviceId) {
    // いいね機能（実装例）
    let likes = parseInt(localStorage.getItem('likes_' + serviceId) || '0');
    likes++;
    localStorage.setItem('likes_' + serviceId, likes);
    document.getElementById('like-count-' + serviceId).textContent = likes;
    showToast('いいねしました！');
}

function dislikeService(serviceId) {
    // だめね機能（実装例）
    let dislikes = parseInt(localStorage.getItem('dislikes_' + serviceId) || '0');
    dislikes++;
    localStorage.setItem('dislikes_' + serviceId, dislikes);
    document.getElementById('dislike-count-' + serviceId).textContent = dislikes;
    showToast('フィードバックありがとうございます');
}

// ページ読み込み時にカウントを表示
document.addEventListener('DOMContentLoaded', function() {
    const serviceId = <?= $service['id'] ?>;
    const likes = localStorage.getItem('likes_' + serviceId) || '0';
    const dislikes = localStorage.getItem('dislikes_' + serviceId) || '0';
    document.getElementById('like-count-' + serviceId).textContent = likes;
    document.getElementById('dislike-count-' + serviceId).textContent = dislikes;
});
</script>

<?php include 'includes/footer.php'; ?>
