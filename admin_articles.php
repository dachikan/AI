<?php
// JSONレスポンス処理部分を修正
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_thumbnail'])) {
    // 出力バッファを完全にクリア
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    require_once 'includes/db_connect.php';
    require_once 'includes/experience_functions.php';
    
    $response = ['success' => false, 'error' => '初期化エラー'];
    
    try {
        $url = $_POST['aurl'] ?? '';
        $articleId = intval($_POST['article_id'] ?? 0);
        
        if (empty($url)) {
            throw new Exception('URLが指定されていません');
        }
        
        if ($articleId <= 0) {
            throw new Exception('無効な記事IDです');
        }
        
        $result = analyzeNoteArticle($url);
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? '記事の解析に失敗しました');
        }
        
        // サムネイルURLをデータベースに保存
        $updateSql = "UPDATE ai_articles SET thumbnail_url = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $result['thumbnail_url'], $articleId);
        
        if (!$updateStmt->execute()) {
            throw new Exception('データベースの更新に失敗しました');
        }
        
        $response = [
            'success' => true,
            'url' => $url,
            'title' => $result['title'],
            'username' => $result['username'],
            'thumbnail_url' => $result['thumbnail_url'],
            'summary' => $result['summary']
        ];
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // ヘッダー設定
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // JSONレスポンス
    echo json_encode($response);
    exit;
}
// 通常の処理を続ける
require_once 'includes/db_connect.php';
// セッション設定を強化
ini_set('session.cookie_lifetime', 3600); // 1時間
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_secure', 0); // HTTPでも動作するように（本番では1に）
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// セッション固定攻撃対策
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// デバッグ用：セッション状態をログに記録
error_log("Admin Articles - Session Info: " . json_encode([
    'session_id' => session_id(),
    'is_admin' => $isAdmin,
    'session_data' => $_SESSION
]));

// 管理者ログイン処理
if (isset($_POST['admin_login'])) {
    $password = $_POST['admin_password'] ?? '';
    if ($password === 'admin123') {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_login_time'] = time();
        $isAdmin = true;
        
        // ログイン成功をログに記録
        error_log("Admin login successful - Session ID: " . session_id());
    } else {
        $loginError = "パスワードが間違っています";
    }
}

// ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    unset($_SESSION['admin_login_time']);
    $isAdmin = false;
    
    // ログアウトをログに記録
    error_log("Admin logout - Session ID: " . session_id());
}

// セッションタイムアウトチェック
if ($isAdmin && isset($_SESSION['admin_login_time'])) {
    if (time() - $_SESSION['admin_login_time'] > 3600) { // 1時間でタイムアウト
        unset($_SESSION['is_admin']);
        unset($_SESSION['admin_login_time']);
        $isAdmin = false;
        $sessionTimeout = true;
    }
}

// 記事の確認状態を更新
if ($isAdmin && isset($_POST['update_verification'])) {
    $articleId = intval($_POST['article_id']);
    $isVerified = intval($_POST['is_verified']);
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    $updateSql = "UPDATE ai_articles SET is_verified = ?, admin_notes = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("isi", $isVerified, $adminNotes, $articleId);
    $updateStmt->execute();
    
    $message = "記事の確認状態を更新しました。";
}

// 通常のHTML処理はここから続く
$pageTitle = '記事管理 - AI活用体験ポータル';
include 'includes/header.php';
?>

<div class="container mt-4">
    <h1><i class="fas fa-cog"></i> 記事管理</h1>
    
    <!-- セッションタイムアウト通知 -->
    <?php if (isset($sessionTimeout)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-clock"></i> セッションがタイムアウトしました。再度ログインしてください。
    </div>
    <?php endif; ?>
    
    <!-- ログインエラー通知 -->
    <?php if (isset($loginError)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($loginError) ?>
    </div>
    <?php endif; ?>
    
    <!-- デバッグ情報（開発時のみ表示） -->
    <?php if ($isAdmin): ?>
    <div class="alert alert-info">
        <small>
            <strong>セッション情報:</strong><br>
            セッションID: <?= session_id() ?><br>
            管理者状態: <?= $_SESSION['is_admin'] ? 'true' : 'false' ?><br>
            ログイン時刻: <?= isset($_SESSION['admin_login_time']) ? date('Y-m-d H:i:s', $_SESSION['admin_login_time']) : '不明' ?><br>
            現在時刻: <?= date('Y-m-d H:i:s') ?>
        </small>
    </div>
    <?php endif; ?>
    
    <?php if (!$isAdmin): ?>
    <!-- 管理者ログイン画面 -->
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>管理者ログイン</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">パスワード</label>
                            <input type="password" class="form-control" name="admin_password" required>
                        </div>
                        <button type="submit" name="admin_login" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> ログイン
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- 管理者画面 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="badge bg-success">管理者モード</span>
            <small class="text-muted">ログイン時刻: <?= date('Y/m/d H:i', $_SESSION['admin_login_time']) ?></small>
        </div>
        <a href="?logout=1" class="btn btn-outline-secondary">
            <i class="fas fa-sign-out-alt"></i> ログアウト
        </a>
    </div>
    
    <?php if (isset($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php
    // 管理対象の記事を取得
    $adminSql = "SELECT 
        a.id, a.title, a.url, a.status, a.is_verified, a.created_at,
        a.thumbnail_url, a.admin_notes, a.summary,
        ai.ai_service,
        c.name as category_name,
        au.note_username, au.avatar_url
    FROM ai_articles a
    JOIN AIInfo ai ON a.ai_service_id = ai.id
    LEFT JOIN ai_users au ON a.user_id = au.id
    LEFT JOIN AIPromptCategories c ON a.category_id = c.id
    WHERE a.status = 'verified'
    ORDER BY a.is_verified ASC, a.created_at DESC
    LIMIT 50";
    
    $adminArticles = $conn->query($adminSql)->fetch_all(MYSQLI_ASSOC);
    ?>
    
    <div class="row">
        <?php foreach ($adminArticles as $article): ?>
        <div class="col-md-6 mb-4">
            <div class="card <?= $article['is_verified'] ? 'border-success' : 'border-warning' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <small class="text-muted">ID: <?= $article['id'] ?></small>
                    <?php if ($article['is_verified']): ?>
                    <span class="badge bg-success">確認済み</span>
                    <?php else: ?>
                    <span class="badge bg-warning">未確認</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <h6 class="card-title">
                        <?= htmlspecialchars($article['title'] ?: '無題') ?>
                        <?php if (!empty($article['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($article['avatar_url']) ?>" alt="avatar" style="width: 40px; height: 40px; border-radius: 50%;">
                        <?php else: ?>
                            <span style="color: #ccc;">(avatarなし)</span>
                        <?php endif; ?>
                    </h6>
<!-- 画像表示部分 -->
<div class="thumbnail-wrapper">
    <?php if (!empty($article['thumbnail_url'])): ?>
        <img src="<?= htmlspecialchars($article['thumbnail_url']) ?>" 
             alt="thumbnail" 
             style="width: 100px; object-fit: cover;"
             id="thumbnail_img_<?= $article['id'] ?>"
             onerror="handleImageError(<?= $article['id'] ?>)">
    <?php else: ?>
        <span style="color: #ccc;" id="thumbnail_placeholder_<?= $article['id'] ?>">
            (thumbnailなし)
        </span>
        <img src="" alt="thumbnail" 
             style="width: 100px; object-fit: cover; display:none;"
             id="thumbnail_img_<?= $article['id'] ?>">
    <?php endif; ?>
</div>

<!-- 画像再取得ボタン -->
<form method="POST" class="d-inline" id="refreshForm_<?= $article['id'] ?>">
    <input type="hidden" name="article_id" value="<?= $article['id'] ?>">
    <input type="hidden" name="aurl" value="<?= htmlspecialchars($article['url']) ?>">
    <button type="submit" name="refresh_thumbnail" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-sync-alt"></i> 画像再取得
    </button>
</form>
                    <p class="card-text">
                        <small class="text-muted">
                            <i class="fas fa-robot"></i> <?= htmlspecialchars($article['ai_service']) ?>
                            <?php if ($article['category_name']): ?>
                            | <i class="fas fa-tag"></i> <?= htmlspecialchars($article['category_name']) ?>
                            <?php endif; ?>
                            <br>
                            <i class="fas fa-calendar"></i> <?= date('Y/m/d H:i', strtotime($article['created_at'])) ?>
                            <?php if ($article['note_username']): ?>
                            | <i class="fas fa-user"></i> <?= htmlspecialchars($article['note_username']) ?>
                            <?php endif; ?>
                        </small>
                    </p>
                    
                    <?php if ($article['summary']): ?>
                    <p class="card-text">
                        <?= htmlspecialchars(mb_substr($article['summary'], 0, 100)) ?>
                        <?php if (mb_strlen($article['summary']) > 100): ?>...<?php endif; ?>
                    </p>
                    <?php endif; ?>

                    <div class="mb-3">
                        <a href="<?= htmlspecialchars($article['url']) ?>" 
                            target="_blank" 
                            class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt"></i> note記事を確認
                        </a>
                    </div>

                    <!-- 確認状態更新フォーム -->
                    <form method="POST" class="border-top pt-3">
                        <input type="hidden" name="article_id" value="<?= $article['id'] ?>">
                        <div class="mb-2">
                            <label class="form-label">確認状態</label>
                            <select name="is_verified" class="form-select form-select-sm">
                                <option value="0" <?= !$article['is_verified'] ? 'selected' : '' ?>>未確認</option>
                                <option value="1" <?= $article['is_verified'] ? 'selected' : '' ?>>確認済み</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">管理者メモ</label>
                            <textarea name="admin_notes" class="form-control form-control-sm" rows="2" 
                                      placeholder="内部メモ（非公開）"><?= htmlspecialchars($article['admin_notes'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" name="update_verification" class="btn btn-sm btn-primary">
                            <i class="fas fa-save"></i> 更新
                        </button>
                    </form>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($adminArticles)): ?>
    <div class="text-center py-5">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
        <h4>管理対象の記事がありません</h4>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('[id^="refreshForm_"]').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const button = this.querySelector('button');
        const articleId = this.id.split('_')[1];
        const imgElement = document.getElementById(`thumbnail_img_${articleId}`);
        const placeholder = document.getElementById(`thumbnail_placeholder_${articleId}`);
        
        // ローディング状態
        button.disabled = true;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 処理中...';
        
        try {
            const response = await fetch('admin_articles.php', {
                method: 'POST',
                body: new FormData(this),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            // 生のレスポンスを取得してデバッグ
            const rawResponse = await response.text();
            console.log('Raw Response:', rawResponse);
            
            // JSONとしてパースを試みる
            let data;
            try {
                data = JSON.parse(rawResponse);
            } catch (e) {
                throw new Error(`JSON解析エラー: ${e.message}\nレスポンス: ${rawResponse.substring(0, 100)}...`);
            }
            
            if (!data.success) {
                throw new Error(data.error || 'リクエストに失敗しました');
            }
            
            // 画像更新処理
            if (data.thumbnail_url) {
                const img = imgElement || createImageElement(articleId, placeholder);
                img.src = data.thumbnail_url + '?t=' + new Date().getTime(); // キャッシュ回避
                img.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';
                
                // 成功メッセージ
                alert('サムネイルURLが更新されました: ' + data.thumbnail_url);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('エラーが発生しました: ' + error.message);
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    });
});

function createImageElement(articleId, placeholder) {
    const img = document.createElement('img');
    img.id = `thumbnail_img_${articleId}`;
    img.alt = 'thumbnail';
    img.style = 'width: 100px; object-fit: cover;';
    img.onerror = () => handleImageError(articleId);
    
    if (placeholder) {
        placeholder.insertAdjacentElement('beforebegin', img);
    }
    
    return img;
}

function handleImageError(articleId) {
    const img = document.getElementById(`thumbnail_img_${articleId}`);
    const placeholder = document.getElementById(`thumbnail_placeholder_${articleId}`);
    
    if (img) img.style.display = 'none';
    if (placeholder) placeholder.style.display = 'inline';
}
</script>
<?php include 'includes/footer.php'; ?>
<?php ob_end_flush(); // ファイルの最後でバッファを出力 ?>