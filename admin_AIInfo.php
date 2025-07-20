<?php
require_once 'includes/db_connect.php';
session_start();

$pageTitle = 'AIサービス管理 - 管理画面';

// 管理者認証（必要に応じて強化してください）
$isAdmin = true; // 実際の認証ロジックに置き換えてください

if (!$isAdmin) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// 新規追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $ai_service = trim($_POST['ai_service']);
    $ai_icon = trim($_POST['ai_icon']);
    $description = trim($_POST['description']);
    $official_url = trim($_POST['official_url']);
    $launch_url = trim($_POST['launch_url']);
    
    if (!empty($ai_service)) {
        $stmt = $conn->prepare("INSERT INTO AIInfo (ai_service, ai_icon, description, official_url, launch_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $ai_service, $ai_icon, $description, $official_url, $launch_url);
        
        if ($stmt->execute()) {
            $message = "新しいAIサービス「{$ai_service}」を追加しました。";
            $messageType = 'success';
        } else {
            $message = "AIサービスの追加に失敗しました。";
            $messageType = 'danger';
        }
        $stmt->close();
    } else {
        $message = "AIサービス名は必須です。";
        $messageType = 'warning';
    }
}

// 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $id = intval($_POST['id']);
    $ai_service = trim($_POST['ai_service']);
    $ai_icon = trim($_POST['ai_icon']);
    $description = trim($_POST['description']);
    $official_url = trim($_POST['official_url']);
    $launch_url = trim($_POST['launch_url']);
    
    $stmt = $conn->prepare("UPDATE AIInfo SET ai_service=?, ai_icon=?, description=?, official_url=?, launch_url=? WHERE id=?");
    $stmt->bind_param("sssssi", $ai_service, $ai_icon, $description, $official_url, $launch_url, $id);
    
    if ($stmt->execute()) {
        $message = "AIサービス「{$ai_service}」を更新しました。";
        $messageType = 'success';
    } else {
        $message = "AIサービスの更新に失敗しました。";
        $messageType = 'danger';
    }
    $stmt->close();
}

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    $id = intval($_POST['id']);
    
    // 関連記事があるかチェック
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM ai_articles WHERE ai_service_id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $checkStmt->close();
    
    if ($count > 0) {
        $message = "このAIサービスは {$count} 件の記事で使用されているため削除できません。";
        $messageType = 'warning';
    } else {
        $stmt = $conn->prepare("DELETE FROM AIInfo WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "AIサービスを削除しました。";
            $messageType = 'success';
        } else {
            $message = "AIサービスの削除に失敗しました。";
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// AIサービス一覧取得（使用記事数も含む）- AI_comparison.phpと同じ並び順
$sql = "SELECT 
    ai.id, 
    ai.ai_service, 
    ai.ai_icon, 
    ai.description, 
    ai.official_url, 
    ai.launch_url,
    COUNT(a.id) as article_count
FROM AIInfo ai
LEFT JOIN ai_articles a ON ai.id = a.ai_service_id
GROUP BY ai.id, ai.ai_service, ai.ai_icon, ai.description, ai.official_url, ai.launch_url
ORDER BY ai.ai_service ASC";

$result = $conn->query($sql);
$aiServices = $result->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.admin-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
    margin-bottom: 1.5rem;
}

.admin-card .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    border-radius: 10px 10px 0 0 !important;
}

.service-icon {
    width: 50px;
    height: 50px;
    object-fit: contain;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.service-stats {
    background: #e9ecef;
    border-radius: 6px;
    padding: 0.5rem;
    font-size: 0.9rem;
}

.btn-group-actions {
    gap: 0.5rem;
}

.form-floating label {
    font-size: 0.9rem;
}

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
}

.table th {
    background: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.badge-article-count {
    background: linear-gradient(45deg, #667eea, #764ba2);
}

.url-preview {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.85rem;
    color: #6c757d;
}

.description-preview {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 768px) {
    .admin-header {
        padding: 1rem 0;
    }
    
    .btn-group-actions {
        flex-direction: column;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
}

.table th:first-child,
.table td:first-child {
    width: 100px;
    text-align: center;
}

.table th:last-child,
.table td:last-child {
    width: 80px;
    text-align: center;
}

.btn-edit-primary {
    background: #007bff;
    border-color: #007bff;
    color: white;
    font-weight: 600;
}

.btn-edit-primary:hover {
    background: #0056b3;
    border-color: #0056b3;
}
</style>

<div class="admin-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="fas fa-robot"></i> AIサービス管理
                </h1>
                <p class="mb-0 mt-2 opacity-75">AIサービス情報の追加・編集・削除</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="admin_articles.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> 記事管理に戻る
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- メッセージ表示 -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 統計情報 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card admin-card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?= count($aiServices) ?></h3>
                    <p class="mb-0">登録AIサービス数</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card admin-card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?= array_sum(array_column($aiServices, 'article_count')) ?></h3>
                    <p class="mb-0">総記事数</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card admin-card">
                <div class="card-body">
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                        <i class="fas fa-plus"></i> 新しいAIサービスを追加
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- AIサービス一覧テーブル -->
    <div class="card admin-card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list"></i> AIサービス一覧
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>編集</th>
                            <th>ID</th>
                            <th>アイコン</th>
                            <th>サービス名</th>
                            <th>説明</th>
                            <th>公式URL</th>
                            <th>起動URL</th>
                            <th>記事数</th>
                            <th>削除</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aiServices as $service): ?>
                            <tr>
                                <td>
                                    <button class="btn btn-primary btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editServiceModal<?= $service['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $service['id'] ?></span>
                                </td>
                                <td>
                                    <img src="icons/<?= htmlspecialchars($service['ai_icon'] ?: 'default-icon.png') ?>" 
                                         alt="<?= htmlspecialchars($service['ai_service']) ?>" 
                                         class="service-icon"
                                         onerror="this.src='icons/default-icon.png'">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($service['ai_service']) ?></strong>
                                </td>
                                <td>
                                    <div class="description-preview" title="<?= htmlspecialchars($service['description']) ?>">
                                        <?= htmlspecialchars($service['description'] ?: '説明なし') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($service['official_url'])): ?>
                                        <a href="<?= htmlspecialchars($service['official_url']) ?>" target="_blank" class="url-preview">
                                            <?= htmlspecialchars($service['official_url']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">未設定</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($service['launch_url'])): ?>
                                        <a href="<?= htmlspecialchars($service['launch_url']) ?>" target="_blank" class="url-preview">
                                            <?= htmlspecialchars($service['launch_url']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">未設定</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-article-count"><?= $service['article_count'] ?> 件</span>
                                </td>
                                <td>
                                    <?php if ($service['article_count'] == 0): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteServiceModal<?= $service['id'] ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="記事が存在するため削除できません">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 新規追加モーダル -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> 新しいAIサービスを追加
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="ai_service" name="ai_service" required>
                                <label for="ai_service">AIサービス名 *</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="ai_icon" name="ai_icon" placeholder="例: chatgpt.png">
                                <label for="ai_icon">アイコンファイル名</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="description" name="description" style="height: 100px"></textarea>
                        <label for="description">説明</label>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="url" class="form-control" id="official_url" name="official_url">
                                <label for="official_url">公式URL</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="url" class="form-control" id="launch_url" name="launch_url">
                                <label for="launch_url">起動URL</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="add_service" class="btn btn-primary">
                        <i class="fas fa-plus"></i> 追加
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 編集モーダル -->
<?php foreach ($aiServices as $service): ?>
<div class="modal fade" id="editServiceModal<?= $service['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> AIサービスを編集
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $service['id'] ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" name="ai_service" 
                                       value="<?= htmlspecialchars($service['ai_service']) ?>" required>
                                <label>AIサービス名 *</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" name="ai_icon" 
                                       value="<?= htmlspecialchars($service['ai_icon']) ?>">
                                <label>アイコンファイル名</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-floating mb-3">
                        <textarea class="form-control" name="description" style="height: 100px"><?= htmlspecialchars($service['description']) ?></textarea>
                        <label>説明</label>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="url" class="form-control" name="official_url" 
                                       value="<?= htmlspecialchars($service['official_url']) ?>">
                                <label>公式URL</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="url" class="form-control" name="launch_url" 
                                       value="<?= htmlspecialchars($service['launch_url']) ?>">
                                <label>起動URL</label>
                            </div>
                        </div>
                    </div>
                    <div class="service-stats">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            このAIサービスは現在 <strong><?= $service['article_count'] ?> 件</strong>の記事で使用されています
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="update_service" class="btn btn-primary">
                        <i class="fas fa-save"></i> 更新
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- 削除確認モーダル -->
<?php foreach ($aiServices as $service): ?>
<?php if ($service['article_count'] == 0): ?>
<div class="modal fade" id="deleteServiceModal<?= $service['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle"></i> 削除確認
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>以下のAIサービスを削除してもよろしいですか？</p>
                <div class="alert alert-warning">
                    <strong><?= htmlspecialchars($service['ai_service']) ?></strong>
                    <br><small>ID: <?= $service['id'] ?></small>
                </div>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    この操作は取り消せません。
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="id" value="<?= $service['id'] ?>">
                    <button type="submit" name="delete_service" class="btn btn-danger">
                        <i class="fas fa-trash"></i> 削除
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<script>
// フォームバリデーション
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('必須項目を入力してください。');
            }
        });
    });
});

// URL検証
function validateUrl(input) {
    const urlPattern = /^https?:\/\/.+/;
    if (input.value && !urlPattern.test(input.value)) {
        input.setCustomValidity('有効なURLを入力してください（http://またはhttps://で始まる）');
    } else {
        input.setCustomValidity('');
    }
}

// URL入力フィールドにイベントリスナーを追加
document.querySelectorAll('input[type="url"]').forEach(input => {
    input.addEventListener('blur', () => validateUrl(input));
});
</script>

<?php include 'includes/footer.php'; ?>
