<?php
require_once 'includes/db_connect.php';

// 簡単な管理者認証（実際の運用では適切な認証システムを使用）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// 管理者ログイン処理
if (isset($_POST['admin_login'])) {
    $password = $_POST['admin_password'] ?? '';
    // 実際の運用では適切なパスワードハッシュを使用
    if ($password === 'admin123') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    }
}

// ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    $isAdmin = false;
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

$pageTitle = '記事管理 - AI活用体験ポータル';
include 'includes/header.php';
?>

<div class="container mt-4">
    <h1><i class="fas fa-cog"></i> 記事管理</h1>
    
    <?php if (!$isAdmin): ?>
    <!-- 管理者ログイン -->
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
        a.thumbnail_url,
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
                    <?php if (!empty($article['thumbnail_url'])): ?>
                        <img src="<?= htmlspecialchars($article['thumbnail_url']) ?>" alt="thumbnail" style="width: 100px; object-fit: cover;">
                    <?php else: ?>
                        <span style="color: #ccc;">(thambnailなし)</span>
                    <?php endif; ?>
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
                                      placeholder="内部メモ（非公開）"><?= htmlspecialchars($article['admin_notes']) ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_verification" class="btn btn-sm btn-primary">
                            <i class="fas fa-save"></i> 更新
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="refetchData(<?= $article['id'] ?>)">
                            画像再取得
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
function refetchData(articleId) {
    const button = event.target;
    button.disabled = true;
    button.textContent = '取得中...';

    fetch('api_refetch_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ article_id: articleId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('データの再取得に成功しました。ページをリロードします。');
            location.reload();
        } else {
            alert('エラー: ' + data.message);
            button.disabled = false;
            button.textContent = '画像再取得';
        }
    })
    .catch(error => {
        alert('通信エラーが発生しました。');
        console.error('Error:', error);
        button.disabled = false;
        button.textContent = '画像再取得';
    });
}
</script>
<?php include 'includes/footer.php'; ?>
