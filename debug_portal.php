<?php
// デバッグ用ページ - エラーの原因を特定

error_reporting(E_ALL);
ini_set('display_errors', 1);

// HTMLヘッダー
include 'includes/header.php';
?>
<div class="container py-4">
    <h1 class="mb-4">
        <i class="fas fa-bug"></i> デバッグポータル
        <span class="badge bg-warning text-dark">開発者向け</span>
    </h1>
    <div class="mb-3">
        <a href="admin_articles.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> 記事管理に戻る
        </a>
    </div>
</div>
<div class="container mt-4">
    <div class="row">
        <!-- 左ペイン：不足アイコン一覧 -->
        <div class="col-md-6">
        <h2>AI活用体験ポータル - デバッグ情報</h2>
        <h3>1. includesファイル存在確認</h3>
    <?php
        $files = [
            'includes/db_connect.php',
            'includes/header.php', 
            'includes/footer.php',
            'includes/experience_functions.php'
        ];
        foreach ($files as $file) {
            if (file_exists($file)) {
                echo "✅ {$file} - 存在<br>";
            } else {
                echo "❌ {$file} - 存在しない<br>";
            }
        }
    ?>
    <h3>2. データベース接続テスト</h3>
    <?php
        try {
            require_once 'includes/db_connect.php';
            if ($conn) {
                echo "✅ データベース接続成功<br>";
                echo "接続情報: " . $conn->host_info . "<br>";
            } else {
                echo "❌ データベース接続失敗<br>";
            }
        } catch (Exception $e) {
            echo "❌ データベース接続エラー: " . $e->getMessage() . "<br>";
        }
    ?>
    <h3>3. テーブル存在確認</h3>
    <?php
        if (isset($conn) && $conn) {
            $tables = ['ai_articles', 'AIInfo', 'AIPromptCategories', 'ai_users'];
            
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '{$table}'");
                if ($result && $result->num_rows > 0) {
                    echo "✅ {$table} - 存在<br>";
                    
                    // カラム確認
                    $columns = $conn->query("DESCRIBE {$table}");
                    if ($columns) {
                        echo "&nbsp;&nbsp;カラム数: " . $columns->num_rows . "<br>";
                    }
                } else {
                    echo "❌ {$table} - 存在しない<br>";
                }
            }
        }
    ?>
    <h3>4. 関数存在確認</h3>
    <?php
        $functions = ['getAIServices', 'getPromptCategories'];
        foreach ($functions as $func) {
            if (function_exists($func)) {
                echo "✅ {$func}() - 定義済み<br>";
            } else {
                echo "❌ {$func}() - 未定義<br>";
            }
        }
    ?>
    <h3>5. 簡単なクエリテスト</h3>
    <?php
        if (isset($conn) && $conn) {
            try {
                // ai_articlesテーブルの件数確認
                $result = $conn->query("SELECT COUNT(*) as count FROM ai_articles");
                if ($result) {
                    $row = $result->fetch_assoc();
                    echo "✅ ai_articles件数: " . $row['count'] . "<br>";
                }
                
                // AIInfoテーブルの件数確認
                $result = $conn->query("SELECT COUNT(*) as count FROM AIInfo");
                if ($result) {
                    $row = $result->fetch_assoc();
                    echo "✅ AIInfo件数: " . $row['count'] . "<br>";
                }
                
            } catch (Exception $e) {
                echo "❌ クエリエラー: " . $e->getMessage() . "<br>";
            }
        }
    ?>
    </div>
    <!-- 右ペイン： -->
    <div class="col-md-6">
    <h3>6. PHP環境情報</h3>
    <?php
            echo "PHPバージョン: " . PHP_VERSION . "<br>";
            echo "MySQLi拡張: " . (extension_loaded('mysqli') ? '✅ 有効' : '❌ 無効') . "<br>";
    ?>
    <h3>7. セッション確認</h3>
    <?php
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            echo "セッションID: " . session_id() . "<br>";
            echo "セッション状態: " . session_status() . "<br>";
    ?>
    <h3>8. エラーログ確認</h3>
    <?php
            $errorLog = ini_get('error_log');
            if ($errorLog && file_exists($errorLog)) {
                echo "エラーログファイル: {$errorLog}<br>";
                $logContents = file_get_contents($errorLog);
                if ($logContents) {
                    echo "<pre>" . htmlspecialchars($logContents) . "</pre>";
                } else {
                    echo "❌ エラーログが空です<br>";
                }
            } else {
                echo "❌ エラーログファイルが設定されていません<br>";
            }
    ?>
    </div>
</div>
<!-- フッター -->
<?php include 'includes/footer.php'; ?>