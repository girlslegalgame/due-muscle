<?php namespace Controllers;

require_once __DIR__ . '/../Views/view_helper.php';

class HomeController {
    public function index() {
        renderView('home/index.php');
    }
}