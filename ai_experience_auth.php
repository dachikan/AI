<?php
require_once 'includes/db_connect.php';

$pageTitle = 'AI体験記認証システム';

// セッション開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// セッションIDを取得または生成
if (!isset($_SESSION['experience_session_id'])) {
    $_SESSION['experience_session_id'] = uniqid('exp_', true);
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-shield-alt"></i> AI体験記認証システム</h2>
                    <p class="mb-0 text-muted">あなたのAI体験を記事として登録しましょう</p>
                </div>
                <div class="card-body">
                    <!-- フロー選択 -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-edit fa-3x text-primary mb-3"></i>
                                    <h5>これから記事を書く</h5>
                                    <p class="text-muted">AI体験後にnote記事を作成</p>
                                    <a href="ai_experience_new.php" class="btn btn-primary">
                                        新規記事作成フロー
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-link fa-3x text-success mb-3"></i>
                                    <h5>既に記事を書いた</h5>
                                    <p class="text-muted">既存のnote記事を登録</p>
                                    <a href="ai_experience_existing.php" class="btn btn-success">
                                        既存記事登録フロー
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 体験記一覧 -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-list"></i> 認証済み体験記一覧</h5>
                        </div>
                        <div class="card-body">
                            <div id="articles-list">
                                <!-- 記事一覧はJavaScriptで動的に読み込み -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 記事一覧を読み込み
function loadArticles() {
    fetch('api/get_articles.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('articles-list');
            if (data.success && data.articles.length > 0) {
                container.innerHTML = data.articles.map(article => `
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6><a href="${article.url}" target="_blank">${article.title}</a></h6>
                                    <p class="text-muted small">${article.summary || '概要なし'}</p>
                                    <small class="text-muted">
                                        by ${article.note_username} | ${article.ai_service_name} | 
                                        ${new Date(article.created_at).toLocaleDateString()}
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <span class="badge ${article.article_type === 'new_post' ? 'bg-primary' : 'bg-success'}">
                                        ${article.article_type === 'new_post' ? '新規記事' : '既存記事'}
                                    </span>
                                    <br>
                                    <small class="text-muted">閲覧: ${article.view_count}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted text-center">まだ認証済みの記事がありません。</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('articles-list').innerHTML = '<p class="text-danger text-center">記事の読み込みに失敗しました。</p>';
        });
}

// ページ読み込み時に記事一覧を表示
document.addEventListener('DOMContentLoaded', loadArticles);
</script>

<?php include 'includes/footer.php'; ?>
