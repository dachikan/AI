<?php
require_once 'includes/db_connect.php';
require_once 'includes/post_functions.php';

$pageTitle = 'AI体験記事投稿（簡易版）';

// 人気のAIサービスを取得
$popularServices = getPopularityRanking(10);

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header text-center">
                    <h2><i class="fas fa-edit"></i> AI体験を共有</h2>
                    <p class="mb-0 text-muted">簡単3ステップでnote記事を作成</p>
                </div>
                <div class="card-body">
                    <form id="quickPostForm">
                        <!-- AIサービス選択 -->
                        <div class="mb-3">
                            <label class="form-label">使ったAIサービス</label>
                            <select class="form-select" name="ai_service_id" required>
                                <option value="">選択してください</option>
                                <?php foreach ($popularServices as $service): ?>
                                    <option value="<?= $service['id'] ?>">
                                        <?= htmlspecialchars($service['ai_service']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 簡易入力 -->
                        <div class="mb-3">
                            <label class="form-label">何に使いましたか？</label>
                            <input type="text" class="form-control" name="usage_purpose" required
                                   placeholder="例：ブログ記事作成、画像生成、翻訳など">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">感想を一言</label>
                            <textarea class="form-control" name="user_review" rows="3" required
                                      placeholder="使ってみてどうでしたか？"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">評価</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="user_rating" value="5" id="rating5">
                                <label class="btn btn-outline-warning" for="rating5">⭐⭐⭐⭐⭐</label>
                                
                                <input type="radio" class="btn-check" name="user_rating" value="4" id="rating4">
                                <label class="btn btn-outline-warning" for="rating4">⭐⭐⭐⭐</label>
                                
                                <input type="radio" class="btn-check" name="user_rating" value="3" id="rating3">
                                <label class="btn btn-outline-warning" for="rating3">⭐⭐⭐</label>
                                
                                <input type="radio" class="btn-check" name="user_rating" value="2" id="rating2">
                                <label class="btn btn-outline-warning" for="rating2">⭐⭐</label>
                                
                                <input type="radio" class="btn-check" name="user_rating" value="1" id="rating1">
                                <label class="btn btn-outline-warning" for="rating1">⭐</label>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="button" class="btn btn-primary btn-lg" onclick="generateQuickPost()">
                                <i class="fas fa-magic"></i> note記事を生成
                            </button>
                        </div>
                    </form>

                    <!-- 結果表示エリア -->
                    <div id="resultArea" style="display: none;" class="mt-4">
                        <div class="alert alert-success">
                            <h5>記事が生成されました！</h5>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <h6 id="generatedTitle"></h6>
                                <div id="generatedContent" class="border p-3 bg-light small" style="max-height: 300px; overflow-y: auto; white-space: pre-line;"></div>
                            </div>
                            <div class="card-footer">
                                <div class="d-grid gap-2">
                                    <a href="#" id="noteLink" class="btn btn-success" target="_blank">
                                        <i class="fas fa-external-link-alt"></i> noteで投稿する
                                    </a>
                                    <button type="button" class="btn btn-outline-primary" onclick="copyContent()">
                                        <i class="fas fa-copy"></i> コピー
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function generateQuickPost() {
    const form = document.getElementById('quickPostForm');
    const formData = new FormData(form);
    
    // 入力チェック
    if (!formData.get('ai_service_id') || !formData.get('usage_purpose') || 
        !formData.get('user_review') || !formData.get('user_rating')) {
        alert('すべての項目を入力してください');
        return;
    }
    
    try {
        const response = await fetch('AI_post_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ai_service_id: formData.get('ai_service_id'),
                template_type: 'new_post',
                usage_purpose: formData.get('usage_purpose'),
                user_review: formData.get('user_review'),
                user_rating: formData.get('user_rating'),
                save_history: true
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('generatedTitle').textContent = result.data.title;
            document.getElementById('generatedContent').textContent = result.data.content;
            document.getElementById('noteLink').href = result.data.note_url;
            document.getElementById('resultArea').style.display = 'block';
            document.getElementById('resultArea').scrollIntoView({ behavior: 'smooth' });
        } else {
            alert('エラー: ' + result.error);
        }
    } catch (error) {
        alert('通信エラーが発生しました: ' + error.message);
    }
}

function copyContent() {
    const content = document.getElementById('generatedContent').textContent;
    navigator.clipboard.writeText(content).then(() => {
        alert('コンテンツをコピーしました！');
    });
}
</script>

<?php include 'includes/footer.php'; ?>
