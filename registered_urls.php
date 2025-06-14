<?php
require_once 'includes/db_connect.php';

$pageTitle = '登録済みURL一覧';

// 検索機能
$search = $_GET['search'] ?? '';
$searchCondition = '';
$searchParam = [];

if (!empty($search)) {
    $searchCondition = " AND (a.url LIKE ? OR a.title LIKE ? OR u.note_username LIKE ?)";
    $searchParam = ["%$search%", "%$search%", "%$search%"];
}

// 登録済みURL一覧を取得
$sql = "SELECT a.url, a.title, a.created_at, u.note_username, 
               GROUP_CONCAT(ai.ai_service ORDER BY ai.ai_service SEPARATOR ', ') as ai_services,
               COUNT(a.id) as service_count
        FROM ai_articles a 
        JOIN ai_users u ON a.user_id = u.id 
        LEFT JOIN AIInfo ai ON a.ai_service_id = ai.id 
        WHERE 1=1 $searchCondition
        GROUP BY a.url, a.title, a.created_at, u.note_username
        ORDER BY a.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($searchParam)) {
    $stmt->bind_param("sss", ...$searchParam);
}
$stmt->execute();
$result = $stmt->get_result();
$articles = $result->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> 登録済みURL一覧</h2>
                    <p class="mb-0 text-muted">システムに登録されているnote記事の一覧です</p>
                </div>
                <div class="card-body">
                    <!-- 検索フォーム -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="URL、タイトル、ユーザー名で検索..."
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">検索</button>
                                <a href="registered_urls.php" class="btn btn-outline-secondary">クリア</a>
                            </div>
                        </div>
                    </form>

                    <!-- 統計情報 -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">総記事数</h5>
                                    <h3 class="text-primary"><?= count($articles) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">総登録エントリ数</h5>
                                    <h3 class="text-success">
                                        <?php
                                        $totalEntries = 0;
                                        foreach ($articles as $article) {
                                            $totalEntries += $article['service_count'];
                                        }
                                        echo $totalEntries;
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">平均AI数/記事</h5>
                                    <h3 class="text-info">
                                        <?= count($articles) > 0 ? number_format($totalEntries / count($articles), 1) : 0 ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 記事一覧 -->
                    <?php if (empty($articles)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> 
                            <?= !empty($search) ? '検索条件に一致する記事が見つかりませんでした。' : 'まだ記事が登録されていません。' ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>記事URL</th>
                                        <th>タイトル</th>
                                        <th>投稿者</th>
                                        <th>対象AIサービス</th>
                                        <th>登録日</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($articles as $article): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= htmlspecialchars($article['url']) ?>" target="_blank" class="text-decoration-none">
                                                    <i class="fas fa-external-link-alt"></i>
                                                    <?= htmlspecialchars(substr($article['url'], 0, 50)) ?>...
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($article['title']) ?></td>
                                            <td>
                                                <a href="https://note.com/<?= htmlspecialchars($article['note_username']) ?>" target="_blank">
                                                    @<?= htmlspecialchars($article['note_username']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary me-1"><?= $article['service_count'] ?>個</span>
                                                <?= htmlspecialchars($article['ai_services']) ?>
                                            </td>
                                            <td><?= date('Y/m/d', strtotime($article['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="ai_experience_existing_multi.php" class="btn btn-success">新しい記事を登録</a>
                        <a href="ai_experience_auth.php" class="btn btn-outline-secondary">メインページに戻る</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
