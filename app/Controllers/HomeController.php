<?php namespace Controllers;

class HomeController {
    public function index() {
        // views/layouts/app.php は、コンテンツを挿入する場所がないので、
        // 実際には require_once で直接ビューファイルを読み込むか、
        // より高度なテンプレートエンジンを使うことになります。
        // ここでは簡易的に、後述の layouts/app.php 内に直接Pタグでコンテンツを記述します。
        require_once __DIR__ . '/../Views/layouts/app.php';
        header('Location: /mydecks');
        exit;
        
    }
    
}
