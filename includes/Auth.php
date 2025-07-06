<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Logger.php';

class Auth {
    
    /**
     * Basic認証をチェック
     */
    public static function checkBasicAuth() {
        // セッション開始
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 既にログイン済みの場合
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            // セッションタイムアウトチェック
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
                self::logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
            return true;
        }
        
        // Basic認証ヘッダーをチェック
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            self::requireAuth();
            return false;
        }
        
        // 認証情報を検証
        $username_match = $_SERVER['PHP_AUTH_USER'] === AUTH_USERNAME;
        $password_match = false;
        
        // デバッグログ
        Logger::debug("認証試行: ユーザー名='" . ($_SERVER['PHP_AUTH_USER'] ?? 'null') . "', パスワード長=" . strlen($_SERVER['PHP_AUTH_PW'] ?? ''));
        Logger::debug("設定値: ユーザー名='" . AUTH_USERNAME . "', パスワード長=" . strlen(AUTH_PASSWORD));
        
        // パスワードが空文字列の場合の特別処理
        if (AUTH_PASSWORD === '') {
            // 設定でパスワードが空の場合、入力パスワードも空であれば認証成功
            $password_match = $_SERVER['PHP_AUTH_PW'] === '';
            Logger::debug("空パスワード認証: 入力パスワード='" . ($_SERVER['PHP_AUTH_PW'] ?? 'null') . "', 一致=" . ($password_match ? 'true' : 'false'));
        } else {
            // 通常のパスワード比較
            $password_match = $_SERVER['PHP_AUTH_PW'] === AUTH_PASSWORD;
            Logger::debug("通常パスワード認証: 一致=" . ($password_match ? 'true' : 'false'));
        }
        
        Logger::debug("認証結果: ユーザー名一致=" . ($username_match ? 'true' : 'false') . ", パスワード一致=" . ($password_match ? 'true' : 'false'));
        
        if ($username_match && $password_match) {
            // 認証成功
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = AUTH_USERNAME;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // アクセスログ
            Logger::access(
                self::getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REQUEST_URI'] ?? ''
            );
            
            Logger::info("ユーザーログイン成功: " . AUTH_USERNAME);
            return true;
        } else {
            // 認証失敗
            Logger::info("ログイン失敗: " . ($_SERVER['PHP_AUTH_USER'] ?? 'unknown'));
            self::requireAuth();
            return false;
        }
    }
    
    /**
     * Basic認証を要求
     */
    private static function requireAuth() {
        header('WWW-Authenticate: Basic realm="' . SYSTEM_NAME . '"');
        header('HTTP/1.0 401 Unauthorized');
        echo '認証が必要です。';
        exit;
    }
    
    /**
     * ログアウト
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        Logger::info("ユーザーログアウト: " . ($_SESSION['username'] ?? 'unknown'));
        
        // セッションを破棄
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * 認証済みかチェック
     */
    public static function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    
    /**
     * クライアントIPアドレスを取得
     */
    private static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * セッション情報を取得
     */
    public static function getSessionInfo() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return [
            'username' => $_SESSION['username'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null
        ];
    }
}