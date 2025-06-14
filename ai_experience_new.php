<?php
// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

// セッション開始（ヘッダー出力前に行う）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';

// 必要な関数を直接定義（experience_functions.phpに依存しない）
function getAIServices() {
    global $conn;
    
    try {
        $sql = "SELECT id, ai_service FROM AIInfo ORDER BY ai_service";
        $result = $conn->query($sql);
        
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            echo "エラー: " . $conn->error;
            return [];
        }
    } catch (Exception $e) {
        echo "例外: " . $e->getMessage();
        return [];
    }
}

function recordExperience($sessionId, $aiServiceId, $experienceData) {
    global $conn;
    
    try {
        $sql = "INSERT INTO ai_experience_logs (session_id, ai_service_id, experience_data) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $experienceJson = json_encode($experienceData);
        $stmt->bind_param("sis", $sessionId, $aiServiceId, $experienceJson);
        
        return $stmt->execute();
    } catch (Exception $e) {
        echo "例外: " . $e->getMessage();
        return false;
    }
}

$pageTitle = '新規記事作成フロー';
$sessionId = $_SESSION['experience_session_id'] ?? uniqid('exp_', true);
$_SESSION['experience_session_id'] = $sessionId;

// フォーム処理
$step = $_GET['step'] ?? 1;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Step 1: AI体験の記録
        $aiServiceId = intval($_POST['ai_service_id']);
        $experienceData = [
            'usage_purpose' => $_POST['usage_purpose'],
            'features_used' => $_POST['features_used'] ?? [],
            'satisfaction' => intval($_POST['satisfaction']),
            'notes' => $_POST['notes']
        ];
        
        if (recordExperience($sessionId, $aiServiceId, $experienceData)) {
            header('Location: ai_experience_new_fixed.php?step=2');
            exit;
        } else {
            $error = '体験の記録に失敗しました。';
        }
    }
}

// AIサービス一覧を取得
$aiServices = getAIServices();

// ヘッダーを含める
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-edit"></i> 新規記事作成フロー</h2>
                    <div class="progress mt-2">
                        <div class="progress-bar" style="width: <?= ($step / 3) * 100 ?>%"></div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <?php if ($step == 1): ?>
                        <!-- Step 1: AI体験の記録 -->
                        <h4>Step 1: AI体験を記録</h4>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">体験したAIサービス <span class="text-danger">*</span></label>
                                <select class="form-select" name="ai_service_id" required>
                                    <option value="">選択してください</option>
                                    <?php foreach ($aiServices as $service): ?>
                                        <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['ai_service']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">使用目的 <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="usage_purpose" rows="3" required
                                          placeholder="どのような目的でこのAIを使用しましたか？"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">使用した機能</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="features_used[]" value="text_generation">
                                            <label class="form-check-label">テキスト生成</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="features_used[]" value="image_generation">
                                            <label class="form-check-label">画像生成</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="features_used[]" value="translation">
                                            <label class="form-check-label">翻訳</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="features_used[]" value="analysis">
                                            <label class="form-check-label">分析・要約</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">満足度 <span class="text-danger">*</span></label>
                                <select class="form-select" name="satisfaction" required>
                                    <option value="">選択してください</option>
                                    <option value="5">⭐⭐⭐⭐⭐ 非常に満足</option>
                                    <option value="4">⭐⭐⭐⭐ 満足</option>
                                    <option value="3">⭐⭐⭐ 普通</option>
                                    <option value="2">⭐⭐ やや不満</option>
                                    <option value="1">⭐ 不満</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">メモ・感想</label>
                                <textarea class="form-control" name="notes" rows="3"
                                          placeholder="体験中に感じたことや気づいたことがあれば記入してください"></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">体験を記録して次へ</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
