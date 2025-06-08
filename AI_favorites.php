<?php
require_once 'includes/db_connect.php';

$pageTitle = 'お気に入りAIサービス';

include 'includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4"><i class="fas fa-heart text-danger"></i> お気に入りAIサービス</h1>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        お気に入りに追加したAIサービスがここに表示されます。ブラウザのローカルストレージに保存されます。
    </div>

    <div id="favorites-container">
        <!-- JavaScriptで動的に生成 -->
    </div>

    <div id="no-favorites" class="text-center py-5" style="display: none;">
        <i class="fas fa-heart-broken fa-3x text-muted mb-3"></i>
        <h4>お気に入りがありません</h4>
        <p class="text-muted">AIサービスの詳細ページでハートマークをクリックしてお気に入りに追加してください。</p>
        <a href="AI_list.php" class="btn btn-primary">AIサービス一覧を見る</a>
    </div>
</div>

<script>
// お気に入り一覧を表示
function displayFavorites() {
    const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
    const container = document.getElementById('favorites-container');
    const noFavorites = document.getElementById('no-favorites');
    
    if (favorites.length === 0) {
        container.style.display = 'none';
        noFavorites.style.display = 'block';
        return;
    }
    
    container.style.display = 'block';
    noFavorites.style.display = 'none';
    
    // お気に入りサービスの詳細を取得して表示
    // 実際の実装では、Ajax等でサーバーからデータを取得
    container.innerHTML = '<div class="row" id="favorites-list"></div>';
    
    favorites.forEach(serviceId => {
        // ここでサービス詳細を取得して表示
        // 簡易実装として、ローカルストレージから基本情報を表示
        const serviceInfo = JSON.parse(localStorage.getItem('service_' + serviceId) || '{}');
        if (serviceInfo.name) {
            const card = `
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5>${serviceInfo.name}</h5>
                            <p class="text-muted">${serviceInfo.company || ''}</p>
                            <div class="d-flex justify-content-between">
                                <a href="AI_detail.php?id=${serviceId}" class="btn btn-primary btn-sm">詳細を見る</a>
                                <button class="btn btn-outline-danger btn-sm" onclick="removeFromFavorites(${serviceId})">
                                    <i class="fas fa-heart-broken"></i> 削除
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('favorites-list').innerHTML += card;
        }
    });
}

function removeFromFavorites(serviceId) {
    let favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
    favorites = favorites.filter(id => id != serviceId);
    localStorage.setItem('favorites', JSON.stringify(favorites));
    displayFavorites();
    showToast('お気に入りから削除しました');
}

// ページ読み込み時に表示
document.addEventListener('DOMContentLoaded', displayFavorites);
</script>

<?php include 'includes/footer.php'; ?>
