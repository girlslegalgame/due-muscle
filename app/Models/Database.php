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
            
            // 1. Railwayの環境変数(MYSQL...)を最優先し、なければLaravel用(DB_...)やローカルの初期値を使用します
            $host     = getenv('MYSQLHOST')     ?: (getenv('DB_HOST')     ?: '127.0.0.1');
            $port     = getenv('MYSQLPORT')     ?: (getenv('DB_PORT')     ?: '3306');
            $dbname   = getenv('MYSQLDATABASE') ?: (getenv('DB_DATABASE') ?: 'laravel');
            $user     = getenv('MYSQLUSER')     ?: (getenv('DB_USERNAME') ?: 'root');
            $password = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASSWORD') ?: '');
            $charset  = 'utf8mb4';
            
            // 2. DSN（接続文字列）を組み立てます
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            try {
                // 3. データベースへの接続を実行します
                self::$pdo = new PDO(
                    $dsn, 
                    $user, 
                    $password, 
                    $options
                );
            } catch (PDOException $e) {
                throw new PDOException("データベース接続エラー: " . $e->getMessage(), (int)$e->getCode());
            }
        }
        
        return self::$pdo;
    }
}
