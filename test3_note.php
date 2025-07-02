<?php
/**
 * note記事の情報を解析する関数
 * 
 * @param string $url note記事のURL
 * @return array 記事情報
 */
function analyzeNoteArticle($url) {
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    // URLの正規化
    $url = trim($url);
    if (strpos($url, 'http') !== 0) {
        $url = 'https://' . $url;
    }
    
    // noteのURLかチェック
    if (strpos($url, 'note.com') === false && strpos($url, 'note.mu') === false) {
        return [
            'success' => false,
            'error' => '無効なnoteのURLです'
        ];
    }
    
    // HTMLを取得
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: ' . $userAgent,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                'Connection: keep-alive',
            ],
            'timeout' => 30,
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    
    if ($html === false) {
        return [
            'success' => false,
            'error' => 'HTMLの取得に失敗しました'
        ];
    }
    
    // DOMDocumentでHTMLを解析
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // タイトルを取得
    $title = 'タイトル不明';
    $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
    if ($ogTitle->length > 0) {
        $title = trim($ogTitle->item(0)->nodeValue);
    } else {
        $titleTag = $xpath->query('//title');
        if ($titleTag->length > 0) {
            $title = trim(str_replace('｜note', '', $titleTag->item(0)->nodeValue));
        }
    }
    
    // サムネイル画像URLを取得
    $thumbnailUrl = null;
    $ogImage = $xpath->query('//meta[@property="og:image"]/@content');
    if ($ogImage->length > 0) {
        $thumbnailUrl = $ogImage->item(0)->nodeValue;
        // 相対URLを絶対URLに変換
        if (strpos($thumbnailUrl, '//') === 0) {
            $thumbnailUrl = 'https:' . $thumbnailUrl;
        } elseif (strpos($thumbnailUrl, '/') === 0) {
            $thumbnailUrl = 'https://note.com' . $thumbnailUrl;
        }
    }
    
    // ユーザー名を取得
    $username = '著者不明';
    $ogSiteName = $xpath->query('//meta[@property="og:site_name"]/@content');
    if ($ogSiteName->length > 0) {
        $username = trim($ogSiteName->item(0)->nodeValue);
    } else {
        // URLからユーザー名を抽出
        if (preg_match('/note\.com\/([^\/]+)\//', $url, $matches)) {
            $username = $matches[1];
        }
    }
    
    // 概要を取得
    $summary = '';
    $ogDescription = $xpath->query('//meta[@property="og:description"]/@content');
    if ($ogDescription->length > 0) {
        $summary = trim($ogDescription->item(0)->nodeValue);
        // 長すぎる場合は切り詰める
        if (mb_strlen($summary) > 150) {
            $summary = mb_substr($summary, 0, 150) . '...';
        }
    }
    
    return [
        'success' => true,
        'title' => $title,
        'thumbnail_url' => $thumbnailUrl,
        'username' => $username,
        'summary' => $summary,
    ];
}

/**
 * noteユーザーのアバター情報を取得する関数
 * 
 * @param string $username noteのユーザー名
 * @return array ユーザー情報
 */
function getNoteUserInfo($username) {
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    // ユーザーページのURLを構築
    $userUrl = 'https://note.com/' . $username;
    
    // HTMLを取得
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: ' . $userAgent,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                'Connection: keep-alive',
            ],
            'timeout' => 30,
        ]
    ]);
    
    $html = @file_get_contents($userUrl, false, $context);
    
    if ($html === false) {
        return [
            'success' => false,
            'error' => 'ユーザー情報の取得に失敗しました'
        ];
    }
    
    // DOMDocumentでHTMLを解析
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // アバター画像URLを取得
    $avatarUrl = null;
    
    // og:imageからアバターを取得
    $ogImage = $xpath->query('//meta[@property="og:image"]/@content');
    if ($ogImage->length > 0) {
        $avatarUrl = $ogImage->item(0)->nodeValue;
        // 相対URLを絶対URLに変換
        if (strpos($avatarUrl, '//') === 0) {
            $avatarUrl = 'https:' . $avatarUrl;
        } elseif (strpos($avatarUrl, '/') === 0) {
            $avatarUrl = 'https://note.com' . $avatarUrl;
        }
    }
    
    // アバターが見つからない場合はプロフィール画像を探す
    if (!$avatarUrl) {
        $avatarElements = $xpath->query('//img[contains(@class, "avatar") or contains(@alt, "avatar") or contains(@src, "avatar")]/@src');
        if ($avatarElements->length > 0) {
            $avatarUrl = $avatarElements->item(0)->nodeValue;
            if (strpos($avatarUrl, '//') === 0) {
                $avatarUrl = 'https:' . $avatarUrl;
            } elseif (strpos($avatarUrl, '/') === 0) {
                $avatarUrl = 'https://note.com' . $avatarUrl;
            }
        }
    }
    
    return [
        'success' => true,
        'avatar_url' => $avatarUrl
    ];
}

// フォーム処理
$articles = [];
$error = '';
$processing = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['urls'])) {
    $processing = true;
    $urls = array_filter(array_map('trim', explode("\n", $_POST['urls'])));
    
    if (count($urls) > 20) {
        $error = '一度に処理できるURLは20個までです。';
        $processing = false;
    } else {
        foreach ($urls as $url) {
            if (empty($url)) continue;
            
            $result = analyzeNoteArticle($url);
            
            if ($result['success']) {
                // ユーザーのアバター情報を取得
                $userInfo = getNoteUserInfo($result['username']);
                $avatarUrl = $userInfo['success'] ? $userInfo['avatar_url'] : null;
                
                $articles[] = array_merge($result, [
                    'url' => $url,
                    'avatar_url' => $avatarUrl
                ]);
            } else {
                $articles[] = [
                    'success' => false,
                    'url' => $url,
                    'title' => 'エラー',
                    'username' => '不明',
                    'thumbnail_url' => null,
                    'avatar_url' => null,
                    'summary' => $result['error'] ?? '取得に失敗しました',
                    'error' => true
                ];
            }
            
            // レート制限
            if (count($urls) > 1) {
                sleep(2); // アバター取得分も考慮して少し長めに
            }
        }
        $processing = false;
    }
}


?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>note記事情報ビューア</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .hero-section h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .hero-section p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .form-card {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: none;
        }
        
        .article-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .article-image {
            height: 200px;
            object-fit: cover;
        }
        
        .no-image {
            height: 200px;
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .article-title {
            font-weight: 600;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 3rem;
        }
        
        .article-summary {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .author-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .user-avatar-placeholder {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .error-card {
            border-left: 4px solid #dc3545;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-analyze {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 12px 30px;
        }
        
        .btn-analyze:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .loading-spinner {
            display: none;
        }
        
        .loading .loading-spinner {
            display: inline-block;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h1><i class="bi bi-journal-text"></i> note記事情報ビューア</h1>
                    <p class="lead">複数のnote記事URLを入力して、美しいカード形式で情報を表示(著者アバター取得)</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Form Section -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8">
                <div class="card form-card">
                    <div class="card-body p-4">
                        <form method="POST" id="urlForm">
                            <div class="mb-4">
                                <label for="urls" class="form-label fw-bold">
                                    <i class="bi bi-link-45deg"></i> note記事のURL
                                </label>
                                <textarea 
                                    class="form-control" 
                                    name="urls" 
                                    id="urls" 
                                    rows="6"
                                    placeholder="https://note.com/username/n/note-id&#10;https://note.com/username2/n/note-id2&#10;..."
                                    required><?php echo htmlspecialchars($_POST['urls'] ?? ''); ?></textarea>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> 1行に1つずつURLを入力してください（最大20個まで）
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-analyze btn-primary btn-lg <?php echo $processing ? 'loading' : ''; ?>" id="analyzeBtn">
                                    <span class="loading-spinner spinner-border spinner-border-sm me-2" role="status"></span>
                                    <i class="bi bi-search"></i> 記事情報を取得
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Error Alert -->
        <?php if ($error): ?>
            <div class="row justify-content-center mb-4">
                <div class="col-lg-8">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Results Section -->
        <?php if (!empty($articles)): ?>
            <!-- Stats Card -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-8">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-graph-up"></i> 
                                <?php echo count($articles); ?>件の記事を処理しました
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Articles Grid -->
            <div class="row g-4 fade-in">
                <?php foreach ($articles as $index => $article): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card article-card h-100 <?php echo isset($article['error']) ? 'error-card' : ''; ?>">
                            <!-- Article Image -->
                            <?php if ($article['thumbnail_url']): ?>
                                <img src="<?php echo htmlspecialchars($article['thumbnail_url']); ?>" 
                                     class="card-img-top article-image" 
                                     alt="記事画像"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="no-image" style="display: none;">
                                    <i class="bi bi-image" style="font-size: 2rem;"></i>
                                </div>
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="bi bi-file-text" style="font-size: 2rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <!-- Article Title -->
                                <h5 class="card-title article-title mb-3">
                                    <?php echo htmlspecialchars($article['title']); ?>
                                </h5>
                                
                                <!-- Article Summary -->
                                <?php if (!empty($article['summary'])): ?>
                                    <p class="card-text article-summary mb-3 flex-grow-1">
                                        <?php echo htmlspecialchars($article['summary']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Author Badge with Avatar -->
                                <div class="mb-3">
                                    <span class="badge author-badge">
                                        <i class="bi bi-person-fill"></i> 
                                        <?php echo htmlspecialchars($article['username']); ?>
                                        <?php if ($article['avatar_url']): ?>
                                            <img src="<?php echo htmlspecialchars($article['avatar_url']); ?>" 
                                                 class="user-avatar" 
                                                 alt="ユーザーアバター"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="user-avatar-placeholder" style="display: none;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="user-avatar-placeholder">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <!-- Article Link -->
                                <div class="mt-auto">
                                    <a href="<?php echo htmlspecialchars($article['url']); ?>" 
                                       target="_blank" 
                                       class="btn btn-outline-primary btn-sm w-100">
                                        <i class="bi bi-box-arrow-up-right"></i> 記事を読む
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <footer class="text-center py-5 mt-5">
            <div class="text-muted">
                <i class="bi bi-heart-fill text-danger"></i> 
                Made with Bootstrap & PHP
            </div>
        </footer>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php include 'includes/footer.php'; ?>
    <script>
        // フォーム送信時のローディング表示
        document.getElementById('urlForm').addEventListener('submit', function() {
            const btn = document.getElementById('analyzeBtn');
            btn.disabled = true;
            btn.classList.add('loading');
        });
        
        // カード画像の遅延読み込みエラーハンドリング
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.article-image');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const noImageDiv = this.nextElementSibling;
                    if (noImageDiv && noImageDiv.classList.contains('no-image')) {
                        noImageDiv.style.display = 'flex';
                    }
                });
            });
        });
        
        // URLの数をカウントして表示
        document.getElementById('urls').addEventListener('input', function() {
            const urls = this.value.split('\n').filter(url => url.trim() !== '');
            const btn = document.getElementById('analyzeBtn');
            
            if (urls.length > 20) {
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> URLが多すぎます（最大20個）';
                btn.classList.add('btn-danger');
                btn.classList.remove('btn-primary');
            } else if (urls.length > 0) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i> ' + urls.length + '件の記事情報を取得';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-primary');
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i> 記事情報を取得';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-primary');
            }
        });
    </script>
</body>
</html>