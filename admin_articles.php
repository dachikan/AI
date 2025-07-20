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

// 記事タイプ更新処理
if ($isAdmin && isset($_POST['update_article_type'])) {
    $articleId = intval($_POST['article_id']);
    $articleType = $_POST['article_type'] ?? '';
    if ($articleId > 0 && $articleType !== '') {
        $updateStmt = $conn->prepare("UPDATE ai_articles SET article_type = ? WHERE id = ?");
        $updateStmt->bind_param("si", $articleType, $articleId);
        $updateStmt->execute();
        $updateStmt->close();
        $message = "記事ID {$articleId} の記事タイプを更新しました。";
    }
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
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#exampleModal">
                <i class="fas fa-table"></i> 新しいサイト
            </button>
            <a href="debug_portal.php" class="btn btn-outline-info btn-sm">
                <i class="fas fa-bug"></i> debug_portal
            </a>
            <a href="check-icons.php" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-check"></i> check-icon
            </a>
            <a href="?logout=1" class="btn btn-outline-secondary">
                <i class="fas fa-sign-out-alt"></i> ログアウト
            </a>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php
        // 管理対象の記事を取得
        $adminSql = "SELECT 
            a.id AS article_id, a.title, a.url, a.status, a.is_verified, a.created_at,
            a.thumbnail_url, a.admin_notes, a.summary,
            a.article_type,
            c.name as category_name,
            au.note_username, au.avatar_url
        FROM ai_articles a
        JOIN AIInfo ai ON a.ai_service_id = ai.id
        LEFT JOIN ai_users au ON a.user_id = au.id
        LEFT JOIN AIPromptCategories c ON a.category_id = c.id
        ORDER BY a.is_verified ASC, a.created_at DESC
        LIMIT 50";
        
        $adminArticles = $conn->query($adminSql)->fetch_all(MYSQLI_ASSOC);

        //foreach ($adminArticles as $article):
            // 削除処理
            if ($isAdmin && isset($_POST['delete_article'])) {
                $articleId = intval($_POST['article_id']);
                if ($articleId > 0) {
                    $delStmt = $conn->prepare("DELETE FROM ai_articles WHERE id = ?");
                    $delStmt->bind_param("i", $articleId);
                    $delStmt->execute();
                    $delStmt->close();
                    $message = "記事ID {$articleId} を削除しました。";
                }
            }
            // 記事タイプ更新処理
            if ($isAdmin && isset($_POST['update_article_type'])) {
                $articleId = intval($_POST['article_id']);
                $articleType = $_POST['article_type'] ?? '';
                if ($articleId > 0 && $articleType !== '') {
                    $updateStmt = $conn->prepare("UPDATE ai_articles SET article_type = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $articleType, $articleId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    $message = "記事ID {$articleId} の記事タイプを更新しました。";
                }
            }
        //endforeach;
        ?>
    <?php endif; ?>
</div>


<style>
    .card-ai-article {
    border: 2px solid #ffd966;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 24px;
    min-height: 480px;
    }
    .card-ai-article .thumbnail-wrapper {
    text-align: center;
    margin-bottom: 8px;
    }
    .card-ai-article .thumbnail-wrapper img {
    width: 100%;
    max-width: 360px;
    height: 200px;
    object-fit: cover;
    object-position: center top;
    border-radius: 8px;
    background: #eee;
    margin-left: auto;
    margin-right: auto;
    display: block;
    }
    .card-ai-article .card-title {
    font-weight: bold;
    font-size: 1.1em;
    }
    .card-ai-article textarea {
    min-height: 42px;
    }
    .card-ai-article .btn-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
    }
    .card-ai-article .btn-row .btn {
    min-width: 90px;
    }
    .card-ai-article .checkbox-label {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 0;
    }
    @media (max-width: 768px) {
        .card-ai-article .thumbnail-wrapper img {
            max-width: 100%;
            height: 160px;
        }
    }
</style>

<div class="row">
<?php foreach ($adminArticles as $article): ?>

  <div class="col-md-6">
    <div class="card card-ai-article p-3 mb-3">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <span><b>ID:</b> <?= $article['article_id'] ?></span>
        <?php if (!$article['is_verified']): ?>
          <span class="badge bg-warning text-dark">未確認</span>
        <?php else: ?>
          <span class="badge bg-success">確認済み</span>
        <?php endif; ?>
      </div>
      <div class="card-title mb-2"><?= htmlspecialchars($article['title'] ?: '無題') ?></div>
      <div class="thumbnail-wrapper mb-2">
        <?php if (!empty($article['thumbnail_url'])): ?>
            <a href="<?= htmlspecialchars($article['url']) ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                <img src="<?= htmlspecialchars($article['thumbnail_url']) ?>" alt="thumbnail">
            </a>
        <?php endif; ?>
        <?php if (!empty($article['avatar_url'])): ?>
          <img src="<?= htmlspecialchars($article['avatar_url']) ?>" alt="avatar" style="width:36px;height:36px;border-radius:50%;vertical-align:middle;">
        <?php else: ?>
          <span style="color:#ccc;">(avatarなし)</span>
        <?php endif; ?>
        <span class="ms-2"><?= htmlspecialchars($article['note_username']) ?></span>
      </div>
      <div class="mb-2 text-muted" style="font-size:0.95em;">
        <i class="fas fa-calendar"></i> <?= date('Y/m/d H:i', strtotime($article['created_at'])) ?>
        <?php if ($article['category_name']): ?>
          | <i class="fas fa-tag"></i> <?= htmlspecialchars($article['category_name']) ?>
        <?php endif; ?>
      </div>
      <div class="mb-2">
        <?= htmlspecialchars(mb_substr($article['summary'], 0, 160)) ?>
        <?php if (mb_strlen($article['summary']) > 160): ?>...<?php endif; ?>
      </div>
      
      <form method="POST" class="mb-2 d-flex align-items-center gap-2">
        <label class="form-label me-2 mb-0">記事タイプ</label>
        <select name="article_type" class="form-select form-select-sm w-auto me-2">
          <option value="">未設定</option>
          <option value="programming" <?= $article['article_type'] === 'programming' ? 'selected' : '' ?>>プログラミング</option>
          <option value="shop" <?= $article['article_type'] === 'shop' ? 'selected' : '' ?>>ショップ運営</option>
          <option value="economy" <?= $article['article_type'] === 'economy' ? 'selected' : '' ?>>経済情報</option>
          <option value="technical" <?= $article['article_type'] === 'technical' ? 'selected' : '' ?>>技術知識</option>
          <option value="scenario" <?= $article['article_type'] === 'scenario' ? 'selected' : '' ?>>シナリオ作り</option>
          <option value="writing" <?= $article['article_type'] === 'writing' ? 'selected' : '' ?>>執筆</option>
          <option value="profit" <?= $article['article_type'] === 'profit' ? 'selected' : '' ?>>収益化</option>
          <option value="drawing" <?= $article['article_type'] === 'drawing' ? 'selected' : '' ?>>作画・イラスト</option>
          <option value="office" <?= $article['article_type'] === 'office' ? 'selected' : '' ?>>オフィス</option>
          <option value="agriculture" <?= $article['article_type'] === 'agriculture' ? 'selected' : '' ?>>農業</option>
          <option value="warry" <?= $article['article_type'] === 'warry' ? 'selected' : '' ?>>悩み相談</option>
          <option value="what" <?= $article['article_type'] === 'what' ? 'selected' : '' ?>>AIとは</option>
        </select>
        <input type="hidden" name="article_id" value="<?= $article['article_id'] ?>">
        <button type="submit" name="update_article_type" class="btn btn-sm btn-secondary">記事タイプを変更する</button>
      </form>
      <div class="btn-row mb-2">
        <form method="POST" class="mb-0 d-inline">
          <input type="hidden" name="article_id" value="<?= $article['article_id'] ?>">
          <label class="checkbox-label">
            <input type="checkbox" name="is_verified" value="1" <?= $article['is_verified'] ? 'checked' : '' ?> onchange="this.form.submit()" title="確認状態">
            <span style="font-size:0.97em;">確認</span>
          </label>
        </form>
        <form method="POST" class="mb-0 d-inline">
          <input type="hidden" name="article_id" value="<?= $article['article_id'] ?>">
          <input type="hidden" name="admin_notes" value="<?= htmlspecialchars($article['admin_notes'] ?? '') ?>">
          <button type="submit" name="update_verification" class="btn btn-primary btn-sm">更新</button>
        </form>
        <form method="POST" class="mb-0 d-inline" onsubmit="return confirm('本当にこの記事を削除しますか？');">
          <input type="hidden" name="article_id" value="<?= $article['article_id'] ?>">
          <button type="submit" name="delete_article" class="btn btn-danger btn-sm">削除</button>
        </form>
      </div>
      <form method="POST" class="mt-2">
        <input type="hidden" name="article_id" value="<?= $article['article_id'] ?>">
        <label class="form-label mb-1" style="font-size:0.96em;">管理者メモ</label>
        <textarea name="admin_notes" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($article['admin_notes']) ?></textarea>
        <button type="submit" name="update_verification" class="btn btn-outline-success btn-sm mt-1">メモ保存</button>
      </form>
    </div>
  </div>
    <?php endforeach; ?>
</div>
<?php include 'includes/footer.php'; ?>

<?php ob_end_flush(); // ファイルの最後でバッファを出力 ?>
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
<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="container py-5">
                <div class="form-container shadow-sm">
                    <h1 class="text-center mb-4">既存記事を登録</h1>
                    <form action="note_article_info.php" method="POST">
                        <div class="mb-3">
                            <label for="article_url" class="form-label">Note記事URL</label>
                            <input type="url" class="form-control" id="article_url" name="url"
                                placeholder="https://note.com/ユーザid/n/記事id/"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="ai_service_id" class="form-label">AIサービスを選択</label>
                            <select class="form-select" id="ai_service_id" name="ai_service_id" required>
                                <?php
                                    $ai_services = getAIServices();
                                    foreach ($ai_services as $service): 
                                ?>
                                    <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['ai_service']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- 記事タイプ選択を追加 -->
                            <div class="mb-3">
                                <label for="modal_article_type" class="form-label">記事の種類</label>
                                <select class="form-select" id="modal_article_type" name="article_type" required>
                                    <option value="">選択してください</option>
                                    <?php 
                                    $articleTypes = getArticleTypes();
                                    foreach ($articleTypes as $value => $label): 
                                    ?>
                                        <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">記事情報を取得して保存</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>