<?php
/**
 * AIサービスの投稿リンクコンポーネント（シンプル版）
 * rating機能なし、「使ったよ」リンクのみ
 */

/**
 * 「使ったよ」リンクを表示
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
    $postUrl = "ai_experience_auth.php?ai_service_id=" . $aiServiceId;
    
    // HTML出力
    ob_start();
?>
<div class="ai-action-links">
    <a href="<?= $postUrl ?>" class="btn btn-primary used-it-link">
        <i class="fas fa-edit"></i> 使ったよ
        <?php if ($showTitle && !empty($titlePreview)): ?>
        <span class="title-preview"><?= htmlspecialchars($titlePreview) ?></span>
        <?php endif; ?>
    </a>
</div>

<style>
/* 使ったよリンクのスタイル */
.used-it-link {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 12px 16px;
    margin-bottom: 10px;
    text-align: left;
    width: 100%;
    text-decoration: none;
}

.title-preview {
    display: block;
    font-size: 0.85rem;
    margin-top: 6px;
    font-weight: normal;
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.3;
}

.used-it-link:hover .title-preview {
    color: rgba(255, 255, 255, 1);
}
</style>
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
    $postUrl = "ai_experience_auth.php?ai_service_id=" . $aiServiceId;
    
    // テンプレートからタイトルプレビュー取得
    $titlePreview = '';
    $template = getPostTemplate($aiServiceId, 'new_post');
    if ($template) {
        $title = $template['title_template'];
        $title = str_replace('{AI_SERVICE_NAME}', $aiService['ai_service'], $title);
        $titlePreview = mb_substr($title, 0, 25) . (mb_strlen($title) > 25 ? '...' : '');
    }
    
    ob_start();
?>
<a href="<?= $postUrl ?>" class="btn btn-sm btn-outline-primary compact-post-link" title="<?= htmlspecialchars($title ?? '') ?>">
    <i class="fas fa-edit"></i> 
    <?php if (!empty($titlePreview)): ?>
        <?= htmlspecialchars($titlePreview) ?>
    <?php else: ?>
        使ったよ
    <?php endif; ?>
</a>

<style>
.compact-post-link {
    font-size: 0.8rem;
    padding: 4px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}
</style>
<?php
    return ob_get_clean();
}
?>
