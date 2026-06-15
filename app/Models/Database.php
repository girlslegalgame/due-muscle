<?php namespace Models;

use PDO;
use PDOException;

class Database {
    private static $pdo;
    private static $config;

    /**
     * データベース接続を確立し、PDOインスタンスを返します。
     * 
     * @return PDO
     * @throws PDOException
     */
    public static function connect() {
        if (self::$pdo === null) {
            // 設定ファイルを読み込む（プロジェクトのディレクトリ構造に合わせる）
            $configPath = __DIR__ . '/../config/database.php';
            
            if (!file_exists($configPath)) {
                throw new PDOException("設定ファイルが見つかりません: " . $configPath);
            }
            
            self::$config = require $configPath;
            
            $dsn = "mysql:host=" . self::$config['host'] . 
                   ";dbname=" . self::$config['dbname'] . 
                   ";charset=" . self::$config['charset'];
            
            $options =[
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            try {
                self::$pdo = new PDO(
                    $dsn, 
                    self::$config['user'], 
                    self::$config['password'], 
                    $options
                );
            } catch (PDOException $e) {
                // エラー発生時はログに出力するなどの対応を推奨
                throw new PDOException("データベース接続エラー: " . $e->getMessage(), (int)$e->getCode());
            }
        }
        
        return self::$pdo;
    }
}
