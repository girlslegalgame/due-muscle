<?php

// エラー表示設定 (開発時のみON。本番環境ではOFFにすること)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// オートローダーの定義
// Controllers\HomeController のような名前空間付きクラスを自動的に読み込む
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ルーティング設定を読み込む
require_once __DIR__ . '/../app/routes.php';

// リクエストURIの解析 (クエリ文字列を除外)
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ルーティングのマッチングとディスパッチ
if (isset($routes[$method][$requestUri])) {
    $handler = $routes[$method][$requestUri];
    list($controllerName, $actionName) = explode('@', $handler);

    $controllerClass = "Controllers\\" . $controllerName; // 名前空間を含むクラス名

    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();
        if (method_exists($controller, $actionName)) {
            $controller->$actionName();
        } else {
            http_response_code(500);
            echo "Error: Action method '{$actionName}' not found in controller '{$controllerClass}'.";
        }
    } else {
        http_response_code(500);
        echo "Error: Controller class '{$controllerClass}' not found.";
    }
} else {
    http_response_code(404);
    echo "404 Not Found.";
}
