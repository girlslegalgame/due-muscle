<?php namespace Controllers;

class HomeController {
    public function index() {
        // HTMLの読み込み（require_once）を削除し、リダイレクト処理のみを行います。
        header('Location: /mydecks');
        exit;
    }
}