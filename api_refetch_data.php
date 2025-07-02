<?php
// エラー表示を有効にする（開発時のみ）
error_reporting(E_ALL);
ini_set('display_errors', 0); // ブラウザには表示しない
ini_set('log_errors', 1);

// すべての出力をバッファリング
ob_start();

try {
    // セッション開始
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 出力バッファをクリア（エラーや警告を防ぐため）
    ob_clean();
    
    // JSONヘッダーを設定
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // セッション情報をチェック
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    
    // テスト用レスポンス
    $response = [
        'success' => true,
        'message' => 'APIテスト成功',
        'debug' => [
            'session_id' => session_id(),
            'is_admin' => $isAdmin,
            'session_data' => $_SESSION,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    // 管理者権限チェック
    if (!$isAdmin) {
        $response = [
            'success' => false,
            'message' => '管理者権限が必要です',
            'debug' => $response['debug']
        ];
    }
    
    // JSONを出力
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // エラーハンドリング
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $errorResponse = [
        'success' => false,
        'message' => 'サーバーエラー: ' . $e->getMessage(),
        'debug' => [
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'error_trace' => $e->getTraceAsString()
        ]
    ];
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}

// 出力バッファを終了
ob_end_flush();
?>