<?php
require_once __DIR__ . '/../config.php';

class Logger {
    
    /**
     * ログを出力
     */
    public static function log($message, $level = LOG_LEVEL_INFO) {
        if (!DEBUG_MODE && $level === LOG_LEVEL_DEBUG) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // ログディレクトリが存在しない場合は作成
        if (!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR, 0755, true);
        }
        
        // 日付別ログファイル
        $logFile = LOG_DIR . '/system_' . date('Y-m-d') . '.log';
        
        // ログファイルに書き込み
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // デバッグモードの場合はエラーログにも出力
        if (DEBUG_MODE) {
            error_log($logMessage);
        }
    }
    
    /**
     * エラーログ
     */
    public static function error($message) {
        self::log($message, LOG_LEVEL_ERROR);
    }
    
    /**
     * 情報ログ
     */
    public static function info($message) {
        self::log($message, LOG_LEVEL_INFO);
    }
    
    /**
     * デバッグログ
     */
    public static function debug($message) {
        self::log($message, LOG_LEVEL_DEBUG);
    }
    
    /**
     * アクセスログ
     */
    public static function access($ip, $userAgent, $requestUri) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] ACCESS IP:{$ip} UA:{$userAgent} URI:{$requestUri}" . PHP_EOL;
        
        if (!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR, 0755, true);
        }
        
        $logFile = LOG_DIR . '/access_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * AI APIログ
     */
    public static function aiApi($questionId, $userQuestion, $response, $executionTime) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] AI_API QuestionID:{$questionId} UserQ:{$userQuestion} ResponseLength:" . strlen($response) . " Time:{$executionTime}ms" . PHP_EOL;
        
        if (!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR, 0755, true);
        }
        
        $logFile = LOG_DIR . '/ai_api_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}