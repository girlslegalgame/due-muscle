<?php

$routes = [
    'GET' => [
        '/' => 'HomeController@index',
        '/mydecks' => 'DeckController@myDecks',
        '/search' => 'DeckController@search',
        '/decks/new' => 'DeckController@create',
        '/decks/edit' => 'DeckController@edit',
        '/account' => 'AuthController@showAccountForm', // ★追加：アカウント設定表示


        '/register' => 'AuthController@showRegisterForm', // 新規追加
        '/register/verify' => 'AuthController@showVerifyForm', // ★追加：認証コード入力画面の表示
        '/login' => 'AuthController@showLoginForm',       // 新規追加

        // APIエンドポイント (GETリクエスト)
        '/api/mydecks' => 'DeckController@myDecksApi',
        '/api/cards' => 'CardController@cardsApi',
        '/api/cards/versions' => 'CardController@cardVersionsApi',
        '/api/cards/combination' => 'CardController@cardCombinationApi',    
        '/api/decks' => 'DeckController@publicDecksApi',
        '/api/decks/view' => 'DeckController@viewDeckApi',

        // マスターデータAPI
        '/api/master/formats' => 'MasterDataController@formatsApi',
        '/api/master/civilizations' => 'MasterDataController@civilizationsApi',
        '/api/master/races' => 'MasterDataController@racesApi',
        '/api/master/cardtypes' => 'MasterDataController@cardTypesApi',
        '/api/master/characteristics' => 'MasterDataController@characteristicsApi',
        '/api/master/abilities' => 'MasterDataController@abilitiesApi',
        '/api/master/themes' => 'MasterDataController@themesApi',
        '/api/master-data' => 'CardController@masterDataApi',
    ],
    'POST' => [
        '/register' => 'AuthController@sendVerificationCode', // ★修正：最初に登録情報を受け取ってコードを送信
        '/register/verify' => 'AuthController@register',      // ★修正：認証コードを検証して本登録する処理        '/login' => 'AuthController@login',       // 新規追加
        '/login' => 'AuthController@login',
        '/logout' => 'AuthController@logout',     // 新規追加 (POSTリクエストでログアウト処理)
        '/account' => 'AuthController@updateAccount', // ★追加：アカウント情報の更新処理
        // APIエンドポイント (POSTリクエスト)
        '/api/decks' => 'DeckController@storeDeckApi',
        '/api/decks/copy' => 'DeckController@copyDeckApi',
    ],
    'PUT' => [
        '/api/decks' => 'DeckController@updateDeckApi',
        '/api/decks/set_public' => 'DeckController@setDeckPublicApi',
        '/decks/edit' => 'DeckController@edit', // 追加：編集画面用
    ],
    'DELETE' => [
            '/api/decks' => 'DeckController@deleteDeckApi',
    ],
];
