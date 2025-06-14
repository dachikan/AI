<?php
// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>デバッグ情報</h2>";

// 1. ファイルの存在確認
$files_to_check = [
    'includes/db_connect.php',
    'includes/header.php',
    'includes/footer.php',
    'includes/experience_functions.php'
];

echo "<h3>ファイル存在確認:</h3>";
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ {$file} - 存在<br>";
    } else {
        echo "❌ {$file} - 存在しない<br>";
    }
}

// 2. データベース接続確認
echo "<h3>データベース接続確認:</h3>";
try {
    require_once 'includes/db_connect.php';
    if (isset($conn) && $conn) {
        echo "✅ データベース接続成功<br>";
        
        // テーブル存在確認
        $tables = ['ai_users', 'ai_experience_logs', 'ai_articles', 'AIInfo'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '{$table}'");
            if ($result && $result->num_rows > 0) {
                echo "✅ テーブル {$table} - 存在<br>";
            } else {
                echo "❌ テーブル {$table} - 存在しない<br>";
            }
        }
    } else {
        echo "❌ データベース接続失敗<br>";
    }
} catch (Exception $e) {
    echo "❌ データベースエラー: " . $e->getMessage() . "<br>";
}

// 3. セッション確認
echo "<h3>セッション確認:</h3>";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
echo "セッションID: " . session_id() . "<br>";
echo "セッション状態: " . session_status() . "<br>";
?>
