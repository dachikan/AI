<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$testUrl = 'https://note.com/info/n/n26b70424b122'; // note公式の記事でテスト

echo "<h1>デバッグ開始 (DOMDocument版): " . htmlspecialchars($testUrl) . "</h1>";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'User-Agent: Mozilla/5.0 (compatible; AI Experience Bot/1.0)',
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);
$html = file_get_contents($testUrl, false, $context);

if ($html === false || empty($html)) {
    echo "<p style='color:red;'><b>致命的エラー:</b> HTMLの取得に失敗しました。</p>";
    exit;
}

echo "<p>HTMLの取得に成功しました。文字数: " . strlen($html) . "文字</p>";
echo "<hr>";
echo "<h2>DOMDocumentでの解析結果</h2>";

// DOMDocumentでHTMLを解析
$dom = new DOMDocument();
// HTML5のタグでエラーが出ないように@で抑制
@$dom->loadHTML($html);

$metaTags = $dom->getElementsByTagName('meta');
$imageUrl = '';

foreach ($metaTags as $tag) {
    if ($tag->getAttribute('property') == 'og:image') {
        $imageUrl = $tag->getAttribute('content');
        break; // 見つかったらループを抜ける
    }
}

if (!empty($imageUrl)) {
    echo "<p style='color:green;'><b>成功:</b> サムネイル/アバター画像が見つかりました。</p>";
    echo '<p>URL: <a href="' . htmlspecialchars($imageUrl) . '">' . htmlspecialchars($imageUrl) . '</a></p>';
    echo '<img src="' . htmlspecialchars($imageUrl) . '" style="max-width:300px; border:1px solid #ccc;">';
} else {
    echo "<p style='color:orange;'><b>警告:</b> og:imageメタタグが見つかりませんでした。</p>";
    echo "<p><b>考えられる原因:</b></p>";
    echo "<ul><li>DOMDocumentでの解析に失敗した。</li><li>対象のページにog:imageが設定されていない。</li></ul>";
}
echo "<hr>";
?>