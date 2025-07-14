<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once("includes/db_connect.php");

// デバッグ関数
function debugLog($message) {
    echo '<div class="debug-message">' . htmlspecialchars($message) . '</div>';
    error_log($message);
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>note記事情報の取得</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-info { background-color: #f8f9fa; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; }
        .debug-message { color: #666; margin: 5px 0; padding: 5px; background-color: #f1f1f1; }
        .error-message { margin: 20px 0; }
        .form-container { max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f8f9fa; border-radius: 10px; }
        .result-container { margin-top: 30px; }
        .card-img-top { height: 200px; object-fit: cover; }
        .avatar-img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
        /* 既存スタイルに追加 */
        .card { width: 356px; height: 304px; overflow: hidden; /* はみ出し防止 */ }
        .card-img-top { width: 357px;  height: 187px; object-fit: cover; /* アスペクト比維持 */
            object-position: center; /* 中央基準でトリミング */ }
        .card-body { padding: 12px; height: calc(304px - 187px); /* カード全体高 - 画像高 */
            display: flex; flex-direction: column; justify-content: space-between;
        }
        @media (max-width: 576px) { .card { width: 100% !important; height: auto !important; }
        .card-img-top { width: 100% !important; height: auto !important; max-height: 187px;}}
</style>
<style>
    /* カード全体の調整 */
    .card {
        width: 356px;
        border-radius: 8px !important; /* noteの角丸サイズ */
        box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* noteの影 */
        transition: transform 0.2s ease;
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    /* サムネイル画像 */
    .card-img-top {
        width: 100%;
        height: 187px;
        object-fit: cover;
        border-bottom: 1px solid #f5f5f5; /* 画像と本文の境界線 */
    }

    /* カード本文コンテナ */
    .card-body {
        padding: 16px;
        height: 117px; /* 304px - 187px */
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    /* タイトルスタイル（note準拠） */
    .card-title {
        font-size: 15px !important;
        font-weight: 700;
        line-height: 1.5;
        color: #333;
        margin-bottom: 8px;
        display: -webkit-box;
        -webkit-line-clamp: 2; /* 2行で省略 */
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 45px; /* 15px × 1.5 × 2 */
    }

    /* サマリーテキスト */
    .card-text {
        font-size: 13px !important;
        line-height: 1.5;
        color: #666;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 2; /* 2行で省略 */
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 39px; /* 13px × 1.5 × 2 */
    }

    /* ユーザー情報表示エリア */
    .author-info {
        display: flex;
        align-items: center;
        margin-top: auto; /* 下部に固定 */
        padding-top: 8px;
        border-top: 1px solid #f5f5f5;
    }

    .avatar-img {
        width: 32px !important; /* noteのアバターサイズ */
        height: 32px !important;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 8px;
    }

    .username {
        font-size: 12px !important;
        color: #999;
        font-weight: 400;
    }

    /* 記事リンクボタン */
    .btn-outline-primary {
        font-size: 12px !important;
        padding: 4px 8px !important;
        border-color: #e5e5e5 !important;
        color: #333 !important;
    }

    .btn-outline-primary:hover {
        background-color: #f8f8f8 !important;
    }
</style>
</head>
<body>
    <?php include 'includes/header.php';
?>
    <div class="container py-5">
        <div class="form-container shadow-sm">
            <h1 class="text-center mb-4">note記事のURLから情報を取得・保存</h1>
            
            <form action="note_article_info.php" method="POST">
                <div class="mb-3">
                    <label for="article_url" class="form-label">Note記事URL</label>
                    <input type="url" class="form-control" id="article_url" name="url"
                        placeholder="https://note.com/ユーザid/n/記事id/"
                        required>
                </div>
                
                <div class="mb-3">
                    <label for="ai_service_id" class="form-label">AIサービスを選択</label>
                    <select class="form-select" id="ai_service_id" name="ai_service_id" required>
                        <?php
                            $ai_services = getAIServices();
                            foreach ($ai_services as $service): 
                        ?>
                            <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['ai_service']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">記事情報を取得して保存</button>
                </div>
            </form>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo '<div class="debug-info mt-4"><h3>処理詳細</h3>';
            
            // 入力チェック
            if (empty($_POST['url'])) {
                die('<div class="alert alert-danger">記事URLが入力されていません</div>');
            }
            
            $url = trim($_POST['url']);
            $ai_service_id = intval($_POST['ai_service_id'] ?? 0);
            debugLog("入力URL: " . $url);
            
            // URLバリデーション
            if (!preg_match('#^https://note\.com/([^/]+)/n/([a-zA-Z0-9]+)#', $url, $matches)) {
                die('<div class="alert alert-danger">不正なNote URL形式です。正しい形式: https://note.com/ユーザーID/n/記事ID</div>');
            }
            
            $note_userId = $matches[1];
            $article_id = $matches[2];
            debugLog("解析結果 - ユーザーID: $note_userId, 記事ID: $article_id");
            
            // HTML取得
            $context = stream_context_create([
                'http' => [
                    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'timeout' => 10
                ]
            ]);
            
            debugLog("記事HTMLの取得を開始...");
            $html = @file_get_contents($url, false, $context);
            if (!$html) {
                $error = error_get_last();
                die('<div class="alert alert-danger">記事を取得できませんでした: ' . htmlspecialchars($error['message']) . '</div>');
            }
            debugLog("HTML取得成功 (長さ: " . strlen($html) . "バイト)");
            
            // DOM解析
            libxml_use_internal_errors(true);
            $doc = new DOMDocument();
            $loaded = @$doc->loadHTML($html);
            
            if (!$loaded) {
                debugLog("DOM解析でエラーが発生しました:");
                foreach (libxml_get_errors() as $error) {
                    debugLog(" - " . $error->message);
                }
                libxml_clear_errors();
            } else {
                debugLog("DOM解析に成功");
            }
            
            $xpath = new DOMXPath($doc);
            
            // メタ情報取得関数
            function getMetaContent($xpath, $attr, $value) {
                $query = "//meta[@$attr='$value']/@content";
                $nodes = $xpath->query($query);
                return $nodes->length > 0 ? $nodes[0]->nodeValue : '';
            }
            
            // 基本情報取得
            $title_full = getMetaContent($xpath, 'property', 'og:title');
            $title = explode('｜', $title_full)[0] ?? $title_full;
            $note_username = explode('｜', $title_full)[1] ?? '';
            $summary = mb_substr(getMetaContent($xpath, 'name', 'description'), 0, 150);
            $thumbnail_url = getMetaContent($xpath, 'property', 'og:image');
            
            debugLog("基本情報取得結果:");
            debugLog(" - タイトル: $title");
            debugLog(" - ユーザー名: $note_username");
            debugLog(" - サマリー: $summary");
            debugLog(" - サムネイルURL: $thumbnail_url");
            
            // アバター画像取得（強化版）
            $avatar_url = '';
            $avatar_sources = [];
            
            // 方法1: プロフィールページから取得
            debugLog("プロフィールページからアバター取得を試みます...");
            $profile_url = "https://note.com/$note_userId";
            $profile_html = @file_get_contents($profile_url, false, $context);
            
            if ($profile_html) {
                debugLog("プロフィールページの取得に成功");
                $profile_doc = new DOMDocument();
                @$profile_doc->loadHTML($profile_html);
                $profile_xpath = new DOMXPath($profile_doc);
                
                // メタタグから取得
                $meta_avatar = getMetaContent($profile_xpath, 'property', 'og:image');
                if ($meta_avatar && strpos($meta_avatar, 'profile_') !== false) {
                    $avatar_sources['meta_tag'] = $meta_avatar;
                    debugLog("メタタグからアバターを取得: $meta_avatar");
                }
                
                // imgタグから取得
                $imgs = $profile_xpath->query("//img[contains(@class, 'o-author__image') or contains(@class, 'a-userIcon')]");
                foreach ($imgs as $img) {
                    $src = $img->getAttribute('src');
                    if (strpos($src, 'profile_') !== false) {
                        $avatar_sources['profile_img_tag'] = $src;
                        debugLog("プロフィール画像タグから取得: $src");
                        break;
                    }
                }
            } else {
                debugLog("プロフィールページの取得に失敗");
            }
            
            // 方法2: 記事ページのJSONデータから取得
            debugLog("記事ページのJSONデータからアバター取得を試みます...");
            if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.+?)<\/script>/s', $html, $match)) {
                debugLog("__NEXT_DATA__ スクリプトを検出");
                $jsonData = json_decode($match[1], true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $json_avatar = $jsonData['props']['pageProps']['user']['profileImageUrl'] ?? 
                                  $jsonData['props']['pageProps']['note']['user']['profileImageUrl'] ?? '';
                    
                    if (!empty($json_avatar)) {
                        $avatar_sources['next_data'] = $json_avatar;
                        debugLog("__NEXT_DATA__ から取得: $json_avatar");
                    }
                } else {
                    debugLog("JSON解析エラー: " . json_last_error_msg());
                }
            } else {
                debugLog("__NEXT_DATA__ スクリプトが見つかりませんでした");
            }
            
            // 方法3: 記事ページのimgタグから直接取得
            debugLog("記事ページのimgタグからアバター取得を試みます...");
            $imgs = $xpath->query("//img[contains(@class, 'o-author__image') or contains(@class, 'a-userIcon')]");
            foreach ($imgs as $img) {
                $src = $img->getAttribute('src');
                if (strpos($src, 'profile_') !== false) {
                    $avatar_sources['article_img_tag'] = $src;
                    debugLog("記事内画像タグから取得: $src");
                    break;
                }
            }
            
            // 最適なアバターURLを選択
            if (!empty($avatar_sources)) {
                $avatar_url = $avatar_sources['next_data'] ?? 
                             $avatar_sources['meta_tag'] ?? 
                             $avatar_sources['profile_img_tag'] ?? 
                             $avatar_sources['article_img_tag'] ?? '';
                
                debugLog("選択されたアバターURL: $avatar_url");
            } else {
                debugLog("アバター画像が見つかりませんでした");
            }
            
            // データベース処理
            try {
                $conn->begin_transaction();
                debugLog("データベース処理を開始...");
                $conn->begin_transaction();
                
                // ユーザー処理
                $stmt = $conn->prepare("SELECT id FROM ai_users WHERE note_userId = ?");
                $stmt->bind_param("s", $note_userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                // ユーザー処理（安全なバージョン）
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $user_id = $row['id'];
                    debugLog("既存ユーザーを更新 (ID: $user_id)");
                    
                    // アバターURLが空でない場合のみ更新
                    if (!empty($avatar_url)) {
                        $stmt = $conn->prepare("UPDATE ai_users SET avatar_url = ?, note_username = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $avatar_url, $note_username, $user_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE ai_users SET note_username = ? WHERE id = ?");
                        $stmt->bind_param("si", $note_username, $user_id);
                    }
                    $stmt->execute();
                    $stmt->close();
                } else {
                    debugLog("新規ユーザーを登録");
                    // 新規登録時はavatar_urlが空でもINSERT
                    $stmt = $conn->prepare("INSERT INTO ai_users (note_userId, note_username, avatar_url) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $note_userId, $note_username, $avatar_url);
                    $stmt->execute();
                    $user_id = $conn->insert_id;
                    $stmt->close();
                }

                // 記事処理 (statusを'verified'に設定)
                $stmt = $conn->prepare("INSERT INTO ai_articles (id, user_id, ai_service_id, title, summary, thumbnail_url, url, status) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?,'verified')
                                        ON DUPLICATE KEY UPDATE 
                                        title = VALUES(title), 
                                        summary = VALUES(summary), 
                                        thumbnail_url = VALUES(thumbnail_url),
                                        status = 'verified'");
                $stmt->bind_param("siissss", $article_id, $user_id, $ai_service_id, $title, $summary, $thumbnail_url, $url);
                if (!$stmt->execute()) {
                    throw new Exception("記事の登録に失敗: " . $stmt->error);
                }
                $stmt->close();

                $conn->commit();
                debugLog("データベース処理が正常に完了");
                
                echo '<div class="card mb-4">';
                echo '<img src="' . htmlspecialchars($thumbnail_url) . '" class="card-img-top" alt="サムネイル">';
                echo '<div class="card-body">';
                echo '<h5 class="card-title">' . htmlspecialchars($title) . '</h5>';
                echo '<p class="card-text">' . htmlspecialchars($summary) . '</p>';

                // ユーザー情報表示部分（note風）
                echo '<div class="author-info">';
                if (!empty($avatar_url)) {
                    echo '<img src="' . htmlspecialchars($avatar_url) . '" class="avatar-img" alt="著者アバター">';
                }
                echo '<span class="username">' . htmlspecialchars($note_username) . '</span>';
                echo '</div>';

                echo '</div>'; // card-body
                echo '</div>'; // card

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                echo '<div class="alert alert-danger">エラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</div>';
                debugLog("データベースエラー: " . $e->getMessage());
            }
            
            echo '</div>'; // debug-info 閉じる
            
            // アバター取得ソースのデバッグ情報
            if (!empty($avatar_sources)) {
                echo '<div class="debug-info mt-4"><h4>アバター取得ソース</h4><ul class="list-group">';
                foreach ($avatar_sources as $source => $url) {
                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                    echo '<span>' . htmlspecialchars($source) . '</span>';
                    echo '<span class="badge bg-primary rounded-pill">' . htmlspecialchars($url) . '</span>';
                    echo '</li>';
                }
                echo '</ul></div>';
            }
        }
        ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>