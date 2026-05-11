<?php
/**
 * REST API для винилового магазина "33 Forever"
 * Любимые исполнители 90-х
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_config.php';
session_start();

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function sendError($message, $statusCode = 400) {
    sendResponse(['success' => false, 'error' => $message], $statusCode);
}

function validateUserData($data, &$errors) {
    $errors = [];
    
    if (empty($data['fullName'])) {
        $errors['fullName'] = 'ФИО обязательно для заполнения';
    } elseif (strlen($data['fullName']) > 150) {
        $errors['fullName'] = 'ФИО не должно превышать 150 символов';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $data['fullName'])) {
        $errors['fullName'] = 'ФИО может содержать только буквы, пробелы и дефисы';
    }
    
    if (empty($data['email'])) {
        $errors['email'] = 'Email обязателен для заполнения';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email адрес';
    } elseif (strlen($data['email']) > 100) {
        $errors['email'] = 'Email не должен превышать 100 символов';
    }
    
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
    
    if (empty($data['message'])) {
        $errors['message'] = 'Сообщение обязательно для заполнения';
    } elseif (strlen($data['message']) < 4) {
        $errors['message'] = 'Сообщение должно содержать не менее 4 символов';
    } elseif (strlen($data['message']) > 65535) {
        $errors['message'] = 'Сообщение слишком длинное';
    }
    
    if (empty($data['artists']) || !is_array($data['artists']) || count($data['artists']) == 0) {
        $errors['artists'] = 'Выберите хотя бы одного любимого исполнителя';
    }
    
    return empty($errors);
}

function checkAuth(&$userId) {
    if (!empty($_SESSION['login']) && !empty($_SESSION['uid'])) {
        $userId = $_SESSION['uid'];
        return true;
    }
    
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

$method = $_SERVER['REQUEST_METHOD'];
$requestId = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            if (!$requestId) {
                sendError('GET запрос требует параметр id', 400);
            }
            $stmt = $db->prepare("SELECT id, fio, email, phone, bio FROM application WHERE id = ?");
            $stmt->execute([$requestId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $artistStmt = $db->prepare("SELECT ar.code FROM artists ar JOIN user_artists ua ON ar.id = ua.artist_id WHERE ua.user_id = ?");
                $artistStmt->execute([$requestId]);
                $user['artists'] = $artistStmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            if (!$user) {
                sendError('Пользователь не найден', 404);
            }
            sendResponse(['success' => true, 'user' => $user]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                sendError('Невалидные входные данные. Ожидается JSON', 400);
            }
            
            $errors = [];
            if (!validateUserData($input, $errors)) {
                sendResponse(['success' => false, 'errors' => $errors], 400);
            }
            
            $login = 'user_' . substr(uniqid(), 0, 8);
            $password = substr(md5(uniqid() . rand()), 0, 8);
            $passHash = md5($password);
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO application (fio, phone, email, bio, login, pass_hash) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $input['fullName'],
                    $input['phone'],
                    $input['email'],
                    $input['message'],
                    $login,
                    $passHash
                ]);
                
                $app_id = $db->lastInsertId();
                
                // Сохраняем исполнителей
                $artist_map = [];
                $artistStmt = $db->query("SELECT id, code FROM artists");
                while ($row = $artistStmt->fetch(PDO::FETCH_ASSOC)) {
                    $artist_map[$row['code']] = $row['id'];
                }
                
                $insertArtist = $db->prepare("INSERT INTO user_artists (user_id, artist_id) VALUES (?, ?)");
                foreach ($input['artists'] as $artist) {
                    if (isset($artist_map[$artist])) {
                        $insertArtist->execute([$app_id, $artist_map[$artist]]);
                    }
                }
                
                $db->commit();
                
                $profileUrl = "https://{$_SERVER['HTTP_HOST']}/profile.php?id={$app_id}";
                
                sendResponse([
                    'success' => true,
                    'message' => 'Регистрация успешна!',
                    'login' => $login,
                    'password' => $password,
                    'profile_url' => $profileUrl,
                    'user_id' => $app_id
                ], 201);
                
            } catch (PDOException $e) {
                $db->rollBack();
                error_log('Registration error: ' . $e->getMessage());
                sendError('Ошибка сохранения данных', 500);
            }
            break;
            
        case 'PUT':
            if (!$requestId) {
                sendError('PUT запрос требует параметр id', 400);
            }
            
            $userId = null;
            if (!checkAuth($userId) || $userId != $requestId) {
                sendError('Необходима авторизация', 401);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                sendError('Невалидные входные данные', 400);
            }
            
            try {
                $updateFields = [];
                $params = [];
                
                if (!empty($input['fullName'])) {
                    $updateFields[] = "fio = ?";
                    $params[] = $input['fullName'];
                }
                if (!empty($input['email'])) {
                    $updateFields[] = "email = ?";
                    $params[] = $input['email'];
                }
                if (!empty($input['phone'])) {
                    $updateFields[] = "phone = ?";
                    $params[] = $input['phone'];
                }
                if (!empty($input['message'])) {
                    $updateFields[] = "bio = ?";
                    $params[] = $input['message'];
                }
                
                if (!empty($updateFields)) {
                    $params[] = $requestId;
                    $sql = "UPDATE application SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                }
                
                // Обновляем исполнителей
                if (!empty($input['artists']) && is_array($input['artists'])) {
                    $delStmt = $db->prepare("DELETE FROM user_artists WHERE user_id = ?");
                    $delStmt->execute([$requestId]);
                    
                    $artist_map = [];
                    $artistStmt = $db->query("SELECT id, code FROM artists");
                    while ($row = $artistStmt->fetch(PDO::FETCH_ASSOC)) {
                        $artist_map[$row['code']] = $row['id'];
                    }
                    
                    $insertArtist = $db->prepare("INSERT INTO user_artists (user_id, artist_id) VALUES (?, ?)");
                    foreach ($input['artists'] as $artist) {
                        if (isset($artist_map[$artist])) {
                            $insertArtist->execute([$requestId, $artist_map[$artist]]);
                        }
                    }
                }
                
                sendResponse(['success' => true, 'message' => 'Данные обновлены'], 200);
                
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
