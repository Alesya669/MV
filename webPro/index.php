<?php
/**
 * Реализовать возможность входа с паролем и логином с использованием
 * сессии для изменения отправленных данных в предыдущей задаче,
 * пароль и логин генерируются автоматически при первоначальной отправке формы.
 */

header('Content-Type: text/html; charset=UTF-8');

// Подключение к БД
require_once 'db_config.php';

// Запуск сессии
session_start();

// Генерация CSRF-токена, если его нет
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $messages = array();
    
    // Сообщение об успешном сохранении
    if (!empty($_COOKIE['save'])) {
        setcookie('save', '', 100000);
        setcookie('login', '', 100000);
        setcookie('pass', '', 100000);
        
        $messages[] = '<div class="success-message">Спасибо, результаты сохранены.</div>';
        
        // Если в куках есть пароль, то выводим сообщение с логином/паролем
        if (!empty($_COOKIE['pass'])) {
            $messages[] = sprintf('<div class="info-message">Вы можете <a href="login.php">войти</a> с логином <strong>%s</strong> и паролем <strong>%s</strong> для изменения данных.</div>',
                htmlspecialchars($_COOKIE['login']),
                htmlspecialchars($_COOKIE['pass']));
        }
    }
    
    // Собираем ошибки из Cookies
    $errors = array();
    $errors['fullName'] = !empty($_COOKIE['fullName_error']);
    $errors['email'] = !empty($_COOKIE['email_error']);
    $errors['phone'] = !empty($_COOKIE['phone_error']);
    $errors['birthdate'] = !empty($_COOKIE['birthdate_error']);
    $errors['gender'] = !empty($_COOKIE['gender_error']);
    $errors['languages'] = !empty($_COOKIE['languages_error']);
    $errors['message'] = !empty($_COOKIE['message_error']);
    $errors['contract'] = !empty($_COOKIE['contract_error']);
    
    // Выводим сообщения об ошибках и удаляем куки
    if ($errors['fullName']) {
        setcookie('fullName_error', '', 100000);
        setcookie('fullName_value', '', 100000);
        $messages[] = '<div class="error-message">ФИО должно содержать только буквы, пробелы и дефисы. Длина не более 150 символов.</div>';
    }
    
    if ($errors['email']) {
        setcookie('email_error', '', 100000);
        setcookie('email_value', '', 100000);
        $messages[] = '<div class="error-message">Email должен быть корректным (например: name@domain.com). Длина не более 100 символов.</div>';
    }
    
    if ($errors['phone']) {
        setcookie('phone_error', '', 100000);
        setcookie('phone_value', '', 100000);
        $messages[] = '<div class="error-message">Телефон может содержать только цифры, пробелы, дефисы, скобки и символ +. Должен содержать 10 или 11 цифр.</div>';
    }
    
    if ($errors['birthdate']) {
        setcookie('birthdate_error', '', 100000);
        setcookie('birthdate_value', '', 100000);
        $messages[] = '<div class="error-message">Дата рождения должна быть корректной в формате ГГГГ-ММ-ДД.</div>';
    }
    
    if ($errors['gender']) {
        setcookie('gender_error', '', 100000);
        setcookie('gender_value', '', 100000);
        $messages[] = '<div class="error-message">Выберите пол (Мужской или Женский).</div>';
    }
    
    if ($errors['languages']) {
        setcookie('languages_error', '', 100000);
        setcookie('languages_value', '', 100000);
        $messages[] = '<div class="error-message">Выберите хотя бы один язык программирования из списка.</div>';
    }
    
    if ($errors['message']) {
        setcookie('message_error', '', 100000);
        setcookie('message_value', '', 100000);
        $messages[] = '<div class="error-message">Биография должна содержать минимум 4 символа и не более 65535 символов.</div>';
    }
    
    if ($errors['contract']) {
        setcookie('contract_error', '', 100000);
        setcookie('contract_value', '', 100000);
        $messages[] = '<div class="error-message">Необходимо подтвердить ознакомление с контрактом.</div>';
    }
    
    // Получаем сохраненные значения из Cookies
    $values = array();
    $values['fullName'] = isset($_COOKIE['fullName_value']) ? htmlspecialchars($_COOKIE['fullName_value']) : '';
    $values['email'] = isset($_COOKIE['email_value']) ? htmlspecialchars($_COOKIE['email_value']) : '';
    $values['phone'] = isset($_COOKIE['phone_value']) ? htmlspecialchars($_COOKIE['phone_value']) : '';
    $values['birthdate'] = isset($_COOKIE['birthdate_value']) ? htmlspecialchars($_COOKIE['birthdate_value']) : '';
    $values['gender'] = isset($_COOKIE['gender_value']) ? htmlspecialchars($_COOKIE['gender_value']) : '';
    $values['languages'] = isset($_COOKIE['languages_value']) ? explode(',', $_COOKIE['languages_value']) : [];
    $values['message'] = isset($_COOKIE['message_value']) ? htmlspecialchars($_COOKIE['message_value']) : '';
    $values['contract'] = !empty($_COOKIE['contract_value']);
    
    // Если пользователь авторизован, загружаем его данные из БД
    if (empty($errors) && !empty($_SESSION['login'])) {
        try {
            // Получаем данные пользователя из БД
            $stmt = $db->prepare("SELECT * FROM application WHERE id = ?");
            $stmt->execute([$_SESSION['uid']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData) {
                $values['fullName'] = htmlspecialchars($userData['fio']);
                $values['email'] = htmlspecialchars($userData['email']);
                $values['phone'] = htmlspecialchars($userData['phone']);
                $values['birthdate'] = htmlspecialchars($userData['birth_date']);
                $values['gender'] = htmlspecialchars($userData['gender']);
                $values['message'] = htmlspecialchars($userData['bio']);
                $values['contract'] = (bool)$userData['contract'];
                
                // Получаем языки пользователя
                $langStmt = $db->prepare("SELECT l.code FROM languages l 
                                          JOIN app_languages al ON l.id = al.lang_id 
                                          WHERE al.app_id = ?");
                $langStmt->execute([$_SESSION['uid']]);
                $values['languages'] = $langStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $messages[] = '<div class="success-message">Вы вошли как ' . htmlspecialchars($_SESSION['login']) . '. Можете изменить данные.</div>';
            }
        } catch (PDOException $e) {
            error_log('Load user data error: ' . $e->getMessage());
            $messages[] = '<div class="error-message">Ошибка загрузки данных пользователя.</div>';
        }
    }
    
    include('form.php');
}
else {
    // POST запрос - проверяем данные
    
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Ошибка безопасности. Пожалуйста, обновите страницу и попробуйте снова.');
    }
    
    $errors = FALSE;
    
    // Валидация ФИО
    if (empty($_POST['fullName'])) {
        setcookie('fullName_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $fullName = $_POST['fullName'];
        if (strlen($fullName) > 150) {
            setcookie('fullName_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $fullName)) {
            setcookie('fullName_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('fullName_value', $_POST['fullName'], time() + 365 * 24 * 60 * 60);
    
    // Валидация email
    if (empty($_POST['email'])) {
        setcookie('email_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $email = $_POST['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setcookie('email_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        } elseif (strlen($email) > 100) {
            setcookie('email_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('email_value', $_POST['email'], time() + 365 * 24 * 60 * 60);
    
    // Валидация телефона
    if (empty($_POST['phone'])) {
        setcookie('phone_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $phone = $_POST['phone'];
        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
        
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            setcookie('phone_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        } elseif (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 11) {
            setcookie('phone_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        } elseif (strlen($phone) > 20) {
            setcookie('phone_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('phone_value', $_POST['phone'], time() + 365 * 24 * 60 * 60);
    
    // Валидация даты рождения
    if (empty($_POST['birthdate'])) {
        setcookie('birthdate_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $_POST['birthdate']);
        if (!$date || $date->format('Y-m-d') !== $_POST['birthdate']) {
            setcookie('birthdate_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('birthdate_value', $_POST['birthdate'], time() + 365 * 24 * 60 * 60);
    
    // Валидация пола
    if (empty($_POST['gender'])) {
        setcookie('gender_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } elseif (!in_array($_POST['gender'], ['male', 'female'])) {
        setcookie('gender_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    }
    setcookie('gender_value', $_POST['gender'] ?? '', time() + 365 * 24 * 60 * 60);
    
    // Валидация языков
    if (empty($_POST['languages']) || !is_array($_POST['languages'])) {
        setcookie('languages_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $allowed_langs = ['pascal','c','cpp','javascript','php','python','java','haskell','clojure','prolog','scala','go'];
        foreach ($_POST['languages'] as $lang) {
            if (!in_array($lang, $allowed_langs)) {
                setcookie('languages_error', '1', time() + 24 * 60 * 60);
                $errors = TRUE;
                break;
            }
        }
    }
    if (!empty($_POST['languages']) && is_array($_POST['languages'])) {
        setcookie('languages_value', implode(',', $_POST['languages']), time() + 365 * 24 * 60 * 60);
    }
    
    // Валидация биографии
    if (empty($_POST['message'])) {
        setcookie('message_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    } else {
        $message = $_POST['message'];
        if (strlen($message) < 4) {
            setcookie('message_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        } elseif (strlen($message) > 65535) {
            setcookie('message_error', '1', time() + 24 * 60 * 60);
            $errors = TRUE;
        }
    }
    setcookie('message_value', $_POST['message'], time() + 365 * 24 * 60 * 60);
    
    // Валидация чекбокса
    if (!isset($_POST['contract']) || $_POST['contract'] !== 'on') {
        setcookie('contract_error', '1', time() + 24 * 60 * 60);
        $errors = TRUE;
    }
    setcookie('contract_value', isset($_POST['contract']) ? '1' : '', time() + 365 * 24 * 60 * 60);
    
    if ($errors) {
        header('Location: index.php');
        exit();
    }
    
    // Удаляем все куки с ошибками
    setcookie('fullName_error', '', 100000);
    setcookie('email_error', '', 100000);
    setcookie('phone_error', '', 100000);
    setcookie('birthdate_error', '', 100000);
    setcookie('gender_error', '', 100000);
    setcookie('languages_error', '', 100000);
    setcookie('message_error', '', 100000);
    setcookie('contract_error', '', 100000);
    
    // Проверяем, авторизован ли пользователь (меняет данные)
    $isAuthenticated = !empty($_SESSION['login']);
    
    if ($isAuthenticated) {
        // Авторизованный пользователь - обновляем его данные
        try {
            $db->beginTransaction();
            
            // Обновляем основную информацию
            $stmt = $db->prepare("UPDATE application SET fio = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract = ? WHERE id = ?");
            $stmt->execute([
                $_POST['fullName'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birthdate'],
                $_POST['gender'],
                $_POST['message'],
                $_POST['contract'] === 'on' ? 1 : 0,
                $_SESSION['uid']
            ]);
            
            // Удаляем старые языки
            $delStmt = $db->prepare("DELETE FROM app_languages WHERE app_id = ?");
            $delStmt->execute([$_SESSION['uid']]);
            
            // Добавляем новые языки
            $lang_map = [];
            $langStmt = $db->query("SELECT id, code FROM languages");
            while ($row = $langStmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['code']] = $row['id'];
            }
            
            $insertLang = $db->prepare("INSERT INTO app_languages (app_id, lang_id) VALUES (?, ?)");
            foreach ($_POST['languages'] as $lang) {
                if (isset($lang_map[$lang])) {
                    $insertLang->execute([$_SESSION['uid'], $lang_map[$lang]]);
                }
            }
            
            $db->commit();
            
            // Обновляем CSRF-токен после изменения данных
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            setcookie('save', '1');
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Update user error: ' . $e->getMessage());
            die('Ошибка обновления данных. Пожалуйста, попробуйте позже.');
        }
    } else {
        // Новый пользователь - генерируем логин и пароль
        $login = substr(uniqid(), 0, 8);
        $pass = substr(md5(rand()), 0, 8);
        $passHash = md5($pass);
        
        try {
            $db->beginTransaction();
            
            // Вставляем нового пользователя
            $stmt = $db->prepare("INSERT INTO application (fio, phone, email, birth_date, gender, bio, contract) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['fullName'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birthdate'],
                $_POST['gender'],
                $_POST['message'],
                $_POST['contract'] === 'on' ? 1 : 0
            ]);
            
            $app_id = $db->lastInsertId();
            
            // Сохраняем логин и хеш пароля
            $updateStmt = $db->prepare("UPDATE application SET login = ?, pass_hash = ? WHERE id = ?");
            $updateStmt->execute([$login, $passHash, $app_id]);
            
            // Добавляем языки
            $lang_map = [];
            $langStmt = $db->query("SELECT id, code FROM languages");
            while ($row = $langStmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['code']] = $row['id'];
            }
            
            $insertLang = $db->prepare("INSERT INTO app_languages (app_id, lang_id) VALUES (?, ?)");
            foreach ($_POST['languages'] as $lang) {
                if (isset($lang_map[$lang])) {
                    $insertLang->execute([$app_id, $lang_map[$lang]]);
                }
            }
            
            $db->commit();
            
            // Сохраняем логин и пароль в Cookies для отображения пользователю
            setcookie('login', $login);
            setcookie('pass', $pass);
            setcookie('save', '1');
            
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Insert user error: ' . $e->getMessage());
            die('Ошибка сохранения данных. Пожалуйста, попробуйте позже.');
        }
    }
}