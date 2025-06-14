<!-- フッター -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-robot"></i> AI Portal</h5>
                    <p>最新のAIサービス情報をお届けします</p>
                </div>
                <div class="col-md-6">
                    <h6>リンク</h6>
                    <ul class="list-unstyled">
                        <li><a href="AI_dashboard.php" class="text-light">ダッシュボード</a></li>
                        <li><a href="AI_list.php" class="text-light">一覧</a></li>
                        <li><a href="AI_ranking.php" class="text-light">ランキング</a></li>
                        <li><a href="AI_comparison.php" class="text-light">比較</a></li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2025 喜寿プログラマ. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 比較リスト管理
        function addToComparison(serviceId) {
            let compareList = JSON.parse(localStorage.getItem('compareList') || '[]');
            if (!compareList.includes(serviceId)) {
                if (compareList.length >= 4) {
                    alert('比較できるのは最大4つまでです');
                    return;
                }
                compareList.push(serviceId);
                localStorage.setItem('compareList', JSON.stringify(compareList));
                updateCompareButton();
                showToast('比較リストに追加しました');
            } else {
                alert('既に比較リストに追加されています');
            }
        }

        function removeFromComparison(serviceId) {
            let compareList = JSON.parse(localStorage.getItem('compareList') || '[]');
            compareList = compareList.filter(id => id != serviceId);
            localStorage.setItem('compareList', JSON.stringify(compareList));
            updateCompareButton();
            showToast('比較リストから削除しました');
        }

        function updateCompareButton() {
            const compareList = JSON.parse(localStorage.getItem('compareList') || '[]');
            const buttons = document.querySelectorAll('.compare-btn');
            buttons.forEach(btn => {
                const serviceId = parseInt(btn.dataset.serviceId);
                if (compareList.includes(serviceId)) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-warning');
                    btn.innerHTML = '<i class="fas fa-check"></i> 比較中';
                } else {
                    btn.classList.remove('btn-warning');
                    btn.classList.add('btn-outline-secondary');
                    btn.innerHTML = '<i class="fas fa-plus"></i> 比較に追加';
                }
            });
        }

        function showToast(message) {
            // 簡単なトースト表示
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 m-3 alert alert-success alert-dismissible';
            toast.style.zIndex = '9999';
            toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // ページ読み込み時に比較ボタンを更新
        document.addEventListener('DOMContentLoaded', updateCompareButton);
    </script>
</body>
</html>
