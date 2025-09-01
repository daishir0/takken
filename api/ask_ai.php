<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Logger.php';
require_once __DIR__ . '/../includes/AIHelper.php';

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
    $userQuestion = $input['user_question'] ?? '';
    
    if (empty($questionId)) {
        throw new Exception('問題IDが指定されていません');
    }
    
    // データベースから問題を取得
    $db = new Database();
    $question = $db->getQuestionById($questionId);
    
    if (!$question) {
        throw new Exception('指定された問題が見つかりません');
    }
    
    Logger::info("AI質問開始: 問題ID={$questionId}, 質問=" . substr($userQuestion, 0, 50));
    
    // 正答番号を取得（整数化）
    $correctAnswerNumber = isset($question['answer_text']) ? intval($question['answer_text']) : null;

    // AIに質問（正答番号をプロンプトに含める）
    $aiResult = AIHelper::askQuestion($question['question_text'], $userQuestion, $questionId, $correctAnswerNumber);
    
    if (!$aiResult['success']) {
        throw new Exception($aiResult['error']);
    }
    
    $aiResponse = $aiResult['response'];
    $executionTime = $aiResult['execution_time'];
    
    // 質問履歴をデータベースに保存
    $historyId = $db->saveQuestionHistory($questionId, $userQuestion, $aiResponse);

    // 保存に失敗した場合はエラーとして扱う（未確定IDをUIに出さない）
    if (!$historyId) {
        Logger::error("質問履歴の保存に失敗: 問題ID={$questionId}");
        throw new Exception('質問履歴の保存に失敗しました');
    }

    // レスポンスを返す（成功時は必ず数値のhistory_idを含む）
    echo json_encode([
        'success' => true,
        'response' => $aiResponse,
        'execution_time' => $executionTime,
        'question_id' => $questionId,
        'history_id' => $historyId,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
    Logger::info("AI質問完了: 問題ID={$questionId}, 実行時間={$executionTime}ms");
    
} catch (Exception $e) {
    Logger::error("AI質問処理エラー: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
