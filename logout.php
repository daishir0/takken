<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Logger.php';

// ログアウト処理
Auth::logout();

// トップページにリダイレクト
header('Location: index.php');
exit;