<?php
header('Content-Type: text/html; charset=UTF-8');

require_once 'db_config.php';
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$auth_success = false;

if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM admins WHERE login = ? AND pass_hash = MD5(?)");
        $stmt->execute([$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']]);
        if ($stmt->fetch()) {
            $auth_success = true;
        }
    } catch (PDOException $e) {
        error_log('Admin auth error: ' . $e->getMessage());
    }
}

if (!$auth_success) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>401 Требуется авторизация</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; background: #C2C5CE; }
            .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
            h1 { color: #721c24; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>401 Требуется авторизация</h1>
            <p><strong>Логин:</strong> admin<br><strong>Пароль:</strong> admin123</p>
            <p><a href="index.html">← На главную</a></p>
        </div>
    </body>
    </html>';
    exit();
}

$action = $_GET['action'] ?? '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'delete' && $user_id > 0) {
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка безопасности';
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("DELETE FROM user_artists WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stmt = $db->prepare("DELETE FROM application WHERE id = ?");
            $stmt->execute([$user_id]);
            $db->commit();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: admin.php?msg=deleted');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Ошибка удаления";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'edit' && $user_id > 0) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка безопасности';
    } else {
        $errors = [];
        $fullName = trim($_POST['fullName'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $artists = $_POST['artists'] ?? [];
        
        if (empty($fullName)) $errors['fullName'] = true;
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = true;
        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
        if (empty($phone) || strlen($digitsOnly) < 10) $errors['phone'] = true;
        if (empty($message) || strlen($message) < 4) $errors['message'] = true;
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE application SET fio = ?, phone = ?, email = ?, bio = ? WHERE id = ?");
                $stmt->execute([$fullName, $phone, $email, $message, $user_id]);
                
                $delStmt = $db->prepare("DELETE FROM user_artists WHERE user_id = ?");
                $delStmt->execute([$user_id]);
                
                $artist_map = [];
                $artistStmt = $db->query("SELECT id, code FROM artists");
                while ($row = $artistStmt->fetch(PDO::FETCH_ASSOC)) {
                    $artist_map[$row['code']] = $row['id'];
                }
                
                $insertArtist = $db->prepare("INSERT INTO user_artists (user_id, artist_id) VALUES (?, ?)");
                foreach ($artists as $artist) {
                    if (isset($artist_map[$artist])) {
                        $insertArtist->execute([$user_id, $artist_map[$artist]]);
                    }
                }
                
                $db->commit();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: admin.php?msg=updated');
                exit();
            } catch (PDOException $e) {
                $db->rollBack();
                $error = "Ошибка обновления";
            }
        }
    }
}

$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted': $message = '<div class="success-msg">✓ Пользователь удален</div>'; break;
        case 'updated': $message = '<div class="success-msg">✓ Данные обновлены</div>'; break;
    }
}

$users = [];
try {
    $stmt = $db->query("
        SELECT a.*,
        GROUP_CONCAT(DISTINCT ar.name ORDER BY ar.name SEPARATOR ', ') as artists_names
        FROM application a
        LEFT JOIN user_artists ua ON a.id = ua.user_id
        LEFT JOIN artists ar ON ua.artist_id = ar.id
        GROUP BY a.id
        ORDER BY a.id DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Ошибка загрузки данных";
}

$artistStats = [];
try {
    $stmt = $db->query("
        SELECT a.name, a.code, COUNT(ua.user_id) as count
        FROM artists a
        LEFT JOIN user_artists ua ON a.id = ua.artist_id
        GROUP BY a.id
        ORDER BY count DESC
    ");
    $artistStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $artistStats = [];
}

$editUser = null;
if ($action === 'edit' && $user_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM application WHERE id = ?");
        $stmt->execute([$user_id]);
        $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($editUser) {
            $stmt = $db->prepare("SELECT ar.code FROM artists ar JOIN user_artists ua ON ar.id = ua.artist_id WHERE ua.user_id = ?");
            $stmt->execute([$user_id]);
            $editUser['artists'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (PDOException $e) {
        $error = "Ошибка загрузки данных";
    }
}

$artists_list = [
    'nirvana' => 'Nirvana',
    'radiohead' => 'Radiohead',
    'oasis' => 'Oasis',
    'backstreet_boys' => 'Backstreet Boys',
    'spice_girls' => 'Spice Girls',
    'splin' => 'Сплин',
    'kino' => 'Кино',
    'agatha_christie' => 'Агата Кристи',
    'metallica' => 'Metallica',
    'britney' => 'Britney Spears',
    'tupac' => 'Tupac Shakur',
    'prodigy' => 'The Prodigy'
];

$totalUsers = count($users);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | 33 Forever</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #C2C5CE; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: #566777; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .header h1 { font-size: 1.8rem; }
        .admin-info { background: rgba(255,255,255,0.2); padding: 10px 15px; border-radius: 8px; }
        .logout-link { color: white; text-decoration: none; margin-left: 15px; padding: 5px 10px; background: rgba(255,255,255,0.2); border-radius: 5px; }
        .stats-section { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .stats-title { font-size: 1.5rem; margin-bottom: 15px; color: #566777; border-left: 4px solid #566777; padding-left: 15px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #566777; color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .lang-stats { display: flex; flex-wrap: wrap; gap: 10px; }
        .lang-stat-item { background: #f0f0f0; padding: 8px 15px; border-radius: 20px; display: inline-flex; align-items: center; gap: 8px; }
        .lang-stat-count { background: #566777; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.8rem; }
        .success-msg, .error-msg { padding: 12px; border-radius: 5px; margin-bottom: 20px; }
        .success-msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .users-section { background: white; border-radius: 10px; padding: 20px; overflow-x: auto; }
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th, .users-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .users-table th { background: #f8f9fa; font-weight: 600; color: #566777; }
        .users-table tr:hover { background: #f8f9fa; }
        .btn-edit, .btn-delete { padding: 5px 12px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 0.85rem; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .modal { display: <?php echo ($editUser) ? 'flex' : 'none'; ?>; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto; padding: 20px; }
        .modal-content { background: white; border-radius: 10px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 25px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #C2C5CE; }
        .modal-header h2 { color: #566777; }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #566777; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #C2C5CE; border-radius: 5px; }
        .form-group textarea { min-height: 80px; }
        select[multiple] { min-height: 150px; }
        .btn-save { background: #566777; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .btn-cancel { background: #475361; color: white; padding: 8px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .badge { background: #C2C5CE; color: #566777; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; display: inline-block; margin: 2px; }
        @media (max-width: 768px) { .users-table { font-size: 0.85rem; } .header { flex-direction: column; text-align: center; gap: 10px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🎵 Админ-панель | 33 Forever</h1>
        <div class="admin-info">
            Вы вошли как <strong>admin</strong>
            <a href="index.html" class="logout-link">← На главную</a>
        </div>
    </div>

    <?php if ($message): echo $message; endif; ?>
    <?php if (isset($error)): echo '<div class="error-msg">⚠️ ' . htmlspecialchars($error) . '</div>'; endif; ?>

    <div class="stats-section">
        <h2 class="stats-title">📊 Статистика</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo htmlspecialchars($totalUsers); ?></div>
                <div class="stat-label">Всего пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo htmlspecialchars(count($artistStats)); ?></div>
                <div class="stat-label">Исполнителей 90-х</div>
            </div>
        </div>

        <h3 style="margin: 20px 0 10px 0; color: #566777;">🎵 Любимые исполнители 90-х (статистика)</h3>
        <div class="lang-stats">
            <?php foreach ($artistStats as $artist): ?>
            <div class="lang-stat-item">
                <strong><?php echo htmlspecialchars($artist['name']); ?></strong>
                <span class="lang-stat-count"><?php echo htmlspecialchars($artist['count']); ?> чел.</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="users-section">
        <h2 class="stats-title">👥 Все пользователи</h2>
        <?php if (empty($users)): ?>
        <p style="text-align: center; padding: 40px;">Нет зарегистрированных пользователей</p>
        <?php else: ?>
        <table class="users-table">
            <thead>
                <tr><th>ID</th><th>ФИО</th><th>Email</th><th>Телефон</th><th>Любимые исполнители</th><th>Действия</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['id']); ?></td>
                <td><?php echo htmlspecialchars($user['fio']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                <td><?php echo htmlspecialchars($user['artists_names'] ?? 'Не указан'); ?></td>
                <td>
                    <a href="admin.php?action=edit&id=<?php echo $user['id']; ?>" class="btn-edit">✏️ Редакт.</a>
                    <a href="admin.php?action=delete&id=<?php echo $user['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn-delete" onclick="return confirm('Удалить?')">🗑️ Удалить</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($editUser): ?>
<div class="modal" style="display: flex;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Редактирование #<?php echo $editUser['id']; ?></h2>
            <button class="close-modal" onclick="window.location.href='admin.php'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group"><label>ФИО</label><input type="text" name="fullName" value="<?php echo htmlspecialchars($editUser['fio']); ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required></div>
            <div class="form-group"><label>Телефон</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($editUser['phone']); ?>" required></div>
            <div class="form-group"><label>Биография</label><textarea name="message" required><?php echo htmlspecialchars($editUser['bio']); ?></textarea></div>
            <div class="form-group">
                <label>Любимые исполнители 90-х</label>
                <select name="artists[]" multiple size="6">
                    <?php foreach ($artists_list as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo in_array($code, $editUser['artists'] ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Ctrl + клик для выбора нескольких</small>
            </div>
            <button type="submit" class="btn-save">💾 Сохранить</button>
            <a href="admin.php" class="btn-cancel">Отмена</a>
        </form>
    </div>
</div>
<?php endif; ?>
</body>
</html>
