<?php

$host = 'localhost';
$dbname = 'jogo_pokemon'; // Nome do banco do seu projeto (conforme script_pokemon.sql)
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erro na conexão: " . $e->getMessage();
}

/**
 * Classe Database para compatibilidade com o Autoload e POO
 */
class Database {
    private static $host = 'localhost';
    private static $dbname = 'jogo_pokemon';
    private static $username = 'root';
    private static $password = '';
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO("mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4", self::$username, self::$password);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                echo "Erro na conexão: " . $e->getMessage();
            }
        }
        return self::$pdo;
    }
}
