<?php
/**
 * ビューをレンダリングするためのヘルパー関数
 * 
 * @param string $viewPath Viewsディレクトリからの相対パス
 * @param array $data ビューに渡すデータ（連想配列）
 */
function renderView($viewPath, $data =[]) {
    // $data のキーを変数として展開（例: ['errors' => []] -> $errors という変数になる）
    extract($data);

    // バッファリング開始：出力されるHTMLを一度変数に格納するため
    ob_start();
    
    // 指定されたビューファイルを読み込む
    require __DIR__ . '/' . $viewPath;
    
    // バッファの内容を取得してクリア
    $content = ob_get_clean();

    // 共通レイアウトを読み込む（$content 変数がこの中で echo される）
    require __DIR__ . '/layouts/app.php';
}
