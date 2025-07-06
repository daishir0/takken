<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Logger.php';

class QuestionParser {
    
    /**
     * 問題文を解析して構造化データに変換
     */
    public static function parseQuestion($questionData) {
        try {
            // 不必要な改行を削除してから処理
            $questionText = self::cleanQuestionText($questionData['question_text']);
            $correctAnswer = intval($questionData['answer_text']);
            
            // 問題文と選択肢を分離
            $parsed = self::extractQuestionAndChoices($questionText);
            
            return [
                'id' => $questionData['id'],
                'filename' => $questionData['filename'],
                'question_number' => $questionData['question_number'],
                'year' => self::extractYear($questionData['filename']),
                'question_title' => $parsed['title'],
                'question_body' => $parsed['body'],
                'choices' => $parsed['choices'],
                'correct_answer' => $correctAnswer,
                'raw_text' => $questionText
            ];
            
        } catch (Exception $e) {
            Logger::error("問題解析エラー: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 問題文から不必要な改行を削除
     */
    private static function cleanQuestionText($questionText) {
        // 不必要な改行を削除し、適切な改行のみ残す
        $cleaned = $questionText;
        
        // 連続する改行を単一の改行に変換
        $cleaned = preg_replace('/\n+/', "\n", $cleaned);
        
        // 行の先頭と末尾の空白を削除
        $lines = explode("\n", $cleaned);
        $lines = array_map('trim', $lines);
        
        // 空行を削除
        $lines = array_filter($lines, function($line) {
            return !empty($line);
        });
        
        // 選択肢の前後で適切な改行を確保
        $result = [];
        foreach ($lines as $line) {
            // 選択肢の番号（1, 2, 3, 4）で始まる行の前に改行を追加
            if (preg_match('/^[1-4]\s+/', $line) && !empty($result)) {
                $result[] = '';
            }
            $result[] = $line;
        }
        
        return implode("\n", $result);
    }
    
    /**
     * 問題文から年度を抽出
     */
    private static function extractYear($filename) {
        if (preg_match('/(\d{4})/', $filename, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }
    
    /**
     * 問題文と選択肢を分離
     */
    private static function extractQuestionAndChoices($questionText) {
        // 問題番号とタイトルを抽出
        $title = '';
        $body = '';
        $choices = [];
        
        // 【問 X】形式のタイトルを抽出
        if (preg_match('/【問\s*(\d+)】\s*(.+?)(?=\n\n|\n1\s|\n2\s|\n3\s|\n4\s|$)/s', $questionText, $matches)) {
            $title = '【問 ' . $matches[1] . '】';
            $body = trim($matches[2]);
        }
        
        // 選択肢を抽出（1, 2, 3, 4で始まる行）
        $lines = explode("\n", $questionText);
        $currentChoice = '';
        $choiceNumber = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // 空行はスキップ
            if (empty($line)) {
                continue;
            }
            
            // 選択肢の開始を検出
            if (preg_match('/^([1-4])\s+(.+)/', $line, $matches)) {
                // 前の選択肢を保存
                if ($choiceNumber > 0 && !empty($currentChoice)) {
                    $choices[$choiceNumber] = self::cleanChoiceText($currentChoice);
                }
                
                $choiceNumber = intval($matches[1]);
                $currentChoice = $matches[2];
            } elseif ($choiceNumber > 0 && !empty($line)) {
                // 選択肢の続きを追加（適切なスペースで連結）
                $currentChoice .= ' ' . $line;
            }
        }
        
        // 最後の選択肢を保存
        if ($choiceNumber > 0 && !empty($currentChoice)) {
            $choices[$choiceNumber] = self::cleanChoiceText($currentChoice);
        }
        
        // 選択肢が4つない場合の処理
        if (count($choices) < 4) {
            Logger::debug("選択肢が4つ未満: " . count($choices) . "個");
            // 簡易的な分割を試行
            $choices = self::fallbackChoiceExtraction($questionText);
        }
        
        return [
            'title' => $title,
            'body' => $body,
            'choices' => $choices
        ];
    }
    
    /**
     * 選択肢テキストをクリーンアップ
     */
    private static function cleanChoiceText($choiceText) {
        // 余分な空白を削除
        $cleaned = preg_replace('/\s+/', ' ', trim($choiceText));
        
        // 特殊文字の正規化
        $cleaned = str_replace(['　'], [' '], $cleaned);
        
        return $cleaned;
    }
    
    /**
     * フォールバック用の選択肢抽出
     */
    private static function fallbackChoiceExtraction($questionText) {
        $choices = [];
        
        // より柔軟な正規表現で選択肢を抽出
        if (preg_match_all('/(?:^|\n)\s*([1-4])\s+(.+?)(?=\n\s*[1-4]\s+|\n\n|$)/s', $questionText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $number = intval($match[1]);
                $text = trim(preg_replace('/\s+/', ' ', $match[2]));
                $choices[$number] = $text;
            }
        }
        
        return $choices;
    }
    
    /**
     * 選択肢をHTMLで表示用にフォーマット
     */
    public static function formatChoicesForDisplay($choices) {
        $html = '';
        for ($i = 1; $i <= 4; $i++) {
            $choiceText = isset($choices[$i]) ? htmlspecialchars($choices[$i]) : "選択肢{$i}が見つかりません";
            $html .= "<div class='choice-item'>";
            $html .= "<button type='button' class='choice-btn' data-choice='{$i}'>";
            $html .= "<span class='choice-number'>{$i}</span>";
            $html .= "<span class='choice-text'>{$choiceText}</span>";
            $html .= "</button>";
            $html .= "</div>";
        }
        return $html;
    }
    
    /**
     * 問題文をHTMLで表示用にフォーマット
     */
    public static function formatQuestionForDisplay($questionBody) {
        // 改行を<br>に変換
        $formatted = nl2br(htmlspecialchars($questionBody));
        
        // 特殊な記号や文字の処理
        $formatted = str_replace('　', '&nbsp;&nbsp;', $formatted);
        
        return $formatted;
    }
}