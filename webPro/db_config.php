<?php
/**
 * Конфигурация подключения к базе данных
 * Соблюдение принципа DRY - единая точка подключения
 */

// Проверяем, определена ли уже константа
if (!defined('ALLOWED_ACCESS')) {
    define('ALLOWED_ACCESS', true);
}

// Параметры подключения
$user = 'u82278';
$pass = '3700374';

try {
    $db = new PDO('mysql:host=localhost;dbname=u82278', $user, $pass,
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    // Логируем ошибку, но не показываем пользователю
    error_log('Database connection error: ' . $e->getMessage());
    die('Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.');
}
?>