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
    
    $questionId = $input['question_id'] ?? '';
    $userAnswer = $input['user_answer'] ?? '';
    $correctAnswer = $input['correct_answer'] ?? '';
    
    if (empty($questionId) || empty($userAnswer) || empty($correctAnswer)) {
        throw new Exception('必要なパラメータが不足しています');
    }
    
    // 正解判定
    $isCorrect = (intval($userAnswer) === intval($correctAnswer));
    
    Logger::info("回答処理開始: 問題ID={$questionId}, ユーザー回答={$userAnswer}, 正解={$correctAnswer}, 判定=" . ($isCorrect ? '正解' : '不正解'));
    
    // データベースに回答履歴を保存
    $db = new Database();
    $result = $db->saveAnswerHistory($questionId, $userAnswer, $isCorrect);
    
    if ($result) {
        // 問題の統計情報を取得
        $stats = $db->getQuestionStats($questionId);
        
        echo json_encode([
            'success' => true,
            'is_correct' => $isCorrect,
            'stats' => $stats,
            'message' => $isCorrect ? '正解です！' : '不正解です',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
        Logger::info("回答処理完了: 問題ID={$questionId}, 統計更新完了");
    } else {
        throw new Exception('回答履歴の保存に失敗しました');
    }
    
} catch (Exception $e) {
    Logger::error("回答処理エラー: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}