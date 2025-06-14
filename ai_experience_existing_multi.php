<?php
// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

// セッション開始（ヘッダー出力前に行う）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';

// 必要な関数を直接定義
function getAIServices() {
    global $conn;
    
    try {
        $sql = "SELECT id, ai_service FROM AIInfo ORDER BY ai_service";
        $result = $conn->query($sql);
        
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
}

function validateNoteUrl($url) {
    return preg_match('/^https:\/\/note\.com\/[^\/]+\/n\/[^\/]+$/', $url);
}

function createOrGetUser($noteUsername, $email, $authType) {
    global $conn;
    
    try {
        // 既存ユーザーをチェック
        $sql = "SELECT id FROM ai_users WHERE note_username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $noteUsername);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }
        
        // 新規ユーザーを作成
        $sql = "INSERT INTO ai_users (note_username, email, auth_type) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $noteUsername, $email, $authType);
        
        if ($stmt->execute()) {
            return $conn->insert_id;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function checkUrlExists($url) {
    global $conn;
    
    $sql = "SELECT id, ai_service_id FROM ai_articles WHERE url = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $url);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

function processMultipleAIArticle($noteUrl, $noteUsername, $email, $aiServiceIds) {
    global $conn;
    
    try {
        // 記事のタイトルを取得（簡易版）
        $title = "note記事: " . basename($noteUrl);
        $summary = "複数のAIサービスについて書かれた記事";
        
        // ユーザーの作成または取得
        $userId = createOrGetUser($noteUsername, $email, 'note_auth');
        if (!$userId) {
            return ['success' => false, 'message' => 'ユーザーの作成に失敗しました'];
        }
        
        $registeredServices = [];
        $skippedServices = [];
        
        // 各AIサービスについて記事を登録
        foreach ($aiServiceIds as $aiServiceId) {
            // 既に同じURLとAIサービスの組み合わせが存在するかチェック
            $sql = "SELECT id FROM ai_articles WHERE url = ? AND ai_service_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $noteUrl, $aiServiceId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // 既に存在する場合はスキップ
                $sql = "SELECT ai_service FROM AIInfo WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $aiServiceId);
                $stmt->execute();
                $serviceResult = $stmt->get_result();
                $serviceRow = $serviceResult->fetch_assoc();
                $skippedServices[] = $serviceRow['ai_service'];
                continue;
            }
            
            // 記事を登録
            $sql = "INSERT INTO ai_articles (user_id, ai_service_id, url, title, summary, article_type, status) 
                    VALUES (?, ?, ?, ?, ?, 'existing_post', 'verified')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisss", $userId, $aiServiceId, $noteUrl, $title, $summary);
            
            if ($stmt->execute()) {
                // 登録されたサービス名を取得
                $sql = "SELECT ai_service FROM AIInfo WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $aiServiceId);
                $stmt->execute();
                $serviceResult = $stmt->get_result();
                $serviceRow = $serviceResult->fetch_assoc();
                $registeredServices[] = $serviceRow['ai_service'];
            }
        }
        
        $message = '';
        if (!empty($registeredServices)) {
            $message .= '登録されたAIサービス: ' . implode(', ', $registeredServices);
        }
        if (!empty($skippedServices)) {
            $message .= (!empty($message) ? '<br>' : '') . 'スキップされたAIサービス（既に登録済み）: ' . implode(', ', $skippedServices);
        }
        
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '例外: ' . $e->getMessage()];
    }
}

$pageTitle = '既存記事登録フロー（複数AI対応）';

// フォーム処理
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noteUrl = trim($_POST['note_url']);
    $noteTitle = trim($_POST['note_title'] ?? '');

    if (empty($noteTitle) && validateNoteUrl($noteUrl)) {
        $noteTitle = fetchNoteTitle($noteUrl);
    }
    $noteUsername = trim($_POST['note_username']);
    $email = trim($_POST['email']);
    $aiServiceIds = $_POST['ai_service_ids'] ?? [];
    
    if (empty($aiServiceIds)) {
        $error = '少なくとも1つのAIサービスを選択してください。';
    } elseif (validateNoteUrl($noteUrl)) {
        $result = processMultipleAIArticle($noteUrl, $noteUsername, $email, $aiServiceIds);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        $error = '有効なnote記事URLを入力してください。';
    }
}

function fetchNoteTitle($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 3
        ]
    ]);
    $html = @file_get_contents($url, false, $context);
    if ($html === false) return '';

    // og:title優先
    if (preg_match('/<meta property="og:title" content="([^"]+)"/i', $html, $matches)) {
        return $matches[1];
    }
    // titleタグ
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
        return trim($matches[1]);
    }
    return '';
}
// AIサービス一覧を取得
$aiServices = getAIServices();

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-link"></i> 既存記事登録フロー（複数AI対応）</h2>
                    <p class="mb-0 text-muted">複数のAIサービスについて書いた記事を登録できます</p>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= $message ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">note記事URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" name="note_url" required
                                   placeholder="https://note.com/username/n/article_id"
                                   value="<?= htmlspecialchars($_POST['note_url'] ?? '') ?>">
                            <div class="form-text">既に投稿済みのAI体験記事のURLを入力してください</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">記事で扱っているAIサービス <span class="text-danger">*</span></label>
                            <div class="form-text mb-2">複数選択可能です（複数のAIについて書いた記事の場合）</div>
                            <div class="row">
                                <?php foreach ($aiServices as $index => $service): ?>
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="ai_service_ids[]" value="<?= $service['id'] ?>" 
                                                   id="ai_service_<?= $service['id'] ?>"
                                                   <?= (isset($_POST['ai_service_ids']) && in_array($service['id'], $_POST['ai_service_ids'])) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="ai_service_<?= $service['id'] ?>">
                                                <?= htmlspecialchars($service['ai_service']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">記事タイトル <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="note_title" id="note_title"
                                    value="<?= htmlspecialchars($_POST['note_title'] ?? '') ?>">
                            <div class="form-text">note記事のタイトルを自動取得します。編集も可能です。</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">noteユーザー名 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="note_username" required
                                   placeholder="your_username"
                                   value="<?= htmlspecialchars($_POST['note_username'] ?? '') ?>">
                            <div class="form-text">noteのユーザー名（@マークなし）</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">メールアドレス</label>
                            <input type="email" class="form-control" name="email"
                                   placeholder="your@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <div class="form-text">認証用（後日メール認証を実装予定）</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">記事を登録</button>
                            <a href="registered_urls.php" class="btn btn-outline-info">登録済みURL一覧を見る</a>
                            <a href="ai_experience_auth.php" class="btn btn-outline-secondary">メインページに戻る</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="ai_service_ids[]"]');
    const titleInput = document.getElementById('note_title');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            let names = [];
            checkboxes.forEach(c => {
                if (c.checked) {
                    let label = document.querySelector('label[for="' + c.id + '"]');
                    if (label) names.push(label.textContent.trim());
                }
            });
            if (names.length > 0) {
                titleInput.value = names.join('・') + ' 体験記事';
            }
        });
    });
});
</script>
<?php include 'includes/footer.php'; ?>
