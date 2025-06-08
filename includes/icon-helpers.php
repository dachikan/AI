<?php
/**
 * AIアイコン表示用のヘルパー関数
 */

/**
 * AIアイコンを安全に表示する
 */
function renderAIIcon($iconPath, $serviceName, $className = 'ai-icon', $lazy = false) {
    $iconUrl = 'icons/' . htmlspecialchars($iconPath);
    $alt = htmlspecialchars($serviceName);
    $class = htmlspecialchars($className);
    
    // レイジーローディング対応
    if ($lazy) {
        return sprintf(
            '<img data-src="%s" alt="%s" class="%s loading" title="%s" loading="lazy">',
            $iconUrl,
            $alt,
            $class,
            $alt
        );
    } else {
        return sprintf(
            '<img src="%s" alt="%s" class="%s" title="%s" onerror="window.aiIconManager.setErrorState(this)">',
            $iconUrl,
            $alt,
            $class,
            $alt
        );
    }
}

/**
 * アイコンファイルの存在確認
 */
function iconExists($iconPath) {
    if (empty($iconPath)) {
        return false;
    }
    
    $fullPath = __DIR__ . '/../icons/' . $iconPath;
    return file_exists($fullPath) && is_readable($fullPath) && filesize($fullPath) > 0;
}

/**
 * デフォルトアイコンのパスを取得
 */
function getDefaultIconPath() {
    return 'default-icon.png';
}

/**
 * アイコンのフォールバック処理
 */
function getIconWithFallback($iconPath, $serviceName) {
    if (empty($iconPath) || !iconExists($iconPath)) {
        // サービス名から推測されるアイコンパスを試行
        $guessedPath = strtolower(str_replace([' ', '-'], '_', $serviceName)) . '.png';
        if (iconExists($guessedPath)) {
            return $guessedPath;
        }
        
        // デフォルトアイコンを返す
        return getDefaultIconPath();
    }
    
    return $iconPath;
}

/**
 * レスポンシブアイコンクラスを取得
 */
function getResponsiveIconClass($size = 'medium') {
    switch ($size) {
        case 'small':
            return 'ai-icon-small';
        case 'large':
            return 'ai-icon-large';
        case 'ranking':
            return 'ai-icon-ranking';
        case 'compare':
            return 'ai-icon-compare';
        default:
            return 'ai-icon';
    }
}
?>
