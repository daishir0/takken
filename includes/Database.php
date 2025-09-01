<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Logger.php';

class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 質問履歴テーブルを初期化
            $this->createQuestionHistoryTable();
            
            // 回答履歴テーブルを初期化
            $this->createAnswerHistoryTable();
            
            Logger::log('データベース接続成功', LOG_LEVEL_DEBUG);
        } catch (PDOException $e) {
            Logger::log('データベース接続エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * 全問題数を取得
     */
    public function getTotalQuestions() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM questions");
        return $stmt->fetchColumn();
    }
    
    /**
     * 年度別問題数を取得
     */
    public function getQuestionsByYear() {
        $stmt = $this->pdo->query("SELECT filename, COUNT(*) as count FROM questions GROUP BY filename ORDER BY filename");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 順次出題用：指定年度の問題を取得
     */
    public function getQuestionsByYearSequential($filename, $offset = 0, $limit = 1) {
        $stmt = $this->pdo->prepare("SELECT * FROM questions WHERE filename = ? ORDER BY CAST(question_number AS INTEGER) LIMIT ? OFFSET ?");
        $stmt->execute([$filename, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ランダム出題用：ランダムな問題を取得
     */
    public function getRandomQuestions($limit = 1) {
        $stmt = $this->pdo->prepare("SELECT * FROM questions ORDER BY RANDOM() LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 正解率の低い問題を優先してランダム取得
     */
    public function getRandomQuestionsByCorrectRate($filename, $limit = 1) {
        try {
            $sql = "
                SELECT q.*,
                       COALESCE(stats.total_answers, 0) as total_answers,
                       COALESCE(stats.correct_answers, 0) as correct_answers,
                       CASE
                           WHEN COALESCE(stats.total_answers, 0) = 0 THEN 0
                           ELSE CAST(stats.correct_answers AS FLOAT) / stats.total_answers
                       END as correct_rate
                FROM questions q
                LEFT JOIN (
                    SELECT question_id,
                           COUNT(*) as total_answers,
                           SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
                    FROM answer_history
                    GROUP BY question_id
                ) stats ON q.id = stats.question_id
                WHERE q.filename = ?
                ORDER BY correct_rate ASC, RANDOM()
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$filename, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::log('正解率ベースランダム取得エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            // フォールバック：通常のランダム取得
            return $this->getQuestionsByYearSequential($filename, 0, $limit);
        }
    }
    
    /**
     * 特定の問題を取得
     */
    public function getQuestionById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM questions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * AI質問履歴を保存
     */
    public function saveQuestionHistory($question_id, $user_question, $ai_response) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO question_history (question_id, user_question, ai_response, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$question_id, $user_question, $ai_response, date('Y-m-d H:i:s')]);
            $id = (int)$this->pdo->lastInsertId();
            Logger::log("AI質問履歴保存: 問題ID={$question_id}", LOG_LEVEL_INFO);
            return $id ?: false;
        } catch (PDOException $e) {
            Logger::log('AI質問履歴保存エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * AI質問履歴を取得
     */
    public function getQuestionHistory($question_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM question_history WHERE question_id = ? ORDER BY created_at ASC");
            $stmt->execute([$question_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::log('AI質問履歴取得エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * AI質問履歴を削除
     */
    public function deleteQuestionHistory($history_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM question_history WHERE id = ?");
            $result = $stmt->execute([$history_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                Logger::log("質問履歴削除成功: ID={$history_id}", LOG_LEVEL_INFO);
                return true;
            } else {
                Logger::log("質問履歴削除失敗: 該当するレコードが見つかりません ID={$history_id}", LOG_LEVEL_ERROR);
                return false;
            }
        } catch (PDOException $e) {
            Logger::log('質問履歴削除エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * 回答履歴を保存
     */
    public function saveAnswerHistory($question_id, $user_answer, $is_correct) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO answer_history (question_id, user_answer, is_correct, answered_at) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$question_id, $user_answer, $is_correct ? 1 : 0, date('Y-m-d H:i:s')]);
            Logger::log("回答履歴保存: 問題ID={$question_id}, 回答={$user_answer}, 正解=" . ($is_correct ? 'true' : 'false'), LOG_LEVEL_INFO);
            return $result;
        } catch (PDOException $e) {
            Logger::log('回答履歴保存エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * 問題の統計情報を取得
     */
    public function getQuestionStats($question_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_answers,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                    CASE
                        WHEN COUNT(*) = 0 THEN 0
                        ELSE CAST(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS FLOAT) / COUNT(*)
                    END as correct_rate
                FROM answer_history
                WHERE question_id = ?
            ");
            $stmt->execute([$question_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::log('問題統計取得エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            return ['total_answers' => 0, 'correct_answers' => 0, 'correct_rate' => 0];
        }
    }
    
    /**
     * 回答履歴テーブルを作成
     */
    public function createAnswerHistoryTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS answer_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question_id INTEGER NOT NULL,
                user_answer INTEGER NOT NULL,
                is_correct BOOLEAN NOT NULL,
                answered_at DATETIME NOT NULL,
                FOREIGN KEY (question_id) REFERENCES questions(id)
            )";
            $this->pdo->exec($sql);
            Logger::log('回答履歴テーブル初期化完了', LOG_LEVEL_DEBUG);
        } catch (PDOException $e) {
            Logger::log('回答履歴テーブル作成エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * 質問履歴テーブルを作成
     */
    public function createQuestionHistoryTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS question_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question_id INTEGER NOT NULL,
                user_question TEXT NOT NULL,
                ai_response TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (question_id) REFERENCES questions(id)
            )";
            $this->pdo->exec($sql);
            Logger::log('質問履歴テーブル初期化完了', LOG_LEVEL_DEBUG);
        } catch (PDOException $e) {
            Logger::log('質問履歴テーブル作成エラー: ' . $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
}
