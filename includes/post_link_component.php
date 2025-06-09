<?php
/**
 * AIサービスの投稿リンクコンポーネント
 * 
 * このファイルは、AIサービス詳細ページやリストページに「使ったよ」リンクと
 * いいね/だめねボタンを表示するためのコンポーネントです。
 */

/**
 * 「使ったよ」リンクとアクションボタンを表示
 * 
 * @param array $aiService AIサービスの情報配列
 * @param bool $showTitle タイトルプレビューを表示するかどうか
 * @param int $previewLength タイトルプレビューの文字数
 * @return string HTML出力
 */
function renderPostLinkComponent($aiService, $showTitle = true, $previewLength = 30) {
    // AIサービスIDの取得
    $aiServiceId = $aiService['id'];
    
    // テンプレートの取得（タイトルプレビュー用）
    $titlePreview = '';
    if ($showTitle) {
        $template = getPostTemplate($aiServiceId, 'new_post');
        if ($template) {
            $title = $template['title_template'];
            $title = str_replace('{AI_SERVICE_NAME}', $aiService['ai_service'], $title);
            $titlePreview = mb_substr($title, 0, $previewLength) . (mb_strlen($title) > $previewLength ? '...' : '');
        }
    }
    
    // 投稿リンクURL
    $postUrl = "AI_post_form.php?ai_service_id=" . $aiServiceId;
    
    // HTML出力
    ob_start();
?>
<div class="ai-action-links">
    <div class="row align-items-center">
        <div class="col-md-6">
            <a href="<?= $postUrl ?>" class="btn btn-primary used-it-link">
                <i class="fas fa-edit"></i> 使ったよ
                <?php if ($showTitle && !empty($titlePreview)): ?>
                <span class="title-preview"><?= htmlspecialchars($titlePreview) ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="col-md-6">
            <div class="rating-buttons">
                <button type="button" class="btn btn-sm btn-outline-success like-btn" data-ai-id="<?= $aiServiceId ?>">
                    <i class="fas fa-thumbs-up"></i> <span class="like-count"><?= $aiService['likes'] ?? 0 ?></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger dislike-btn" data-ai-id="<?= $aiServiceId ?>">
                    <i class="fas fa-thumbs-down"></i> <span class="dislike-count"><?= $aiService['dislikes'] ?? 0 ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* 使ったよリンクのスタイル */
.used-it-link {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 8px 12px;
    margin-bottom: 10px;
    text-align: left;
    width: 100%;
}

.title-preview {
    display: block;
    font-size: 0.8rem;
    margin-top: 4px;
    font-weight: normal;
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.2;
}

/* いいね/だめねボタンのスタイル - 小さくする */
.rating-buttons {
    display: flex;
    gap: 8px;
}

.rating-buttons .btn {
    padding: 4px 8px;
    font-size: 0.75rem;
}

/* モバイル対応 */
@media (max-width: 767px) {
    .rating-buttons {
        margin-top: 5px;
        justify-content: flex-start;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // いいね/だめねボタンのイベントハンドラ
    const likeButtons = document.querySelectorAll('.like-btn');
    const dislikeButtons = document.querySelectorAll('.dislike-btn');
    
    likeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const aiId = this.getAttribute('data-ai-id');
            updateRating(aiId, 'like', this);
        });
    });
    
    dislikeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const aiId = this.getAttribute('data-ai-id');
            updateRating(aiId, 'dislike', this);
        });
    });
    
    // 評価更新関数
    function updateRating(aiId, action, button) {
        // ここにAjaxリクエストを実装
        fetch('update_rating.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ai_id=${aiId}&action=${action}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // カウント更新
                const countSpan = button.querySelector(action === 'like' ? '.like-count' : '.dislike-count');
                if (countSpan) {
                    countSpan.textContent = data.count;
                }
                
                // ボタンスタイル更新
                button.classList.add('active');
                setTimeout(() => {
                    button.classList.remove('active');
                }, 1000);
            }
        })
        .catch(error => console.error('Error:', error));
    }
});
</script>
<?php
    return ob_get_clean();
}

/**
 * 簡易版の「使ったよ」リンクを表示（リストページ用）
 * 
 * @param array $aiService AIサービスの情報配列
 * @return string HTML出力
 */
function renderCompactPostLink($aiService) {
    $aiServiceId = $aiService['id'];
    $postUrl = "AI_post_form.php?ai_service_id=" . $aiServiceId;
    
    // テンプレートからタイトルプレビュー取得
    $titlePreview = '';
    $template = getPostTemplate($aiServiceId, 'new_post');
    if ($template) {
        $title = $template['title_template'];
        $title = str_replace('{AI_SERVICE_NAME}', $aiService['ai_service'], $title);
        $titlePreview = mb_substr($title, 0, 20) . (mb_strlen($title) > 20 ? '...' : '');
    }
    
    ob_start();
?>
<div class="compact-post-link">
    <a href="<?= $postUrl ?>" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-edit"></i> 
        <?php if (!empty($titlePreview)): ?>
            <?= htmlspecialchars($titlePreview) ?>
        <?php else: ?>
            使ったよ
        <?php endif; ?>
    </a>
</div>
<?php
    return ob_get_clean();
}
?>
