<?php
/**
 * アイコンファイルの存在確認ユーティリティ
 */
require_once 'includes/db_connect.php';

// 全AIサービスのアイコンをチェック
$services = getAIServices();
$missingIcons = [];
$existingIcons = [];

foreach ($services as $service) {
    $iconPath = 'icons/' . $service['ai_icon'];
    
    if (file_exists($iconPath) && filesize($iconPath) > 0) {
        $existingIcons[] = [
            'service' => $service['ai_service'],
            'icon' => $service['ai_icon'],
            'size' => filesize($iconPath)
        ];
    } else {
        $missingIcons[] = [
            'service' => $service['ai_service'],
            'icon' => $service['ai_icon']
        ];
    }
}

echo "<h2>アイコン存在確認結果</h2>";
echo "<h3>存在するアイコン: " . count($existingIcons) . "個</h3>";
echo "<h3>不足しているアイコン: " . count($missingIcons) . "個</h3>";

if (!empty($missingIcons)) {
    echo "<h4>不足しているアイコン一覧:</h4>";
    echo "<ul>";
    foreach ($missingIcons as $missing) {
        echo "<li>{$missing['service']} - {$missing['icon']}</li>";
    }
    echo "</ul>";
    echo "<p><strong>これらのサービスには default-icon.png が表示されます。</strong></p>";
}

// default-icon.pngの存在確認
if (file_exists('icons/default-icon.png')) {
    echo "<p style='color: green;'>✓ default-icon.png は存在します (" . filesize('icons/default-icon.png') . " bytes)</p>";
} else {
    echo "<p style='color: red;'>✗ default-icon.png が見つかりません</p>";
}
?>
