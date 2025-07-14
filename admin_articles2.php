<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once("includes/db_connect.php");

// セッション管理・ログイン処理
session_start();
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$loginError = '';
if (isset($_POST['admin_login'])) {
    $password = $_POST['admin_password'] ?? '';
    if ($password === 'admin123') {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_login_time'] = time();
        $isAdmin = true;
    } else {
        $loginError = "パスワードが間違っています";
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    unset($_SESSION['admin_login_time']);
    $isAdmin = false;
    header("Location: admin_note_articles.php");
    exit;
}

// 並べ替え（デフォルトtitle）
$order = $_GET['order'] ?? 'title';
$order_sql = $order === 'ai_service' ? 'ai_service' : 'title';

// 記事登録処理（note記事URLから情報取得→DB登録）
if ($isAdmin && isset($_POST['fetch_register_article'])) {
    $note_url = trim($_POST['note_url'] ?? '');
    $ai_service_id = intval($_POST['ai_service_id'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    if ($note_url && $ai_service_id) {
        require_once("includes/note_article_fetch.php");
        $info = fetch_note_article_info($note_url);
        if ($info && isset($info['user_id'])) {
            $articleId = saveArticle(
                $info['user_id'],
                $ai_service_id,
                $info['title'],
                $note_url,
                $info['summary'],
                $info['thumbnail_url'],
                $category_id
            );
            $registerResult = $articleId ? "記事を登録しました" : "記事登録に失敗しました";
        } else {
            $registerResult = "記事情報の取得に失敗しました";
        }
    } else {
        $registerResult = "URLとAIサービスを入力してください";
    }
}

// サムネイル画像URL登録
if ($isAdmin && isset($_POST['set_thumbnail'])) {
    $article_id = intval($_POST['article_id']);
    $thumbnail_url = trim($_POST['thumbnail_url']);
    if ($thumbnail_url && $article_id) {
        $stmt = $conn->prepare("UPDATE ai_articles SET thumbnail_url=? WHERE id=?");
        $stmt->bind_param("si", $thumbnail_url, $article_id);
        $stmt->execute();
    }
}

// avatar画像URL登録
if ($isAdmin && isset($_POST['set_avatar'])) {
    $user_id = intval($_POST['user_id']);
    $avatar_url = trim($_POST['avatar_url']);
    if ($avatar_url && $user_id) {
        $stmt = $conn->prepare("UPDATE ai_users SET avatar_url=? WHERE id=?");
        $stmt->bind_param("si", $avatar_url, $user_id);
        $stmt->execute();
    }
}

// AIサービス変更
if ($isAdmin && isset($_POST['update_ai_service'])) {
    $article_id = intval($_POST['article_id']);
    $ai_service_id = intval($_POST['ai_service_id']);
    if ($article_id && $ai_service_id) {
        $stmt = $conn->prepare("UPDATE ai_articles SET ai_service_id=? WHERE id=?");
        $stmt->bind_param("ii", $ai_service_id, $article_id);
        $stmt->execute();
    }
}

// 確認状態更新
if ($isAdmin && isset($_POST['update_verified'])) {
    $article_id = intval($_POST['article_id']);
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;
    $stmt = $conn->prepare("UPDATE ai_articles SET is_verified=? WHERE id=?");
    $stmt->bind_param("ii", $is_verified, $article_id);
    $stmt->execute();
}

// 管理者メモ保存
if ($isAdmin && isset($_POST['save_notes'])) {
    $article_id = intval($_POST['article_id']);
    $admin_notes = trim($_POST['admin_notes']);
    $stmt = $conn->prepare("UPDATE ai_articles SET admin_notes=? WHERE id=?");
    $stmt->bind_param("si", $admin_notes, $article_id);
    $stmt->execute();
}

// note記事表示でview_countインクリメント
if ($isAdmin && isset($_POST['view_note'])) {
    $article_id = intval($_POST['article_id']);
    $url = $_POST['url'] ?? '';
    if ($article_id) {
        $stmt = $conn->prepare("UPDATE ai_articles SET view_count = IFNULL(view_count,0)+1 WHERE id=?");
        $stmt->bind_param("i", $article_id);
        $stmt->execute();
    }
    if ($url) {
        header("Location: ".$url);
        exit;
    }
}

// 並べ替え取得
$ai_services = getAIServices();
$categories = getPromptCategories();
$sql = "SELECT a.*, ai.ai_service, c.name as category_name, u.note_username, u.avatar_url 
        FROM ai_articles a
        LEFT JOIN AIInfo ai ON a.ai_service_id=ai.id
        LEFT JOIN ai_users u ON a.user_id=u.id
        LEFT JOIN AIPromptCategories c ON a.category_id=c.id
        ORDER BY $order_sql ASC";
$articles = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

?>
<?php
// ...（冒頭のロジック・DB処理・認証処理はそのまま残してください）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者向けnote記事管理ツール</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .avatar-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .card-title { font-size: 1.1rem; font-weight: bold; }
        .card-user { font-size: 0.95rem; color: #666; margin-left: 8px; }
        .card { min-height: 420px; }
        .thumbnail-top-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            object-position: center top;
            border-radius: 8px 8px 0 0;
            background: #f5f5f5;
            display: block;
        }
        @media (max-width: 576px) { .card { min-height: unset; } }
    </style>
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">管理者向けnote記事管理ツール</h1>
    <?php if (!$isAdmin): ?>
    <!-- ログインフォーム（省略） -->
    <?php else: ?>
    <!-- 記事登録フォーム -->
    <div class="p-3 mb-4">
        <form method="POST">
            <input type="hidden" name="fetch_register_article" value="1">
            <div class="row align-items-end">
                <div class="col-md-5 mb-2 mb-md-0">
                    <label class="form-label mb-1">note記事URL</label>
                    <input type="url" name="note_url" class="form-control" placeholder="https://note.com/..." required>
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <label class="form-label mb-1">AIサービス</label>
                    <select name="ai_service_id" class="form-select" required>
                        <option value="">AIサービス選択</option>
                        <?php foreach($ai_services as $ai): ?>
                            <option value="<?= $ai['id'] ?>"><?= htmlspecialchars($ai['ai_service']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-2 mb-md-0">
                    <label class="form-label mb-1">カテゴリ</label>
                    <select name="category_id" class="form-select">
                        <option value="">カテゴリ選択（任意）</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">記事情報を取得して登録</button>
                </div>
            </div>
        </form>
        <?php if (!empty($registerResult)): ?>
            <div class="mt-2 alert alert-info"><?= htmlspecialchars($registerResult) ?></div>
        <?php endif; ?>
    </div>
    <!-- 並べ替え（省略） -->

    <!-- カード形式の記事一覧 -->
    <div class="row">
    <?php foreach($articles as $a): ?>
      <div class="col-md-6 col-lg-4 mb-4">
        <div class="card shadow-sm <?= $a['is_verified'] ? 'border-success' : 'border-warning' ?>">
          <?php if ($a['thumbnail_url']): ?>
            <img src="<?= htmlspecialchars($a['thumbnail_url']) ?>" class="thumbnail-top-img" alt="サムネイル">
          <?php else: ?>
            <div class="bg-light d-flex align-items-center justify-content-center" style="width:100%;height:200px;">
              <form method="POST" class="w-100 px-2">
                <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                <input type="text" name="thumbnail_url" class="form-control form-control-sm mb-2" placeholder="画像URL">
                <button name="set_thumbnail" class="btn btn-outline-secondary btn-sm w-100">画像url入力</button>
              </form>
            </div>
          <?php endif; ?>
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <small>ID: <?= $a['id'] ?></small>
            <?= $a['is_verified'] ?
                '<span class="badge bg-success">確認済み</span>' :
                '<span class="badge bg-warning text-dark">未確認</span>'; ?>
          </div>
          <div class="card-body pb-2">
            <div class="d-flex align-items-center mb-2">
              <?php if ($a['avatar_url']): ?>
                <img src="<?= htmlspecialchars($a['avatar_url']) ?>" class="avatar-img me-2">
              <?php else: ?>
                <form method="POST" class="d-flex flex-row me-2">
                  <input type="hidden" name="user_id" value="<?= $a['user_id'] ?>">
                  <input type="text" name="avatar_url" class="form-control form-control-sm" placeholder="avatar画像URL" style="max-width:110px;">
                  <button name="set_avatar" class="btn btn-outline-secondary btn-sm ms-1">画像url入力</button>
                </form>
              <?php endif; ?>
              <div>
                <span class="card-title"><?= htmlspecialchars($a['title']) ?></span>
                <?php if ($a['note_username']): ?>
                  <span class="card-user">| <?= htmlspecialchars($a['note_username']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="mb-1">
              <form method="POST" class="mb-0">
                <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                <select name="ai_service_id" class="form-select form-select-sm d-inline" style="width:auto;display:inline-block;" onchange="this.form.submit()">
                  <option value="">AIサービス選択</option>
                  <?php foreach($ai_services as $ai): ?>
                    <option value="<?= $ai['id'] ?>" <?= $ai['id']==$a['ai_service_id']?'selected':'' ?>><?= htmlspecialchars($ai['ai_service']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="update_ai_service" style="display:none"></button>
                <?php if (empty($a['ai_service_id'])): ?>
                  <span class="text-danger ms-2">AIサービス未設定！</span>
                <?php endif; ?>
              </form>
            </div>
            <div>
              <span class="badge bg-secondary"><?= htmlspecialchars($a['category_name'] ?? '') ?></span>
            </div>
            <div class="mt-2" style="font-size:0.95em; min-height:45px;">
              <?= htmlspecialchars($a['summary']) ?>
            </div>
            <div class="mt-3 d-flex flex-wrap gap-2">
              <form method="POST" target="_blank" class="mb-0">
                <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                <input type="hidden" name="url" value="<?= htmlspecialchars($a['url']) ?>">
                <button type="submit" name="view_note" class="btn btn-outline-primary btn-sm">note記事表示</button>
              </form>
              <form method="POST" class="mb-0">
                <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                <input type="checkbox" name="is_verified" value="1" <?= $a['is_verified']?'checked':'' ?> onchange="this.form.submit()">
                <button type="submit" name="update_verified" style="display:none;"></button>
                <span class="ms-1">確認状態</span>
              </form>
              <div>閲覧数: <?= (int)($a['view_count'] ?? 0) ?></div>
            </div>
            <form method="POST" class="mt-2">
              <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
              <label class="form-label mb-1" style="font-size:0.95em;">管理者メモ</label>
              <textarea name="admin_notes" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($a['admin_notes']) ?></textarea>
              <button type="submit" name="save_notes" class="btn btn-outline-success btn-sm mt-1">保存</button>
            </form>
          </div><!-- card-body -->
        </div><!-- card -->
      </div><!-- col -->
    <?php endforeach; ?>
    </div><!-- row -->
    <?php if (empty($articles)): ?>
      <div class="alert alert-info mt-4">記事がありません。</div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>