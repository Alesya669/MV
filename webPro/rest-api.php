<?php
/**
 * REST API для винилового магазина "33 Forever"
 * Поддерживает JSON и XML форматы
 * 
 * POST /rest-api.php - регистрация нового пользователя
 * PUT /rest-api.php?id=X - обновление данных авторизованного пользователя
 * GET /rest-api.php?id=X - получение данных пользователя
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Подключение к БД
require_once 'db_config.php';

// Запуск сессии для авторизации
session_start();

// Определяем формат ответа (JSON или XML)
function detectOutputFormat() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/xml') !== false || strpos($accept, 'text/xml') !== false) {
        return 'xml';
    }
    return 'json';
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    $format = detectOutputFormat();
    
    if ($format === 'xml') {
        header('Content-Type: application/xml; charset=UTF-8');
        echo arrayToXml($data, 'response');
    } else {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    exit();
}

function arrayToXml($data, $rootNodeName = 'response') {
    $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$rootNodeName}></{$rootNodeName}>");
    arrayToXmlRecursive($data, $xml);
    return $xml->asXML();
}

function arrayToXmlRecursive($data, &$xml) {
    foreach ($data as $key => $value) {
        if (is_numeric($key)) {
            $key = 'item';
        }
        if (is_array($value)) {
            $subnode = $xml->addChild($key);
            arrayToXmlRecursive($value, $subnode);
        } else {
            $xml->addChild($key, htmlspecialchars((string)$value));
        }
    }
}

function sendError($message, $statusCode = 400) {
    sendResponse(['error' => $message, 'status' => $statusCode], $statusCode);
}

// Валидация данных (переиспользуем из index.php)
function validateUserData($data, &$errors) {
    $errors = [];
    
    // Валидация ФИО
    if (empty($data['fullName'])) {
        $errors['fullName'] = 'ФИО обязательно для заполнения';
    } elseif (strlen($data['fullName']) > 150) {
        $errors['fullName'] = 'ФИО не должно превышать 150 символов';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $data['fullName'])) {
        $errors['fullName'] = 'ФИО может содержать только буквы, пробелы и дефисы';
    }
    
    // Валидация email
    if (empty($data['email'])) {
        $errors['email'] = 'Email обязателен для заполнения';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email адрес';
    } elseif (strlen($data['email']) > 100) {
        $errors['email'] = 'Email не должен превышать 100 символов';
    }
    
    // Валидация телефона
    if (empty($data['phone'])) {
        $errors['phone'] = 'Телефон обязателен для заполнения';
    } else {
        $digitsOnly = preg_replace('/[^0-9]/', '', $data['phone']);
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $data['phone'])) {
            $errors['phone'] = 'Телефон содержит недопустимые символы';
        } elseif (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 11) {
            $errors['phone'] = 'Телефон должен содержать 10 или 11 цифр';
        }
    }
    
    // Валидация сообщения (биография)
    if (empty($data['message'])) {
        $errors['message'] = 'Сообщение обязательно для заполнения';
    } elseif (strlen($data['message']) < 4) {
        $errors['message'] = 'Сообщение должно содержать не менее 4 символов';
    } elseif (strlen($data['message']) > 65535) {
        $errors['message'] = 'Сообщение слишком длинное';
    }
    
    return empty($errors);
}

// Получение данных из запроса (JSON или XML)
function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $input = file_get_contents('php://input');
    
    if (strpos($contentType, 'application/json') !== false) {
        return json_decode($input, true);
    } elseif (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) {
        $xml = simplexml_load_string($input);
        return json_decode(json_encode($xml), true);
    }
    
    // Если не JSON и не XML, пробуем как form-data
    return $_POST;
}

// Проверка авторизации (сессия или Basic Auth)
function checkAuth(&$userId) {
    // Проверяем сессию
    if (!empty($_SESSION['login']) && !empty($_SESSION['uid'])) {
        $userId = $_SESSION['uid'];
        return true;
    }
    
    // Проверяем HTTP Basic Auth
    if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
        global $db;
        $stmt = $db->prepare("SELECT id FROM application WHERE login = ? AND pass_hash = MD5(?)");
        $stmt->execute([$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']]);
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userId = $user['id'];
            $_SESSION['login'] = $_SERVER['PHP_AUTH_USER'];
            $_SESSION['uid'] = $user['id'];
            return true;
        }
    }
    
    return false;
}

// Получение пользователя по ID
function getUserById($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT id, fio, email, phone, birth_date, gender, bio, contract, login FROM application WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Получаем языки пользователя
            $langStmt = $db->prepare("SELECT l.code FROM languages l JOIN app_languages al ON l.id = al.lang_id WHERE al.app_id = ?");
            $langStmt->execute([$id]);
            $user['languages'] = $langStmt->fetchAll(PDO::FETCH_COLUMN);
            $user['profile_url'] = "https://{$_SERVER['HTTP_HOST']}/profile.php?id={$id}";
        }
        
        return $user;
    } catch (PDOException $e) {
        error_log('Get user error: ' . $e->getMessage());
        return null;
    }
}

// Маршрутизация
$method = $_SERVER['REQUEST_METHOD'];
$requestId = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            // Получение данных пользователя
            if (!$requestId) {
                sendError('GET запрос требует параметр id', 400);
            }
            
            $user = getUserById($requestId);
            if (!$user) {
                sendError('Пользователь не найден', 404);
            }
            
            sendResponse($user);
            break;
            
        case 'POST':
            // Регистрация нового пользователя
            $data = getRequestData();
            
            if (!$data) {
                sendError('Невалидные входные данные. Ожидается JSON или XML', 400);
            }
            
            $errors = [];
            if (!validateUserData($data, $errors)) {
                sendResponse(['errors' => $errors, 'message' => 'Ошибки валидации'], 400);
            }
            
            // Генерация логина и пароля
            $login = substr(uniqid('user_'), 0, 10);
            $password = substr(md5(uniqid() . rand()), 0, 8);
            $passHash = md5($password);
            
            try {
                $db->beginTransaction();
                
                // Вставляем нового пользователя
                $stmt = $db->prepare("INSERT INTO application (fio, phone, email, bio, login, pass_hash) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['fullName'],
                    $data['phone'],
                    $data['email'],
                    $data['message'],
                    $login,
                    $passHash
                ]);
                
                $app_id = $db->lastInsertId();
                
                // Если есть языки, добавляем их (опционально)
                if (!empty($data['languages']) && is_array($data['languages'])) {
                    $lang_map = [];
                    $langStmt = $db->query("SELECT id, code FROM languages");
                    while ($row = $langStmt->fetch(PDO::FETCH_ASSOC)) {
                        $lang_map[$row['code']] = $row['id'];
                    }
                    
                    $insertLang = $db->prepare("INSERT INTO app_languages (app_id, lang_id) VALUES (?, ?)");
                    foreach ($data['languages'] as $lang) {
                        if (isset($lang_map[$lang])) {
                            $insertLang->execute([$app_id, $lang_map[$lang]]);
                        }
                    }
                }
                
                $db->commit();
                
                // Формируем ответ
                $profileUrl = "https://{$_SERVER['HTTP_HOST']}/profile.php?id={$app_id}";
                
                sendResponse([
                    'success' => true,
                    'message' => 'Пользователь успешно зарегистрирован',
                    'login' => $login,
                    'password' => $password,
                    'profile_url' => $profileUrl,
                    'user_id' => $app_id
                ], 201);
                
            } catch (PDOException $e) {
                $db->rollBack();
                error_log('Registration error: ' . $e->getMessage());
                sendError('Ошибка сохранения данных. Пожалуйста, попробуйте позже.', 500);
            }
            break;
            
        case 'PUT':
            // Обновление данных авторизованного пользователя
            if (!$requestId) {
                sendError('PUT запрос требует параметр id', 400);
            }
            
            $userId = null;
            if (!checkAuth($userId) || $userId != $requestId) {
                sendError('Необходима авторизация для редактирования данных', 401);
            }
            
            $data = getRequestData();
            if (!$data) {
                sendError('Невалидные входные данные. Ожидается JSON или XML', 400);
            }
            
            $errors = [];
            validateUserData($data, $errors);
            
            // Для обновления не все поля обязательны
            unset($errors['fullName']); // ФИО можно не обновлять
            unset($errors['email']);    // Email можно не обновлять
            
            if (!empty($errors)) {
                sendResponse(['errors' => $errors, 'message' => 'Ошибки валидации'], 400);
            }
            
            try {
                // Формируем UPDATE запрос только для переданных полей
                $updateFields = [];
                $params = [];
                
                if (!empty($data['fullName'])) {
                    $updateFields[] = "fio = ?";
                    $params[] = $data['fullName'];
                }
                if (!empty($data['email'])) {
                    $updateFields[] = "email = ?";
                    $params[] = $data['email'];
                }
                if (!empty($data['phone'])) {
                    $updateFields[] = "phone = ?";
                    $params[] = $data['phone'];
                }
                if (!empty($data['message'])) {
                    $updateFields[] = "bio = ?";
                    $params[] = $data['message'];
                }
                
                if (empty($updateFields)) {
                    sendResponse(['success' => true, 'message' => 'Нет данных для обновления'], 200);
                }
                
                $params[] = $requestId;
                $sql = "UPDATE application SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                sendResponse([
                    'success' => true,
                    'message' => 'Данные успешно обновлены'
                ], 200);
                
            } catch (PDOException $e) {
                error_log('Update error: ' . $e->getMessage());
                sendError('Ошибка обновления данных', 500);
            }
            break;
            
        default:
            sendError('Метод не поддерживается', 405);
            break;
    }
} catch (Exception $e) {
    error_log('API error: ' . $e->getMessage());
    sendError('Внутренняя ошибка сервера', 500);
}
?>