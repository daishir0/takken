<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Logger.php';

// 認証チェック
if (!Auth::checkBasicAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

// POSTリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POSTリクエストのみ受け付けます']);
    exit;
}

// Content-Typeをapplication/jsonに設定
header('Content-Type: application/json; charset=utf-8');

try {
    // リクエストデータを取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('無効なJSONデータです');
    }
    
    $historyId = $input['history_id'] ?? '';
    
    if (empty($historyId) || !is_numeric($historyId)) {
        throw new Exception('履歴IDが不正です');
    }
    
    Logger::info("質問履歴削除開始: ID={$historyId}");
    
    // データベースから削除
    $db = new Database();
    $result = $db->deleteQuestionHistory($historyId);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => '質問履歴を削除しました',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
        Logger::info("質問履歴削除完了: ID={$historyId}");
    } else {
        throw new Exception('データベースからの削除に失敗しました');
    }
    
} catch (Exception $e) {
    Logger::error("質問履歴削除エラー: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
