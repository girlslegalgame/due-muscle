<?php

$routes = [
    'GET' => [
        '/' => 'HomeController@index',
        '/mydecks' => 'DeckController@myDecks',
        '/search' => 'DeckController@search',
        '/decks/new' => 'DeckController@create',
        '/decks/edit' => 'DeckController@edit',
        '/account' => 'AuthController@showAccountForm',
        '/decks/playtest' => 'DeckController@playtest',

        '/register' => 'AuthController@showRegisterForm',
        '/register/verify' => 'AuthController@showVerifyForm', // 新規登録用：認証コード入力画面の表示
        '/login' => 'AuthController@showLoginForm',
        '/login/verify' => 'AuthController@showLoginVerifyForm', // ★追加：ログイン用：認証コード入力画面の表示

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

        '/api/cards/help-search' => 'CardController@helpSearchApi',
        '/api/cards/help-detail' => 'CardController@helpDetailApi',
        '/api/master-data-extended' => 'CardController@masterDataExtendedApi',
                
        '/help' => 'DeckController@help',
        '/help/search' => 'DeckController@helpSearch',
    ],
    'POST' => [
        '/register' => 'AuthController@sendVerificationCode', // 新規登録：一時登録と認証コード送信
        '/register/verify' => 'AuthController@verifyRegister', // ★変更：DBへ本登録するメソッド名を verifyRegister に統一
        '/login' => 'AuthController@login',                    // ログイン：認証コード送信処理へ
        '/login/verify' => 'AuthController@verifyLogin',       // ★追加：ログイン：入力されたコードの検証処理
        '/logout' => 'AuthController@logout',
        '/account' => 'AuthController@updateAccount',
        
        // APIエンドポイント (POSTリクエスト)
        '/api/decks' => 'DeckController@storeDeckApi',
        '/api/decks/copy' => 'DeckController@copyDeckApi',
        '/api/cards/help-update' => 'CardController@helpUpdateApi',
    ],
    'PUT' => [
        '/api/decks' => 'DeckController@updateDeckApi',
        '/api/decks/set_public' => 'DeckController@setDeckPublicApi',
        '/decks/edit' => 'DeckController@edit',
    ],
    'DELETE' => [
        '/api/decks' => 'DeckController@deleteDeckApi',
    ],
];