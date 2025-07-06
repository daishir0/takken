<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Logger.php';

class AIHelper {
    
    /**
     * OpenAI APIに質問を送信
     */
    public static function askQuestion($questionText, $userQuestion, $questionId = null) {
        $startTime = microtime(true);
        
        try {
            // プロンプトを構築
            $prompt = self::buildPrompt($questionText, $userQuestion);
            
            // OpenAI APIリクエスト
            $response = self::callOpenAI($prompt);
            
            $executionTime = round((microtime(true) - $startTime) * 1000);
            
            // ログ出力
            Logger::aiApi($questionId, $userQuestion, $response, $executionTime);
            Logger::info("AI質問処理完了: 問題ID={$questionId}, 実行時間={$executionTime}ms");
            
            return [
                'success' => true,
                'response' => $response,
                'execution_time' => $executionTime
            ];
            
        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000);
            Logger::error("AI質問処理エラー: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime
            ];
        }
    }
    
    /**
     * プロンプトを構築
     */
    private static function buildPrompt($questionText, $userQuestion) {
        // 空の質問の場合はデフォルトの解説を要求
        if (empty(trim($userQuestion))) {
            $userQuestion = "問題を解説しなおして";
        }
        
        $prompt = "以下は宅地建物取引士資格試験の問題です。\n\n";
        $prompt .= "【問題】\n";
        $prompt .= $questionText . "\n\n";
        $prompt .= "【質問】\n";
        $prompt .= $userQuestion . "\n\n";
        $prompt .= "上記の問題について、予備校の先生みたいに、まず端的に答えを教えて。そして、興味を持ってもらえるように実例を含めて、受講者にわかりやすいよう、口語体で説明してください。最後に、文中の宅建の用語について、簡単に説明して";
        
        return $prompt;
    }
    
    /**
     * OpenAI APIを呼び出し
     */
    private static function callOpenAI($prompt) {
        $apiKey = OPENAI_API_KEY;
        
        if (empty($apiKey) || $apiKey === 'your-openai-api-key-here') {
            throw new Exception('OpenAI APIキーが設定されていません');
        }
        
        $data = [
            'model' => OPENAI_MODEL,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'あなたは宅地建物取引士資格試験の専門家です。法律や不動産に関する知識を持ち、受験生にわかりやすく説明することができます。'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.7
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL エラー: ' . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('OpenAI API エラー: HTTP ' . $httpCode . ' - ' . $response);
        }
        
        $responseData = json_decode($response, true);
        
        if (!$responseData || !isset($responseData['choices'][0]['message']['content'])) {
            throw new Exception('OpenAI API レスポンス解析エラー');
        }
        
        return trim($responseData['choices'][0]['message']['content']);
    }
}