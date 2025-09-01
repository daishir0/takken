document.addEventListener('DOMContentLoaded', function() {
    const choiceBtns = document.querySelectorAll('.choice-btn');
    const answerResult = document.getElementById('answer-result');
    const askAiBtn = document.getElementById('ask-ai-btn');
    const aiQuestionInput = document.getElementById('ai-question-input');
    const aiResponse = document.getElementById('ai-response');
    
    let answered = false;

    // 選択肢ボタンのクリックイベント
    choiceBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            if (answered) return;

            const selectedChoice = parseInt(this.dataset.choice);
            const correctAnswer = questionData.correctAnswer;
            
            // 全ての選択肢を無効化
            choiceBtns.forEach(b => {
                b.style.pointerEvents = 'none';
                b.classList.remove('selected');
            });

            // 選択した選択肢をハイライト
            this.classList.add('selected');

            // 正解・不正解の判定と表示
            const isCorrect = selectedChoice === correctAnswer;
            
            // 回答履歴をサーバーに保存
            saveAnswerHistory(questionData.id, selectedChoice, correctAnswer);
            
            setTimeout(() => {
                showAnswerResult(isCorrect, selectedChoice, correctAnswer);
                answered = true;
            }, 500);
        });

        // ホバーエフェクト
        btn.addEventListener('mouseenter', function() {
            if (!answered) {
                this.style.transform = 'translateY(-2px)';
            }
        });

        btn.addEventListener('mouseleave', function() {
            if (!answered) {
                this.style.transform = 'translateY(0)';
            }
        });
    });

    // AI質問ボタンのクリックイベント
    askAiBtn.addEventListener('click', function() {
        const question = aiQuestionInput.value.trim();
        askAI(question);
    });

    // Enterキーでの送信
    aiQuestionInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            const question = this.value.trim();
            askAI(question);
        }
    });

    // 初期アニメーション
    animateQuestionElements();
    
    // 削除ボタンのイベントリスナー
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-history-btn')) {
            const historyId = e.target.dataset.historyId;
            const historyItem = e.target.closest('.history-item');
            
            if (confirm('この質問履歴を削除しますか？')) {
                deleteQuestionHistory(historyId, historyItem);
            }
        }
    });
});

/**
 * 回答結果を表示
 */
function showAnswerResult(isCorrect, selectedChoice, correctAnswer) {
    const answerResult = document.getElementById('answer-result');
    const resultIcon = answerResult.querySelector('.result-icon');
    const resultText = answerResult.querySelector('.result-text');
    const correctAnswerNumber = document.getElementById('correct-answer-number');
    const correctAnswerText = document.getElementById('correct-answer-text');

    // 選択肢の色分け
    const choiceBtns = document.querySelectorAll('.choice-btn');
    choiceBtns.forEach(btn => {
        const choiceNum = parseInt(btn.dataset.choice);
        if (choiceNum === correctAnswer) {
            btn.classList.add('correct');
        } else if (choiceNum === selectedChoice && !isCorrect) {
            btn.classList.add('incorrect');
        }
    });

    // 結果表示の設定
    if (isCorrect) {
        answerResult.classList.remove('incorrect');
        resultIcon.textContent = '🎉';
        resultText.textContent = '正解です！';
        resultText.style.color = '#48bb78';
    } else {
        answerResult.classList.add('incorrect');
        resultIcon.textContent = '😞';
        resultText.textContent = '不正解です';
        resultText.style.color = '#e53e3e';
    }

    // 正解の選択肢テキストを表示
    correctAnswerText.textContent = questionData.choices[correctAnswer] || '';

    // アニメーション付きで表示
    answerResult.style.display = 'block';
    answerResult.style.opacity = '0';
    answerResult.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        answerResult.style.transition = 'all 0.5s ease';
        answerResult.style.opacity = '1';
        answerResult.style.transform = 'translateY(0)';
    }, 100);

    // 効果音（可能であれば）
    playAnswerSound(isCorrect);
}

/**
 * AIに質問を送信
 */
async function askAI(userQuestion) {
    const askAiBtn = document.getElementById('ask-ai-btn');
    const aiResponse = document.getElementById('ai-response');
    const btnText = askAiBtn.querySelector('.btn-text');
    const loadingSpinner = askAiBtn.querySelector('.loading-spinner');

    // ローディング状態に変更
    askAiBtn.disabled = true;
    btnText.style.display = 'none';
    loadingSpinner.style.display = 'inline-block';
    
    // 前回の回答をクリア
    aiResponse.style.display = 'none';
    aiResponse.innerHTML = '';

    try {
        const response = await fetch('api/ask_ai.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                question_id: questionData.id,
                user_question: userQuestion
            })
        });

        const data = await response.json();

        if (data.success) {
            // 成功時の処理
            aiResponse.innerHTML = `
                <div class="ai-response-header">
                    <span class="ai-icon">🤖</span>
                    <span class="ai-label">AI回答</span>
                    <span class="response-time">(${data.execution_time}ms)</span>
                </div>
                <div class="ai-response-content">${data.response.replace(/\n/g, '<br>')}</div>
            `;
            
            // アニメーション付きで表示
            aiResponse.style.display = 'block';
            aiResponse.style.opacity = '0';
            aiResponse.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                aiResponse.style.transition = 'all 0.3s ease';
                aiResponse.style.opacity = '1';
                aiResponse.style.transform = 'translateY(0)';
            }, 100);

            // 質問入力欄をクリア
            document.getElementById('ai-question-input').value = '';

            // 履歴に追加（保存された履歴IDを反映）
            addToQuestionHistory(userQuestion, data.response, data.timestamp, data.history_id);

            // 成功通知
            showNotification('AI回答を取得しました', 'success');

        } else {
            // エラー時の処理
            aiResponse.innerHTML = `
                <div class="ai-error">
                    <span class="error-icon">❌</span>
                    <span class="error-message">エラー: ${data.error}</span>
                </div>
            `;
            aiResponse.style.display = 'block';
            aiResponse.style.opacity = '1';
            aiResponse.style.transform = 'translateY(0)';

            showNotification('AI回答の取得に失敗しました', 'error');
        }

    } catch (error) {
        console.error('AI質問エラー:', error);
        
        aiResponse.innerHTML = `
            <div class="ai-error">
                <span class="error-icon">❌</span>
                <span class="error-message">通信エラーが発生しました</span>
            </div>
        `;
        aiResponse.style.display = 'block';
        aiResponse.style.opacity = '1';
        aiResponse.style.transform = 'translateY(0)';

        showNotification('通信エラーが発生しました', 'error');

    } finally {
        // ローディング状態を解除
        askAiBtn.disabled = false;
        btnText.style.display = 'inline';
        loadingSpinner.style.display = 'none';
    }
}

/**
 * 問題要素のアニメーション
 */
function animateQuestionElements() {
    const elements = [
        '.question-header',
        '.question-body',
        '.choices-container',
        '.navigation-buttons',
        '.ai-question-section'
    ];

    elements.forEach((selector, index) => {
        const element = document.querySelector(selector);
        if (element) {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                element.style.transition = 'all 0.6s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 150);
        }
    });

    // 選択肢の個別アニメーション
    const choiceItems = document.querySelectorAll('.choice-item');
    choiceItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.4s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, 800 + (index * 100));
    });
}

/**
 * 効果音を再生（ブラウザ対応時のみ）
 */
function playAnswerSound(isCorrect) {
    try {
        // Web Audio APIを使用した簡単な効果音
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        if (isCorrect) {
            // 正解音（高い音）
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
        } else {
            // 不正解音（低い音）
            oscillator.frequency.setValueAtTime(300, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(200, audioContext.currentTime + 0.1);
        }

        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.2);

    } catch (error) {
        // 効果音が再生できない場合は無視
        console.log('効果音の再生に失敗しました:', error);
    }
}

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
    }, 3000);
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

/**
 * 回答履歴を保存
 */
async function saveAnswerHistory(questionId, userAnswer, correctAnswer) {
    try {
        const response = await fetch('api/save_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question_id: questionId,
                user_answer: userAnswer,
                correct_answer: correctAnswer
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('回答履歴保存成功:', data);
            // 統計情報があれば表示（将来的な拡張用）
            if (data.stats) {
                console.log('問題統計:', data.stats);
            }
        } else {
            console.error('回答履歴保存失敗:', data.error);
        }
    } catch (error) {
        console.error('回答履歴保存エラー:', error);
    }
}

/**
 * 質問履歴を削除
 */
async function deleteQuestionHistory(historyId, historyItem) {
    try {
        // 保存直後でID未付与の場合のガード
        if (!/^\d+$/.test(String(historyId))) {
            showNotification('この履歴はまだ保存が完了していません', 'warning');
            return;
        }
        const response = await fetch('api/delete_history.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ history_id: historyId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // アニメーション付きで削除
            historyItem.style.transition = 'all 0.3s ease';
            historyItem.style.opacity = '0';
            historyItem.style.transform = 'translateX(-100%)';
            
            setTimeout(() => {
                historyItem.remove();
                
                // 履歴が空になった場合は履歴セクション全体を非表示
                const historyList = document.querySelector('.history-list');
                if (historyList && historyList.children.length === 0) {
                    const historySection = document.querySelector('.question-history');
                    if (historySection) {
                        historySection.style.display = 'none';
                    }
                }
                
                showNotification('質問履歴を削除しました', 'success');
            }, 300);
        } else {
            showNotification('削除に失敗しました: ' + (data.error || '不明なエラー'), 'error');
        }
    } catch (error) {
        console.error('削除エラー:', error);
        showNotification('通信エラーが発生しました', 'error');
    }
}

/**
 * 質問履歴を動的に追加
 */
function addToQuestionHistory(userQuestion, aiResponse, timestamp, historyId = null) {
    let historySection = document.querySelector('.question-history');
    
    // 履歴セクションが存在しない場合は作成
    if (!historySection) {
        historySection = document.createElement('div');
        historySection.className = 'question-history';
        historySection.innerHTML = `
            <h3>📝 過去の質問履歴</h3>
            <div class="history-list"></div>
        `;
        
        // AI質問セクションの後に挿入
        const aiQuestionSection = document.querySelector('.ai-question-section');
        aiQuestionSection.parentNode.insertBefore(historySection, aiQuestionSection.nextSibling);
    }
    
    const historyList = historySection.querySelector('.history-list');
    
    // 新しい履歴アイテムを作成
    const historyItem = document.createElement('div');
    historyItem.className = 'history-item';
    
    // 日時をフォーマット
    const date = new Date(timestamp);
    const formattedDate = date.getFullYear() + '/' +
                         String(date.getMonth() + 1).padStart(2, '0') + '/' +
                         String(date.getDate()).padStart(2, '0') + ' ' +
                         String(date.getHours()).padStart(2, '0') + ':' +
                         String(date.getMinutes()).padStart(2, '0');
    
    const hasNumericId = historyId && /^\d+$/.test(String(historyId));
    historyItem.innerHTML = `
        <div class="history-header">
            <div class="history-date">${formattedDate}</div>
            ${hasNumericId ? `<button class="delete-history-btn" data-history-id="${historyId}" title="この履歴を削除">×</button>` : ``}
        </div>
        <div class="history-question">
            <strong>質問:</strong> ${userQuestion.replace(/\n/g, '<br>')}
        </div>
        <div class="history-answer">
            <strong>回答:</strong> ${aiResponse.replace(/\n/g, '<br>')}
        </div>
    `;
    
    // 履歴リストの最後に追加（最新が下に来るように）
    historyList.appendChild(historyItem);
    
    // アニメーション付きで表示
    historyItem.style.opacity = '0';
    historyItem.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        historyItem.style.transition = 'all 0.5s ease';
        historyItem.style.opacity = '1';
        historyItem.style.transform = 'translateY(0)';
    }, 100);
    
    // 履歴セクション全体を表示（初回の場合）
    if (historySection.style.display === 'none') {
        historySection.style.display = 'block';
    }
}

// AI回答のスタイル追加
const aiResponseStyle = document.createElement('style');
aiResponseStyle.textContent = `
    .ai-response-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .ai-icon {
        font-size: 1.2rem;
    }
    
    .ai-label {
        font-weight: bold;
        color: #2d3748;
    }
    
    .response-time {
        color: #666;
        font-size: 0.85rem;
        margin-left: auto;
    }
    
    .ai-response-content {
        line-height: 1.8;
        color: #2d3748;
    }
    
    .ai-error {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #e53e3e;
        font-weight: 500;
    }
    
    .error-icon {
        font-size: 1.2rem;
    }
    
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
document.head.appendChild(aiResponseStyle);
