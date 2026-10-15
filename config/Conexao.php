<?php

/**
 * Classe Conexao (Singleton de PDO).
 * Em caso de falha, lança PDOException para quem chamou tratar
 * (assim nenhuma mensagem de erro é impressa no meio das páginas/JSON).
 */
class Conexao {
    private static $host = 'localhost';
    private static $dbname = 'jogo_pokemon'; 
    private static $username = 'root';
    private static $password = '';
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4",
                self::$username,
                self::$password
            );
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return self::$pdo;
    }
}

class_alias('Conexao', 'Database');
