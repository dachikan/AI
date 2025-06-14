<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['url'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$url = trim($_POST['url']);

// URL検証
if (!filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'note.com') === false) {
    echo json_encode(['success' => false, 'error' => 'Invalid note URL']);
    exit;
}

// タイトル取得
function fetchNoteTitle($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (compatible; TitleFetcher/1.0)'
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    if ($html === false) return '';

    // og:title優先
    if (preg_match('/<meta property="og:title" content="([^"]+)"/i', $html, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }
    
    // titleタグ
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
        $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        // noteの場合、「｜note」を除去
        $title = preg_replace('/\s*[｜|]\s*note\s*$/i', '', $title);
        return trim($title);
    }
    
    return '';
}

$title = fetchNoteTitle($url);

if (!empty($title)) {
    echo json_encode(['success' => true, 'title' => $title]);
} else {
    echo json_encode(['success' => false, 'error' => 'Title not found']);
}
?>