<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Logger.php';

// 認証チェック
if (!Auth::checkBasicAuth()) {
    exit;
}

try {
    $db = new Database();
    $totalQuestions = $db->getTotalQuestions();
    $questionsByYear = $db->getQuestionsByYear();
} catch (Exception $e) {
    Logger::error("データベースエラー: " . $e->getMessage());
    $totalQuestions = 0;
    $questionsByYear = [];
}

Logger::info("トップページアクセス");
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1 class="system-title"><?php echo SYSTEM_NAME; ?></h1>
            <div class="user-info">
                <span>ログイン中: <?php echo htmlspecialchars(Auth::getSessionInfo()['username']); ?></span>
                <a href="logout.php" class="logout-btn">ログアウト</a>
            </div>
        </header>

        <main class="main-content">
            <div class="welcome-section">
                <h2>出題モードを選択してください</h2>
                <p class="stats">総問題数: <strong><?php echo number_format($totalQuestions); ?></strong>問</p>
            </div>

            <div class="mode-selection">
                <div class="mode-card">
                    <h3>📚 順次出題モード</h3>
                    <p>年度ごとに順番に問題を出題します</p>
                    <div class="year-selection">
                        <label for="year-select">年度を選択:</label>
                        <select id="year-select" class="year-select">
                            <option value="">年度を選択してください</option>
                            <?php foreach ($questionsByYear as $yearData): ?>
                                <?php 
                                $year = preg_replace('/\.(pdf)$/', '', $yearData['filename']);
                                $displayYear = str_replace(['(', ')'], ['（', '）'], $year);
                                ?>
                                <option value="<?php echo htmlspecialchars($yearData['filename']); ?>">
                                    <?php echo htmlspecialchars($displayYear); ?> (<?php echo $yearData['count']; ?>問)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="start-btn" id="start-sequential" disabled>
                            順次出題を開始
                        </button>
                    </div>
                </div>

                <div class="mode-card">
                    <h3>🎲 ランダム出題モード</h3>
                    <p>選択した年度からランダムに問題を出題します（正解率の低い問題を優先）</p>
                    <div class="random-selection">
                        <label for="random-year-select">年度を選択:</label>
                        <select id="random-year-select" class="year-select">
                            <option value="">年度を選択してください</option>
                            <?php foreach ($questionsByYear as $yearData): ?>
                                <?php
                                $year = preg_replace('/\.(pdf)$/', '', $yearData['filename']);
                                $displayYear = str_replace(['(', ')'], ['（', '）'], $year);
                                ?>
                                <option value="<?php echo htmlspecialchars($yearData['filename']); ?>">
                                    <?php echo htmlspecialchars($displayYear); ?> (<?php echo $yearData['count']; ?>問)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="start-btn" id="start-random" disabled>
                            年度を選択してください
                        </button>
                    </div>
                </div>
            </div>

            <div class="year-stats">
                <h3>年度別問題数</h3>
                <div class="stats-grid">
                    <?php foreach ($questionsByYear as $yearData): ?>
                        <?php 
                        $year = preg_replace('/\.(pdf)$/', '', $yearData['filename']);
                        $displayYear = str_replace(['(', ')'], ['（', '）'], $year);
                        ?>
                        <div class="stat-item">
                            <span class="year"><?php echo htmlspecialchars($displayYear); ?></span>
                            <span class="count"><?php echo $yearData['count']; ?>問</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; 2025 <?php echo SYSTEM_NAME; ?></p>
        </footer>
    </div>

    <script src="assets/js/index.js"></script>
</body>
</html>