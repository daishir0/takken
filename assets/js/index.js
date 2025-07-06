document.addEventListener('DOMContentLoaded', function() {
    const yearSelect = document.getElementById('year-select');
    const randomYearSelect = document.getElementById('random-year-select');
    const startSequentialBtn = document.getElementById('start-sequential');
    const startRandomBtn = document.getElementById('start-random');

    // 順次出題の年度選択の変更イベント
    yearSelect.addEventListener('change', function() {
        const selectedYear = this.value;
        startSequentialBtn.disabled = !selectedYear;
        
        if (selectedYear) {
            startSequentialBtn.textContent = '順次出題を開始';
            startSequentialBtn.classList.remove('disabled');
        } else {
            startSequentialBtn.textContent = '年度を選択してください';
            startSequentialBtn.classList.add('disabled');
        }
    });

    // ランダム出題の年度選択の変更イベント
    if (randomYearSelect) {
        randomYearSelect.addEventListener('change', function() {
            const selectedYear = this.value;
            startRandomBtn.disabled = !selectedYear;
            
            if (selectedYear) {
                startRandomBtn.textContent = 'ランダム出題を開始';
                startRandomBtn.classList.remove('disabled');
            } else {
                startRandomBtn.textContent = '年度を選択してください';
                startRandomBtn.classList.add('disabled');
            }
        });
    } else {
        console.error('random-year-select要素が見つかりません');
    }

    // 順次出題開始ボタンのクリックイベント
    startSequentialBtn.addEventListener('click', function() {
        const selectedYear = yearSelect.value;
        if (selectedYear) {
            // ローディング状態に変更
            this.disabled = true;
            this.textContent = '問題を読み込み中...';
            
            // 問題ページに遷移
            window.location.href = `question.php?mode=sequential&year=${encodeURIComponent(selectedYear)}&offset=0`;
        }
    });

    // ランダム出題開始ボタンのクリックイベント
    if (startRandomBtn && randomYearSelect) {
        startRandomBtn.addEventListener('click', function() {
            const selectedYear = randomYearSelect.value;
            if (selectedYear) {
                // ローディング状態に変更
                this.disabled = true;
                this.textContent = '問題を読み込み中...';
                
                // 問題ページに遷移（年度パラメータ付き）
                window.location.href = `question.php?mode=random&year=${encodeURIComponent(selectedYear)}`;
            }
        });
    } else {
        console.error('start-random または random-year-select要素が見つかりません');
    }

    // 統計カードのホバーエフェクト
    const statItems = document.querySelectorAll('.stat-item');
    statItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
            this.style.boxShadow = '0 6px 20px rgba(72, 187, 120, 0.2)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });

    // モードカードのアニメーション
    const modeCards = document.querySelectorAll('.mode-card');
    modeCards.forEach((card, index) => {
        // 初期状態を設定
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        // アニメーションを適用
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });

    // 統計グリッドのアニメーション
    const statsGrid = document.querySelector('.stats-grid');
    if (statsGrid) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statItems = entry.target.querySelectorAll('.stat-item');
                    statItems.forEach((item, index) => {
                        setTimeout(() => {
                            item.style.opacity = '1';
                            item.style.transform = 'translateY(0)';
                        }, index * 100);
                    });
                }
            });
        });

        // 初期状態を設定
        const statItems = statsGrid.querySelectorAll('.stat-item');
        statItems.forEach(item => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            item.style.transition = 'all 0.4s ease';
        });

        observer.observe(statsGrid);
    }

    // エラーメッセージの表示
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    if (error) {
        showNotification(error, 'error');
        
        // URLからエラーパラメータを削除
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }

    // 成功メッセージの表示
    const success = urlParams.get('success');
    if (success) {
        showNotification(success, 'success');
        
        // URLから成功パラメータを削除
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
});

/**
 * 通知メッセージを表示
 */
function showNotification(message, type = 'info') {
    // 既存の通知を削除
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    // 通知要素を作成
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${getNotificationIcon(type)}</span>
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;

    // スタイルを設定
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        transform: translateX(100%);
        transition: all 0.3s ease;
        max-width: 400px;
    `;

    // 通知内容のスタイル
    const style = document.createElement('style');
    style.textContent = `
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            margin-left: auto;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-close:hover {
            opacity: 0.7;
        }
    `;
    document.head.appendChild(style);

    // DOMに追加
    document.body.appendChild(notification);

    // アニメーション
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);

    // 自動削除
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

/**
 * 通知アイコンを取得
 */
function getNotificationIcon(type) {
    switch (type) {
        case 'success': return '✅';
        case 'error': return '❌';
        case 'warning': return '⚠️';
        default: return 'ℹ️';
    }
}

/**
 * 通知カラーを取得
 */
function getNotificationColor(type) {
    switch (type) {
        case 'success': return 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)';
        case 'error': return 'linear-gradient(135deg, #e53e3e 0%, #c53030 100%)';
        case 'warning': return 'linear-gradient(135deg, #f6ad55 0%, #ed8936 100%)';
        default: return 'linear-gradient(135deg, #4299e1 0%, #3182ce 100%)';
    }
}