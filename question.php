<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Logger.php';
require_once __DIR__ . '/includes/QuestionParser.php';
require_once __DIR__ . '/includes/AIHelper.php';

// 認証チェック
if (!Auth::checkBasicAuth()) {
    exit;
}

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $db = new Database();
    $question = null;
    $mode = $_GET['mode'] ?? '';
    $questionId = $_GET['id'] ?? '';
    $year = $_GET['year'] ?? '';
    $offset = intval($_GET['offset'] ?? 0);

    // 問題を取得
    if ($mode === 'sequential' && !empty($year)) {
        $questions = $db->getQuestionsByYearSequential($year, $offset, 1);
        $question = $questions[0] ?? null;
        $_SESSION['current_mode'] = 'sequential';
        $_SESSION['current_year'] = $year;
        $_SESSION['current_offset'] = $offset;
    } elseif ($mode === 'random' && !empty($year)) {
        $questions = $db->getRandomQuestionsByCorrectRate($year, 1);
        $question = $questions[0] ?? null;
        $_SESSION['current_mode'] = 'random';
        $_SESSION['current_year'] = $year;
    } elseif (!empty($questionId)) {
        $question = $db->getQuestionById($questionId);
    }

    if (!$question) {
        throw new Exception('問題が見つかりません');
    }

    // 問題を解析
    $parsedQuestion = QuestionParser::parseQuestion($question);
    
    // AI質問履歴を取得
    $questionHistory = $db->getQuestionHistory($question['id']);

    Logger::info("問題表示: ID={$question['id']}, モード={$mode}");

} catch (Exception $e) {
    Logger::error("問題表示エラー: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('問題の取得に失敗しました'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $parsedQuestion['question_title']; ?> - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1 class="system-title">
                <a href="index.php"><?php echo SYSTEM_NAME; ?></a>
            </h1>
            <div class="question-info">
                <span class="year-badge"><?php echo htmlspecialchars($parsedQuestion['year']); ?>年度</span>
                <span class="mode-badge"><?php echo $mode === 'sequential' ? '順次出題' : 'ランダム出題'; ?></span>
            </div>
        </header>

        <main class="main-content">
            <div class="question-container">
                <div class="question-body">
                    <span class="question-number"><?php echo htmlspecialchars($parsedQuestion['question_title']); ?></span>
                    <?php echo QuestionParser::formatQuestionForDisplay($parsedQuestion['question_body']); ?>
                </div>

                <div class="choices-container" id="choices-container">
                    <?php echo QuestionParser::formatChoicesForDisplay($parsedQuestion['choices']); ?>
                </div>

                <div class="answer-result" id="answer-result" style="display: none;">
                    <div class="result-content">
                        <div class="result-icon"></div>
                        <div class="result-text"></div>
                        <div class="correct-answer-info">
                            正解: <span id="correct-answer-number"><?php echo $parsedQuestion['correct_answer']; ?></span>番
                            <span id="correct-answer-text"></span>
                        </div>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <?php if ($mode === 'sequential'): ?>
                        <?php if ($offset > 0): ?>
                            <a href="question.php?mode=sequential&year=<?php echo urlencode($year); ?>&offset=<?php echo $offset - 1; ?>" class="nav-btn prev-btn">前の問題</a>
                        <?php endif; ?>
                        <a href="question.php?mode=sequential&year=<?php echo urlencode($year); ?>&offset=<?php echo $offset + 1; ?>" class="nav-btn next-btn">次の問題</a>
                    <?php else: ?>
                        <a href="question.php?mode=random&year=<?php echo urlencode($year); ?>" class="nav-btn next-btn">次の問題</a>
                    <?php endif; ?>
                    <a href="index.php" class="nav-btn home-btn">トップに戻る</a>
                </div>
            </div>

            <div class="ai-question-section">
                <h3>💡 AIに質問する</h3>
                <div class="ai-question-form">
                    <textarea id="ai-question-input" placeholder="この問題について質問があれば入力してください。空欄で送信すると自動で解説します。" rows="3"></textarea>
                    <button type="button" id="ask-ai-btn" class="ask-ai-btn">
                        <span class="btn-text">質問する</span>
                        <span class="loading-spinner" style="display: none;">🔄</span>
                    </button>
                </div>
                <div id="ai-response" class="ai-response" style="display: none;"></div>
            </div>

            <?php if (!empty($questionHistory)): ?>
            <div class="question-history">
                <h3>📝 過去の質問履歴</h3>
                <div class="history-list">
                    <?php foreach ($questionHistory as $history): ?>
                        <div class="history-item" data-history-id="<?php echo $history['id']; ?>">
                            <div class="history-header">
                                <div class="history-date"><?php echo date('Y/m/d H:i', strtotime($history['created_at'])); ?></div>
                                <button class="delete-history-btn" data-history-id="<?php echo $history['id']; ?>" title="この履歴を削除">×</button>
                            </div>
                            <div class="history-question">
                                <strong>質問:</strong> <?php echo htmlspecialchars($history['user_question']); ?>
                            </div>
                            <div class="history-answer">
                                <strong>回答:</strong> <?php echo nl2br(htmlspecialchars($history['ai_response'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // 問題データをJavaScriptに渡す
        const questionData = {
            id: <?php echo $parsedQuestion['id']; ?>,
            correctAnswer: <?php echo $parsedQuestion['correct_answer']; ?>,
            choices: <?php echo json_encode($parsedQuestion['choices'], JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
    <script src="assets/js/question.js"></script>
</body>
</html>