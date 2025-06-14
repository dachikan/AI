<?php
// AJAX リクエスト処理（最初に配置）
if (isset($_POST['ajax_fetch_title']) && !empty($_POST['url'])) {
    header('Content-Type: application/json');
    
    $url = trim($_POST['url']);
    
    // URL検証
    if (!filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'note.com') === false) {
        echo json_encode(['success' => false, 'error' => 'Invalid note URL']);
        exit;
    }
    
    // タイトル取得関数
    function fetchNoteTitleAjax($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (compatible; TitleFetcher/1.0)'
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html === false) return '';

        // og:title優先
        if (preg_match('/<meta property="og:title" content="([^"]+)"/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        
        // titleタグ
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            // noteの場合、「｜note」を除去
            $title = preg_replace('/\s*[｜|]\s*note\s*$/i', '', $title);
            return trim($title);
        }
        
        return '';
    }
    
    $title = fetchNoteTitleAjax($url);
    
    if (!empty($title)) {
        echo json_encode(['success' => true, 'title' => $title]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Title not found']);
    }
    exit;
}

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

// セッション開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';

$pageTitle = '既存記事登録フロー';

// フォーム処理
$message = '';
$error = '';
$urlCheckResult = null;
$existingRegistrations = [];

// URL重複チェック機能
if (isset($_POST['check_url']) && !empty($_POST['note_url'])) {
    $noteUrl = trim($_POST['note_url']);
    
    if (filter_var($noteUrl, FILTER_VALIDATE_URL) && strpos($noteUrl, 'note.com') !== false) {
        try {
            // 既存登録をチェック
            $stmt = $conn->prepare("
                SELECT a.*, ai.ai_service 
                FROM ai_articles a 
                JOIN AIInfo ai ON a.ai_service_id = ai.id 
                WHERE a.url = ?
            ");
            $stmt->bind_param("s", $noteUrl);
            $stmt->execute();
            $result = $stmt->get_result();

            $existingRegistrations = [];
            while ($row = $result->fetch_assoc()) {
                $existingRegistrations[] = $row;
            }
            
            if (count($existingRegistrations) > 0) {
                $urlCheckResult = 'exists';
                $message = '既に登録済みの記事です。AIサービスを追加できます。';
            } else {
                $urlCheckResult = 'new';
                $message = '未登録の記事です。新規登録できます。';
            }
        } catch (Exception $e) {
            $error = 'URL確認中にエラーが発生しました: ' . $e->getMessage();
        }
    } else {
        $error = '有効なnote記事URLを入力してください。';
    }
}

// 記事登録処理
if (isset($_POST['register_article'])) {
    $noteUrl = trim($_POST['note_url']);
    $noteTitle = trim($_POST['note_title'] ?? '');
    $noteUsername = trim($_POST['note_username']);
    $email = trim($_POST['email']);
    $aiServiceId = intval($_POST['ai_service_id']);
    
    if (empty($noteUrl) || empty($noteUsername) || empty($aiServiceId)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $conn->begin_transaction();
            
            // ユーザーを取得または作成
            $stmt = $conn->prepare("SELECT id FROM ai_users WHERE note_username = ?");
            $stmt->bind_param("s", $noteUsername);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!$user) {
                $stmt = $conn->prepare("
                    INSERT INTO ai_users (note_username, email, auth_type) 
                    VALUES (?, ?, 'note_auth')
                ");
                $stmt->bind_param("ss", $noteUsername, $email);
                $stmt->execute();
                $userId = $conn->insert_id;
            } else {
                $userId = $user['id'];
            }
            
            // 重複チェック
            $stmt = $conn->prepare("
                SELECT id FROM ai_articles 
                WHERE url = ? AND ai_service_id = ?
            ");
            $stmt->bind_param("si", $noteUrl, $aiServiceId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->fetch_assoc()) {
                $error = 'この記事は既に同じAIサービスで登録済みです。';
            } else {
                // 記事を登録
                $stmt = $conn->prepare("
                    INSERT INTO ai_articles (user_id, ai_service_id, url, title, article_type, status) 
                    VALUES (?, ?, ?, ?, 'existing_post', 'verified')
                ");
                $stmt->bind_param("iiss", $userId, $aiServiceId, $noteUrl, $noteTitle);
                $stmt->execute();
                
                $conn->commit();
                $message = '記事が正常に登録されました！';
                
                // フォームをリセット
                $_POST = [];
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = '登録中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}

// AIサービス一覧を取得
try {
    $stmt = $conn->query("SELECT id, ai_service FROM AIInfo ORDER BY ai_service");
    $aiServices = [];
    while ($row = $stmt->fetch_assoc()) {
        $aiServices[] = $row;
    }
} catch (Exception $e) {
    $aiServices = [];
    $error = 'AIサービス一覧の取得に失敗しました: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-link"></i> 既存記事登録フロー</h2>
                    <p class="mb-0 text-muted">既に書いたAI体験記事を登録してください</p>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <!-- URL確認フォーム -->
                    <form method="POST" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label">note記事URL <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="url" class="form-control" name="note_url" required
                                       placeholder="https://note.com/username/n/article_id"
                                       value="<?= htmlspecialchars($_POST['note_url'] ?? '') ?>">
                                <button type="submit" name="check_url" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> 確認
                                </button>
                            </div>
                            <div class="form-text">まず記事URLを入力して、登録状況を確認してください</div>
                        </div>
                    </form>

                    <?php if ($urlCheckResult): ?>
                        <!-- 既存登録がある場合 -->
                        <?php if ($urlCheckResult === 'exists'): ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle"></i> 登録済みの記事です</h5>
                                <p class="mb-2">この記事は以下のAIサービスで既に登録されています：</p>
                                <ul class="mb-3">
                                    <?php foreach ($existingRegistrations as $reg): ?>
                                        <li><?= htmlspecialchars($reg['ai_service']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="d-grid gap-2">
                                    <a href="ai_experience_existing_multi.php?url=<?= urlencode($_POST['note_url']) ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-plus"></i> AIサービスを追加登録
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 新規登録の場合 -->
                        <?php if ($urlCheckResult === 'new'): ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle"></i> 新規登録可能です</h5>
                                <p class="mb-0">この記事は未登録です。下記フォームから登録してください。</p>
                            </div>

                            <!-- 新規登録フォーム -->
                            <form method="POST">
                                <input type="hidden" name="note_url" value="<?= htmlspecialchars($_POST['note_url']) ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">記事で扱っているAIサービス <span class="text-danger">*</span></label>
                                    <select class="form-select" name="ai_service_id" required>
                                        <option value="">選択してください</option>
                                        <?php foreach ($aiServices as $service): ?>
                                            <option value="<?= $service['id'] ?>">
                                                <?= htmlspecialchars($service['ai_service']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">記事で主に扱っているAIサービスを1つ選択してください</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">記事タイトル</label>
                                    <input type="text" class="form-control" name="note_title" id="note_title"
                                           placeholder="URLから自動取得されます..."
                                           value="<?= htmlspecialchars($_POST['note_title'] ?? '') ?>">
                                    <div class="form-text">
                                        <i class="fas fa-magic"></i> note記事のタイトルを自動取得します。編集も可能です。
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">noteユーザー名 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="note_username" required
                                           placeholder="your_username"
                                           value="<?= htmlspecialchars($_POST['note_username'] ?? '') ?>">
                                    <div class="form-text">noteのユーザー名（@マークなし）</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">メールアドレス</label>
                                    <input type="email" class="form-control" name="email"
                                           placeholder="your@email.com"
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    <div class="form-text">認証用（後日メール認証を実装予定）</div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>記事の要件:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>選択したAIサービスの実際の使用体験が記載されていること</li>
                                        <li>具体的な使用目的や感想が含まれていること</li>
                                        <li>公開記事であること（限定公開は不可）</li>
                                    </ul>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="register_article" class="btn btn-primary">
                                        <i class="fas fa-save"></i> 記事を登録
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="mt-4">
                        <div class="d-grid gap-2">
                            <a href="ai_experience_auth.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> メインページに戻る
                            </a>
                            <a href="registered_urls.php" class="btn btn-outline-info">
                                <i class="fas fa-list"></i> 登録済み記事一覧を見る
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlInput = document.querySelector('input[name="note_url"]');
    let timeoutId;
    
    // URL入力時の処理
    if (urlInput) {
        urlInput.addEventListener('input', function() {
            const url = this.value.trim();
            
            clearTimeout(timeoutId);
            
            // 500ms後に実行
            timeoutId = setTimeout(() => {
                if (url && url.includes('note.com')) {
                    // タイトル欄が存在する場合のみ取得
                    const titleInput = document.querySelector('input[name="note_title"]');
                    if (titleInput) {
                        fetchTitleFromUrl(url, titleInput);
                    }
                }
            }, 500);
        });
    }
    
    // フォーム送信後にタイトル欄が表示された場合の処理
    const titleInput = document.querySelector('input[name="note_title"]');
    if (titleInput && urlInput && urlInput.value) {
        const url = urlInput.value.trim();
        if (url && url.includes('note.com') && !titleInput.value) {
            fetchTitleFromUrl(url, titleInput);
        }
    }
});

// タイトル取得関数
function fetchTitleFromUrl(url, titleInput) {
    if (!titleInput) return;
    
    console.log('Fetching title for URL:', url); // デバッグ用
    
    // ローディング表示
    const originalPlaceholder = titleInput.placeholder;
    titleInput.placeholder = 'タイトル取得中...';
    titleInput.style.backgroundColor = '#f8f9fa';
    
    // 同じファイルにAJAXリクエスト
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_fetch_title=1&url=' + encodeURIComponent(url)
    })
    .then(response => {
        console.log('Response status:', response.status); // デバッグ用
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data); // デバッグ用
        titleInput.style.backgroundColor = '';
        if (data.success && data.title) {
            titleInput.value = data.title;
            titleInput.placeholder = originalPlaceholder;
        } else {
            titleInput.placeholder = 'タイトルを手動で入力してください';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        titleInput.style.backgroundColor = '';
        titleInput.placeholder = 'タイトル取得に失敗しました';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
