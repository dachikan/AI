<?php
// デバッグ用ページ - エラーの原因を特定

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>AI活用体験ポータル - デバッグ情報</h2>";

// 1. ファイルの存在確認
echo "<h3>1. ファイル存在確認</h3>";
$files = [
    'includes/db_connect.php',
    'includes/header.php', 
    'includes/footer.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ {$file} - 存在<br>";
    } else {
        echo "❌ {$file} - 存在しない<br>";
    }
}

// 2. データベース接続テスト
echo "<h3>2. データベース接続テスト</h3>";
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

// 3. テーブル存在確認
echo "<h3>3. テーブル存在確認</h3>";
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

// 4. 関数存在確認
echo "<h3>4. 関数存在確認</h3>";
$functions = ['getAIServices', 'getPromptCategories'];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ {$func}() - 定義済み<br>";
    } else {
        echo "❌ {$func}() - 未定義<br>";
    }
}

// 5. 簡単なクエリテスト
echo "<h3>5. 簡単なクエリテスト</h3>";
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

// 6. PHPバージョン確認
echo "<h3>6. PHP環境情報</h3>";
echo "PHPバージョン: " . PHP_VERSION . "<br>";
echo "MySQLi拡張: " . (extension_loaded('mysqli') ? '✅ 有効' : '❌ 無効') . "<br>";

// 7. セッション確認
echo "<h3>7. セッション確認</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "セッションID: " . session_id() . "<br>";
echo "セッション状態: " . session_status() . "<br>";

?>
