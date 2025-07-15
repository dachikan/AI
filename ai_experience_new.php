<?php
// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// セッション開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';

$pageTitle = 'AI体験記事作成';

// セッションIDの生成
$sessionId = $_SESSION['article_session_id'] ?? uniqid('art_', true);
$_SESSION['article_session_id'] = $sessionId;

// 現在のステップを取得
$step = $_GET['step'] ?? 1;
$message = '';
$error = '';

// AJAX処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // エラー出力を無効化
    ob_clean();
    ob_start();
    
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_ai_summary':
                $aiServiceId = intval($_POST['ai_service_id']);
                $aiService = getAIServiceById($aiServiceId);
                if ($aiService) {
                    echo json_encode([
                        'success' => true,
                        'summary' => [
                            'description' => $aiService['description'],
                            'strengths' => $aiService['strengths'],
                            'limitations' => $aiService['limitations'],
                            'company_name' => $aiService['company_name']
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'AIサービスが見つかりません']);
                }
                exit;
                
            case 'save_draft':
                $draftData = [
                    'ai_service_id' => intval($_POST['ai_service_id']),
                    'usage_purpose' => $_POST['usage_purpose'],
                    'features_used' => $_POST['features_used'] ?? [],
                    'version_used' => $_POST['version_used'],
                    'satisfaction' => intval($_POST['satisfaction']),
                    'notes' => $_POST['notes'],
                    'article_content' => $_POST['article_content']
                ];
                
                // セッションに保存
                $_SESSION['article_draft'] = $draftData;
                $_SESSION['draft_saved_at'] = date('Y-m-d H:i:s');
                
                echo json_encode(['success' => true, 'message' => '下書きを保存しました']);
                exit;
                
            case 'load_draft':
                if (isset($_SESSION['article_draft'])) {
                    echo json_encode([
                        'success' => true,
                        'draft' => $_SESSION['article_draft'],
                        'saved_at' => $_SESSION['draft_saved_at']
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => '保存された下書きがありません']);
                }
                exit;
                
            case 'generate_article':
                try {
                    $formData = $_POST;
                    $aiService = getAIServiceById($formData['ai_service_id']);
                    
                    if (!$aiService) {
                        echo json_encode(['success' => false, 'error' => 'AIサービスが見つかりません']);
                        exit;
                    }
                    
                    $categories = getAITypes(); // 正しい関数名に変更
                    
                    // 選択されたカテゴリ名を取得
                    $selectedCategories = [];
                    if (!empty($formData['features_used'])) {
                        foreach ($categories as $category) {
                            if (in_array($category['id'], $formData['features_used'])) {
                                $selectedCategories[] = $category['name'];
                            }
                        }
                    }
                    
                    // 記事テンプレートを生成
                    $article = generateArticleTemplate($aiService, $formData, $selectedCategories);
                    
                    echo json_encode(['success' => true, 'article' => $article]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'エラーが発生しました: ' . $e->getMessage()]);
                }
                exit;
            case 'save_article_for_posting':
                $_SESSION['article_content'] = $_POST['article_content'];
                echo json_encode(['success' => true]);
                exit;
            case 'get_ai_service_info':
                $aiServiceId = intval($_POST['ai_service_id']);
                $aiService = getAIServiceById($aiServiceId);    
                if ($aiService) {
                    echo json_encode([
                        'success' => true,
                        'aiService' => $aiService
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'AIサービスが見つかりません']);
                }
                exit;
            default:
                echo json_encode(['success' => false, 'error' => '不明なアクションです']);
                exit;
        }
    } catch (Exception $e) {
        // 例外をキャッチしてエラーメッセージをJSONで返す
        echo json_encode(['success' => false, 'error' => 'エラーが発生しました: ' . $e->getMessage()]);
        exit;
    }
}

// データ取得
$aiServices = getAIServices();
$promptCategories = getAITypes(); // または正しい関数名に変更

include 'includes/header.php';
?>

<style>
    .pane-container {
        display: flex;
        height: calc(100vh - 200px);
        min-height: 600px;
    }

    .left-pane, .right-pane {
        flex: 1;
        padding: 20px;
        border: 1px solid #dee2e6;
        overflow-y: auto;
    }

    .left-pane {
        background-color: #f8f9fa;
        border-right: none;
    }

    .right-pane {
        background-color: #ffffff;
    }

    .pane-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #dee2e6;
    }

    .swap-btn {
        background: none;
        border: none;
        font-size: 1.2em;
        cursor: pointer;
        color: #6c757d;
    }

    .swap-btn:hover {
        color: #495057;
    }

    .article-editor {
        width: 100%;
        height: 400px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        padding: 10px;
        font-family: 'Hiragino Sans', 'Yu Gothic', sans-serif;
        font-size: 14px;
        line-height: 1.6;
        resize: vertical;
    }

    .ai-summary-box {
        background-color: #e3f2fd;
        border: 1px solid #2196f3;
        border-radius: 0.375rem;
        padding: 15px;
        margin-top: 15px;
    }

    .satisfaction-stars {
        display: flex;
        gap: 5px;
    }

    .satisfaction-stars input[type="radio"] {
        display: none;
    }

    .satisfaction-stars label {
        font-size: 1.5em;
        color: #ddd;
        cursor: pointer;
        transition: color 0.2s;
    }

    .satisfaction-stars input[type="radio"]:checked ~ label,
    .satisfaction-stars label:hover {
        color: #ffc107;
    }

    .draft-info {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 0.375rem;
        padding: 10px;
        margin-bottom: 15px;
        font-size: 0.9em;
    }

    .action-buttons {
        position: sticky;
        bottom: 0;
        background-color: white;
        padding: 15px 0;
        border-top: 1px solid #dee2e6;
        margin-top: 20px;
    }

    .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
    }

    .step-indicator {
        display: flex;
        justify-content: center;
        margin-bottom: 30px;
    }

    .step-indicator .step {
        display: flex;
        align-items: center;
        padding: 10px 20px;
        background-color: #e9ecef;
        border-radius: 20px;
        margin: 0 10px;
        font-weight: bold;
    }

    .step-indicator .step.active {
        background-color: #007bff;
        color: white;
    }

    .step-indicator .step.completed {
        background-color: #28a745;
        color: white;
    }
</style>

<div class="container-fluid py-4">
    <!-- ステップインジケーター -->
    <div class="step-indicator">
        <div class="step <?= $step == 1 ? 'active' : ($step > 1 ? 'completed' : '') ?>">
            <i class="fas fa-edit me-2"></i>1. 記事生成
        </div>
        <div class="step <?= $step == 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>">
            <i class="fas fa-pen me-2"></i>2. 記事編集
        </div>
        <div class="step <?= $step == 3 ? 'active' : ($step > 3 ? 'completed' : '') ?>">
            <i class="fas fa-upload me-2"></i>3. 投稿
        </div>
        <div class="step <?= $step == 4 ? 'active' : '' ?>">
            <i class="fas fa-check me-2"></i>4. 登録
        </div>
    </div>

    <?php if ($step == 1): ?>
    <!-- Step 1: 記事生成ステップ -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-edit"></i> AI体験記事作成</h4>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="saveDraft()">
                            <i class="fas fa-save"></i> 下書き保存
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm me-2" onclick="loadDraft()">
                            <i class="fas fa-folder-open"></i> 下書き読込
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="swapPanes()">
                            <i class="fas fa-exchange-alt"></i> ペイン入替
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- 下書き情報表示エリア -->
                    <div id="draft-info" class="draft-info" style="display: none; margin: 15px;">
                        <i class="fas fa-info-circle"></i> <span id="draft-message"></span>
                    </div>
                    
                    <div class="pane-container" id="pane-container">
                        <!-- 左ペイン: 情報入力 -->
                        <div class="left-pane" id="left-pane">
                            <div class="pane-header">
                                <h5><i class="fas fa-form"></i> 体験情報入力</h5>
                            </div>
                            
                            <form id="experience-form">
                                <!-- AIサービス選択 -->
                                <div class="mb-3">
                                    <label class="form-label">使用したAIサービス <span class="text-danger">*</span></label>
                                    <select class="form-select" name="ai_service_id" id="ai_service_id" required>
                                        <option value="">選択してください</option>
                                        <?php foreach ($aiServices as $service): ?>
                                            <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['ai_service']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- 使用目的 -->
                                <div class="mb-3">
                                    <label class="form-label">使用目的 <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="usage_purpose" rows="3" required
                                                placeholder="どのような目的でこのAIを使いましたか？"></textarea>
                                </div>

                                <!-- 使用した機能 -->
                                <div class="mb-3">
                                    <label class="form-label">使用した機能</label>
                                    <div class="feature-grid">
                                        <?php foreach ($promptCategories as $category): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                    name="features_used[]" value="<?= $category['id'] ?>"
                                                    id="feature_<?= $category['id'] ?>">
                                                <label class="form-check-label" for="feature_<?= $category['id'] ?>">
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- 使用した版 -->
                                <div class="mb-3">
                                    <label class="form-label">使用した版 <span class="text-danger">*</span></label>
                                    <select class="form-select" name="version_used" required>
                                        <option value="">選択してください</option>
                                        <option value="free">無料版</option>
                                        <option value="paid">有料版</option>
                                        <option value="enterprise">企業版</option>
                                    </select>
                                </div>

                                <!-- 満足度 -->
                                <div class="mb-3">
                                    <label class="form-label">満足度 <span class="text-danger">*</span></label>
                                    <select class="form-select" name="satisfaction" required>
                                        <option value="">選択してください</option>
                                        <option value="1">⭐ 不満</option>
                                        <option value="2">⭐⭐ やや不満</option>
                                        <option value="3" selected>⭐⭐⭐ 普通</option>
                                        <option value="4">⭐⭐⭐⭐ 満足</option>
                                        <option value="5">⭐⭐⭐⭐⭐ 非常に満足</option>
                                    </select>
                                </div>

                                <!-- メモ・感想 -->
                                <div class="mb-3">
                                    <label class="form-label">メモ・感想</label>
                                    <textarea class="form-control" name="notes" rows="3"
                                            placeholder="使って感じたことや気づいたことがあれば記入して下さい"></textarea>
                                </div>

                                <!-- AIサマリー表示エリア -->
                                <div id="ai-summary" class="ai-summary-box" style="display: none;">
                                    <h6><i class="fas fa-robot"></i> AIサマリー</h6>
                                    <div id="ai-summary-content"></div>
                                </div>
                            </form>

                            <div class="action-buttons">
                                <button type="button" class="btn btn-primary" onclick="generateArticle()">
                                    <i class="fas fa-magic"></i> 記事を生成
                                </button>
                            </div>
                        </div>

                        <!-- 右ペイン: 記事編集 -->
                        <div class="right-pane" id="right-pane">
                            <div class="pane-header">
                                <h5><i class="fas fa-edit"></i> 記事編集</h5>
                            </div>
                            
                            <div id="article-generation-message" class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                左ペインで体験情報を入力し、「記事を生成」ボタンをクリックしてください。
                            </div>
                            
                            <textarea id="article-editor" class="article-editor" 
                                      placeholder="記事が生成されるとここに表示されます..." 
                                      disabled></textarea>
                            
                            <div class="action-buttons" style="display: none;" id="editor-actions">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="addImage()">
                                        <i class="fas fa-image"></i> 画像追加
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="reviewWithAI()">
                                        <i class="fas fa-robot"></i> AIレビュー
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="proceedToEdit()">
                                        <i class="fas fa-arrow-right"></i> 編集完了
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($step == 2): ?>
<!-- Step 2: 投稿ステップ -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-upload"></i> 記事投稿</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    作成した記事を投稿プラットフォームに投稿してください。記事内容は自動的にクリップボードにコピーされます。
                </div>
                
                <!-- 記事プレビュー -->
                <div class="mb-4">
                    <h5>作成した記事</h5>
                    <div class="border rounded p-3" style="background-color: #f8f9fa; max-height: 300px; overflow-y: auto;">
                        <pre id="article-preview" style="white-space: pre-wrap; font-family: inherit;"><?= htmlspecialchars($_SESSION['article_content'] ?? '記事が見つかりません') ?></pre>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="copyToClipboard()">
                        <i class="fas fa-copy"></i> クリップボードにコピー
                    </button>
                </div>
                
                <!-- 投稿プラットフォーム選択 -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-edit fa-3x text-primary mb-3"></i>
                                <h5>note</h5>
                                <p class="text-muted">日本最大級のクリエイタープラットフォーム</p>
                                <a href="https://note.com/new" target="_blank" class="btn btn-primary" onclick="trackPlatformClick('note')">
                                    <i class="fas fa-external-link-alt"></i> noteで投稿
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-code fa-3x text-success mb-3"></i>
                                <h5>Qiita</h5>
                                <p class="text-muted">エンジニア向け技術情報共有サービス</p>
                                <a href="https://qiita.com/drafts/new" target="_blank" class="btn btn-success" onclick="trackPlatformClick('qiita')">
                                    <i class="fas fa-external-link-alt"></i> Qiitaで投稿
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- その他のプラットフォーム -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fab fa-medium fa-2x text-dark mb-2"></i>
                                <h6>Medium</h6>
                                <a href="https://medium.com/new-story" target="_blank" class="btn btn-outline-dark btn-sm">投稿</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fab fa-dev fa-2x text-dark mb-2"></i>
                                <h6>dev.to</h6>
                                <a href="https://dev.to/new" target="_blank" class="btn btn-outline-dark btn-sm">投稿</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fab fa-blogger fa-2x text-warning mb-2"></i>
                                <h6>はてなブログ</h6>
                                <a href="https://blog.hatena.ne.jp/" target="_blank" class="btn btn-outline-warning btn-sm">投稿</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="?step=1" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 記事編集に戻る
                    </a>
                    <button type="button" class="btn btn-primary" onclick="proceedToRegistration()">
                        <i class="fas fa-arrow-right"></i> 投稿完了・URL登録へ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 記事内容をセッションから取得してプレビューに表示
document.addEventListener('DOMContentLoaded', function() {
    // 記事内容を自動的にクリップボードにコピー
    copyToClipboard();
});

// クリップボードにコピー
function copyToClipboard() {
    const articleContent = document.getElementById('article-preview').textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(articleContent).then(function() {
            showToast('記事内容をクリップボードにコピーしました');
        }).catch(function(err) {
            console.error('コピーに失敗しました: ', err);
        });
    } else {
        // フォールバック
        const textArea = document.createElement('textarea');
        textArea.value = articleContent;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('記事内容をクリップボードにコピーしました');
    }
}

// プラットフォームクリック追跡
function trackPlatformClick(platform) {
    console.log('投稿プラットフォーム選択:', platform);
    // 将来的にアナリティクス追跡を追加可能
}

// 登録ステップへ進む
function proceedToRegistration() {
    if (confirm('記事を投稿しましたか？\n\n次のステップでは投稿した記事のURLを登録します。')) {
        window.location.href = '?step=3';
    }
}

// トースト通知表示
function showToast(message) {
    // 簡易トースト通知
    const toast = document.createElement('div');
    toast.className = 'alert alert-success position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>
<?php endif; ?>
</div>

<script>
// グローバル変数
let currentDraft = null;
let isSwapped = false;

// ページ読み込み時の初期化
document.addEventListener('DOMContentLoaded', function() {
    // AIサービス選択時のイベント
    document.getElementById('ai_service_id').addEventListener('change', function() {
        const aiServiceId = this.value;
        if (aiServiceId) {
            loadAISummary(aiServiceId);
        } else {
            document.getElementById('ai-summary').style.display = 'none';
        }
    });

    // 満足度の星評価
    setupStarRating();
});

// AIサマリー読み込み
function loadAISummary(aiServiceId) {
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_ai_summary&ai_service_id=' + aiServiceId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAISummary(data.summary);
        } else {
            console.error('Error:', data.error);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
    });
}

// AIサマリー表示
function displayAISummary(summary) {
    const summaryDiv = document.getElementById('ai-summary');
    const contentDiv = document.getElementById('ai-summary-content');
    
    contentDiv.innerHTML = `
        <p><strong>概要:</strong> ${summary.description}</p>
        <p><strong>強み:</strong> ${summary.strengths}</p>
        <p><strong>制限事項:</strong> ${summary.limitations}</p>
        <p><strong>開発会社:</strong> ${summary.company_name}</p>
    `;
    
    summaryDiv.style.display = 'block';
}

// 星評価の設定
function setupStarRating() {
    const stars = document.querySelectorAll('.satisfaction-stars input[type="radio"]');
    stars.forEach((star, index) => {
        star.addEventListener('change', function() {
            updateStarDisplay(index + 1);
        });
    });
}

// 星表示の更新
function updateStarDisplay(rating) {
    const labels = document.querySelectorAll('.satisfaction-stars label');
    labels.forEach((label, index) => {
        if (index < rating) {
            label.style.color = '#ffc107';
        } else {
            label.style.color = '#ddd';
        }
    });
}

// 記事生成
function generateArticle() {
    const form = document.getElementById('experience-form');
    const formData = new FormData(form);
    formData.append('action', 'generate_article');

    // フォームバリデーション
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayGeneratedArticle(data.article);
        } else {
            alert('記事生成に失敗しました: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('記事生成中にエラーが発生しました');
    });
}

// 生成された記事を表示
function displayGeneratedArticle(article) {
    const editor = document.getElementById('article-editor');
    const message = document.getElementById('article-generation-message');
    const actions = document.getElementById('editor-actions');
    
    editor.value = article;
    editor.disabled = false;
    message.style.display = 'none';
    actions.style.display = 'block';
}

// ペイン入れ替え
function swapPanes() {
    const container = document.getElementById('pane-container');
    const leftPane = document.getElementById('left-pane');
    const rightPane = document.getElementById('right-pane');
    
    if (!isSwapped) {
        container.appendChild(rightPane);
        container.appendChild(leftPane);
        isSwapped = true;
    } else {
        container.appendChild(leftPane);
        container.appendChild(rightPane);
        isSwapped = false;
    }
}

// 下書き保存
function saveDraft() {
    const form = document.getElementById('experience-form');
    const formData = new FormData(form);
    const articleContent = document.getElementById('article-editor').value;
    
    formData.append('action', 'save_draft');
    formData.append('article_content', articleContent);

    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showDraftInfo('下書きを保存しました (' + new Date().toLocaleString() + ')');
        } else {
            alert('下書き保存に失敗しました');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('下書き保存中にエラーが発生しました');
    });
}

// 下書き読み込み
function loadDraft() {
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=load_draft'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadDraftData(data.draft);
            showDraftInfo('下書きを読み込みました (保存日時: ' + data.saved_at + ')');
        } else {
            alert('読み込める下書きがありません');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('下書き読み込み中にエラーが発生しました');
    });
}

// 下書きデータをフォームに設定
function loadDraftData(draft) {
    const form = document.getElementById('experience-form');
    
    // 各フィールドに値を設定
    if (draft.ai_service_id) {
        form.ai_service_id.value = draft.ai_service_id;
        loadAISummary(draft.ai_service_id);
    }
    if (draft.usage_purpose) form.usage_purpose.value = draft.usage_purpose;
    if (draft.version_used) form.version_used.value = draft.version_used;
    if (draft.satisfaction) {
        form.satisfaction.value = draft.satisfaction;
        updateStarDisplay(parseInt(draft.satisfaction));
    }
    if (draft.notes) form.notes.value = draft.notes;
    
    // チェックボックスの設定
    if (draft.features_used) {
        draft.features_used.forEach(featureId => {
            const checkbox = form.querySelector(`input[name="features_used[]"][value="${featureId}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }
    
    // 記事内容の設定
    if (draft.article_content) {
        document.getElementById('article-editor').value = draft.article_content;
        document.getElementById('article-editor').disabled = false;
        document.getElementById('article-generation-message').style.display = 'none';
        document.getElementById('editor-actions').style.display = 'block';
    }
}

// 下書き情報表示
function showDraftInfo(message) {
    const infoDiv = document.getElementById('draft-info');
    const messageSpan = document.getElementById('draft-message');
    
    messageSpan.textContent = message;
    infoDiv.style.display = 'block';
    
    // 3秒後に非表示
    setTimeout(() => {
        infoDiv.style.display = 'none';
    }, 3000);
}

// 画像追加（プレースホルダー）
function addImage() {
    alert('画像追加機能は今後実装予定です');
}

// AIレビュー機能の実装
function reviewWithAI() {
    const aiServiceSelect = document.getElementById('ai_service_id');
    const articleContent = document.getElementById('article-editor').value;
    
    // AIサービスが選択されているかチェック
    if (!aiServiceSelect.value) {
        alert('左ペインでAIサービスを選択してください。');
        return;
    }
    
    // 記事内容があるかチェック
    if (!articleContent.trim()) {
        alert('記事内容が入力されていません。');
        return;
    }
    
    // AIサービス情報を取得
    getAIServiceInfo(aiServiceSelect.value, articleContent);
}
// AIサービス情報取得
function getAIServiceInfo(aiServiceId, articleContent) {
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_ai_service_info&ai_service_id=${aiServiceId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAIReviewDialog(data.aiService, articleContent);
        } else {
            alert('AIサービス情報の取得に失敗しました');
        }
    })
    .catch(error => {
        alert('エラーが発生しました');
    });
}
// AIレビューダイアログ表示
function showAIReviewDialog(aiService, articleContent) {
    const prompt = `AI体験をもとにこのような記事を書いてみました。多くの方に読んでもらえるように、題名、初めに、本文、終わりにの項目名や文章の提案と見直しをお願いします。　以下、AI体験記事作成ページの右側に集まった情報：

${articleContent}`;

    const modal = `
        <div class="modal fade" id="aiReviewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-robot"></i> AIレビュー</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <h6>使用するAI</h6>
                            <div class="d-flex align-items-center mb-2">
                                <strong>${aiService.ai_service}</strong>
                                <span class="badge bg-secondary ms-2">${aiService.company_name}</span>
                            </div>
                            <a href="${aiService.launch_url.replace('{prompt}', encodeURIComponent(prompt))}" 
                               target="_blank" class="btn btn-primary">
                                <i class="fas fa-external-link-alt"></i> ${aiService.ai_service}を開く
                            </a>
                        </div>
                        
                        <div class="mb-3">
                            <h6>コピー用プロンプト</h6>
                            <textarea id="promptText" class="form-control" rows="8" readonly>${prompt}</textarea>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="copyPrompt()">
                                <i class="fas fa-copy"></i> プロンプトをコピー
                            </button>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            上記のリンクをクリックして${aiService.ai_service}を開き、プロンプトをコピーして貼り付けてください。
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // 既存のモーダルがあれば削除
    const existingModal = document.getElementById('aiReviewModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // モーダルをページに追加
    document.body.insertAdjacentHTML('beforeend', modal);
    
    // モーダルを表示
    const modalElement = new bootstrap.Modal(document.getElementById('aiReviewModal'));
    modalElement.show();
}
// プロンプトコピー機能
function copyPrompt() {
    const promptText = document.getElementById('promptText');
    promptText.select();
    document.execCommand('copy');
    
    // コピー完了メッセージ
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> コピー完了';
    button.classList.add('btn-success');
    button.classList.remove('btn-outline-secondary');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}
// 編集完了時に記事内容をセッションに保存
function proceedToEdit() {
    const articleContent = document.getElementById('article-editor').value;
    
    // セッションに保存
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=save_article_for_posting&article_content=' + encodeURIComponent(articleContent)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (confirm('記事編集を完了して次のステップに進みますか？\n\n次のステップでは：\n• noteやQiitaなどの投稿プラットフォームを選択\n• 作成した記事を自動的にコピー\n• 投稿用のリンクを提供\n• 投稿後のURL登録')) {
                window.location.href = '?step=2';
            }
        }
    });
}
</script>

<?php
// 記事テンプレート生成関数
function generateArticleTemplate($aiService, $formData, $selectedCategories) {
    $versionText = [
        'free' => '無料版',
        'paid' => '有料版', 
        'enterprise' => '企業版'
    ][$formData['version_used']] ?? '';
    
    $satisfactionText = [
        '1' => '⭐ 不満',
        '2' => '⭐⭐ やや不満',
        '3' => '⭐⭐⭐ 普通',
        '4' => '⭐⭐⭐⭐ 満足',
        '5' => '⭐⭐⭐⭐⭐ 非常に満足'
    ][$formData['satisfaction']] ?? '';
    
    $categoriesText = !empty($selectedCategories) ? implode('、', $selectedCategories) : '特になし';
    
    $template = "# " . htmlspecialchars($aiService['ai_service']) . "を使ってみた体験レポート\n\n";
    $template .= "## 使用したAI\n";
    $template .= "**" . htmlspecialchars($aiService['ai_service']) . "**（" . htmlspecialchars($aiService['company_name']) . "）\n\n";
    $template .= htmlspecialchars($aiService['description']) . "\n\n";
    
    $template .= "## 使用目的\n";
    $template .= htmlspecialchars($formData['usage_purpose']) . "\n\n";
    
    $template .= "## 使用した機能・用途\n";
    $template .= $categoriesText . "\n\n";
    
    $template .= "## 使用した版\n";
    $template .= $versionText . "\n\n";
    
    $template .= "## 体験・感想\n";
    if (!empty($formData['notes'])) {
        $template .= htmlspecialchars($formData['notes']) . "\n\n";
    } else {
        $template .= "（ここに詳しい体験談や感想を記入してください）\n\n";
    }
    
    $template .= "## 総合評価\n";
    $template .= $satisfactionText . "\n\n";
    
    $template .= "## まとめ\n";
    $template .= "（ここに総括や他の人へのおすすめポイントを記入してください）\n\n";
    
    $template .= "---\n";
    $template .= "*この記事は実際の体験に基づいて作成されています。*";
    
    return $template;
}

include 'includes/footer.php';
?>
