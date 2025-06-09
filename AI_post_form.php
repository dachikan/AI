<?php
require_once 'includes/db_connect.php';

$pageTitle = 'AI体験記事を投稿';

// AIサービス一覧を取得
$aiServices = getAIServices([], 'ai_service', 'ASC');

// 選択されたAIサービスのテンプレートを取得
$selectedServiceId = isset($_GET['ai_service_id']) ? intval($_GET['ai_service_id']) : null;
$templates = [];
$selectedService = null;

if ($selectedServiceId) {
    $selectedService = getAIServiceById($selectedServiceId);
    $templates = getPostTemplates($selectedServiceId);
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-edit"></i> AI体験記事を投稿</h2>
                    <p class="mb-0 text-muted">あなたのAI体験をnoteで共有しましょう</p>
                </div>
                <div class="card-body">
                    <!-- Step 1: AIサービス選択 -->
                    <div class="mb-4">
                        <h5>Step 1: AIサービスを選択</h5>
                        <form method="GET" id="serviceSelectForm">
                            <div class="row">
                                <div class="col-md-8">
                                    <select class="form-select" name="ai_service_id" onchange="document.getElementById('serviceSelectForm').submit()">
                                        <option value="">AIサービスを選択してください</option>
                                        <?php foreach ($aiServices as $service): ?>
                                            <option value="<?= $service['id'] ?>" <?= $selectedServiceId == $service['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($service['ai_service']) ?> - <?= htmlspecialchars($service['company_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary">選択</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if ($selectedService && !empty($templates)): ?>
                        <!-- Step 2: 投稿フォーム -->
                        <div class="mb-4">
                            <h5>Step 2: 体験内容を入力</h5>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                選択されたAI: <strong><?= htmlspecialchars($selectedService['ai_service']) ?></strong>
                            </div>
                            
                            <form id="postForm">
                                <input type="hidden" name="ai_service_id" value="<?= $selectedServiceId ?>">
                                
                                <!-- テンプレートタイプ選択 -->
                                <div class="mb-3">
                                    <label class="form-label">投稿タイプ</label>
                                    <div class="row">
                                        <?php foreach ($templates as $template): ?>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="template_type" 
                                                           value="<?= $template['template_type'] ?>" 
                                                           id="template_<?= $template['template_type'] ?>"
                                                           <?= $template['template_type'] === 'new_post' ? 'checked' : '' ?>
                                                           onchange="updateTemplatePreview()">
                                                    <label class="form-check-label" for="template_<?= $template['template_type'] ?>">
                                                        <strong><?= $template['template_type'] === 'new_post' ? '新規記事作成' : '既存記事への追記' ?></strong>
                                                        <br><small class="text-muted">
                                                            <?= $template['template_type'] === 'new_post' ? 'noteに新しい記事を作成' : '既存の記事にコメントを追加' ?>
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- 既存記事URL（existing_postの場合のみ表示） -->
                                <div class="mb-3" id="existing_url_field" style="display: none;">
                                    <label class="form-label">既存記事のURL</label>
                                    <input type="url" class="form-control" name="post_url" 
                                           placeholder="https://note.com/username/n/article_id">
                                </div>

                                <!-- 使用目的 -->
                                <div class="mb-3">
                                    <label class="form-label">使用目的 <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="usage_purpose" rows="2" required
                                              placeholder="どのような目的でこのAIを使いましたか？（例：ブログ記事の作成、画像生成、コード作成など）"></textarea>
                                </div>

                                <!-- 使用感・レビュー -->
                                <div class="mb-3">
                                    <label class="form-label">使ってみた感想 <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="user_review" rows="4" required
                                              placeholder="実際に使ってみてどうでしたか？良かった点、改善点、驚いたことなど詳しく教えてください。"></textarea>
                                </div>

                                <!-- 評価 -->
                                <div class="mb-3">
                                    <label class="form-label">評価 <span class="text-danger">*</span></label>
                                    <select class="form-select" name="user_rating" required>
                                        <option value="">評価を選択</option>
                                        <option value="5">⭐⭐⭐⭐⭐ 最高！期待以上でした</option>
                                        <option value="4">⭐⭐⭐⭐ 良い！満足しています</option>
                                        <option value="3">⭐⭐⭐ 普通。まあまあです</option>
                                        <option value="2">⭐⭐ イマイチ。期待外れでした</option>
                                        <option value="1">⭐ 残念。使いにくかったです</option>
                                    </select>
                                </div>

                                <!-- プレビューボタン -->
                                <div class="d-grid gap-2 mb-3">
                                    <button type="button" class="btn btn-outline-primary" onclick="generatePreview()">
                                        <i class="fas fa-eye"></i> プレビューを生成
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Step 3: プレビュー -->
                        <div id="previewSection" style="display: none;">
                            <h5>Step 3: プレビュー確認</h5>
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 id="previewTitle">記事タイトル</h6>
                                </div>
                                <div class="card-body">
                                    <div id="previewContent" class="border p-3 bg-light" style="white-space: pre-line;"></div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-grid gap-2">
                                        <a href="#" id="notePostLink" class="btn btn-success btn-lg" target="_blank">
                                            <i class="fas fa-external-link-alt"></i> noteに投稿する
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard()">
                                            <i class="fas fa-copy"></i> 内容をコピー
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($selectedService): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            選択されたAIサービスのテンプレートが見つかりません。
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 使い方ガイド -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-question-circle"></i> 使い方ガイド</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>1. AIサービス選択</h6>
                            <p class="small">体験したAIサービスを選択してください。</p>
                        </div>
                        <div class="col-md-4">
                            <h6>2. 体験内容入力</h6>
                            <p class="small">使用目的、感想、評価を詳しく入力してください。</p>
                        </div>
                        <div class="col-md-4">
                            <h6>3. noteに投稿</h6>
                            <p class="small">生成された記事をnoteに投稿して共有しましょう。</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- テンプレートデータをJavaScriptで使用 -->
<script>
const templates = <?= json_encode($templates) ?>;
const serviceData = <?= json_encode($selectedService) ?>;

function updateTemplatePreview() {
    const templateType = document.querySelector('input[name="template_type"]:checked')?.value;
    const existingUrlField = document.getElementById('existing_url_field');
    
    if (templateType === 'existing_post') {
        existingUrlField.style.display = 'block';
        existingUrlField.querySelector('input').required = true;
    } else {
        existingUrlField.style.display = 'none';
        existingUrlField.querySelector('input').required = false;
    }
}

function generatePreview() {
    const formData = new FormData(document.getElementById('postForm'));
    const templateType = formData.get('template_type');
    const usagePurpose = formData.get('usage_purpose');
    const userReview = formData.get('user_review');
    const userRating = formData.get('user_rating');
    const postUrl = formData.get('post_url');
    
    // 入力チェック
    if (!usagePurpose || !userReview || !userRating) {
        alert('必須項目をすべて入力してください。');
        return;
    }
    
    if (templateType === 'existing_post' && !postUrl) {
        alert('既存記事のURLを入力してください。');
        return;
    }
    
    // テンプレートを取得
    const template = templates.find(t => t.template_type === templateType);
    if (!template) {
        alert('テンプレートが見つかりません。');
        return;
    }
    
    // テンプレートの変数を置換
    let title = template.title_template.replace('{AI_SERVICE_NAME}', serviceData.ai_service);
    let content = template.content_template
        .replace(/{AI_SERVICE_NAME}/g, serviceData.ai_service)
        .replace(/{USAGE_PURPOSE}/g, usagePurpose)
        .replace(/{USER_REVIEW}/g, userReview)
        .replace(/{USER_RATING}/g, userRating)
        .replace(/{OFFICIAL_URL}/g, serviceData.official_url || '')
        .replace(/{POST_URL}/g, postUrl || '');
    
    // ハッシュタグ処理
    let hashtags = template.hashtags.replace(/{AI_SERVICE_NAME}/g, serviceData.ai_service);
    content = content.replace(/{HASHTAGS}/g, hashtags);
    
    // プレビュー表示
    document.getElementById('previewTitle').textContent = title;
    document.getElementById('previewContent').textContent = content;
    
    // noteリンク生成
    if (template.note_url_template) {
        const noteUrl = template.note_url_template
            .replace('{TITLE}', encodeURIComponent(title))
            .replace('{CONTENT}', encodeURIComponent(content));
        document.getElementById('notePostLink').href = noteUrl;
    }
    
    // プレビューセクションを表示
    document.getElementById('previewSection').style.display = 'block';
    document.getElementById('previewSection').scrollIntoView({ behavior: 'smooth' });
}

function copyToClipboard() {
    const content = document.getElementById('previewContent').textContent;
    navigator.clipboard.writeText(content).then(() => {
        alert('内容をクリップボードにコピーしました！');
    }).catch(err => {
        console.error('コピーに失敗しました:', err);
    });
}

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    updateTemplatePreview();
});
</script>

<?php include 'includes/footer.php'; ?>
