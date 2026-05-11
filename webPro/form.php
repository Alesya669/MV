<?php  ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма обратной связи</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Форма обратной связи</h1>
    
    <?php
    // Проверяем, авторизован ли пользователь
    $isAuthenticated = !empty($_SESSION['login']);
    ?>
    
    <?php if ($isAuthenticated): ?>
    <div style="text-align: right; margin-bottom: 15px; padding: 10px; background-color: #e8f4fd; border-radius: 4px;">
        <span>Вы вошли как <strong><?php echo htmlspecialchars($_SESSION['login']); ?></strong></span>
        <a href="login.php?logout=1" style="margin-left: 15px; color: #566777; text-decoration: none; padding: 5px 10px; border: 1px solid #566777; border-radius: 4px;">Выйти</a>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($messages)): ?>
        <div id="messages">
            <?php foreach ($messages as $message): ?>
                <?php echo $message; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form action="" method="POST">
        <!-- CSRF-токен -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <div class="form-group <?php echo isset($errors['fullName']) && $errors['fullName'] ? 'error' : ''; ?>">
            <label for="fullName">ФИО *</label>
            <input type="text" 
                   id="fullName" 
                   name="fullName" 
                   value="<?php echo htmlspecialchars($values['fullName'] ?? ''); ?>"
                   class="<?php echo isset($errors['fullName']) && $errors['fullName'] ? 'error' : ''; ?>">
        </div>
        
        <div class="form-group <?php echo isset($errors['email']) && $errors['email'] ? 'error' : ''; ?>">
            <label for="email">Email *</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   value="<?php echo htmlspecialchars($values['email'] ?? ''); ?>"
                   class="<?php echo isset($errors['email']) && $errors['email'] ? 'error' : ''; ?>">
        </div>
        
        <div class="form-group <?php echo isset($errors['phone']) && $errors['phone'] ? 'error' : ''; ?>">
            <label for="phone">Телефон *</label>
            <input type="tel" 
                   id="phone" 
                   name="phone" 
                   value="<?php echo htmlspecialchars($values['phone'] ?? ''); ?>"
                   class="<?php echo isset($errors['phone']) && $errors['phone'] ? 'error' : ''; ?>">
        </div>
        
        <div class="form-group <?php echo isset($errors['birthdate']) && $errors['birthdate'] ? 'error' : ''; ?>">
            <label for="birthdate">Дата рождения *</label>
            <input type="date" 
                   id="birthdate" 
                   name="birthdate" 
                   value="<?php echo htmlspecialchars($values['birthdate'] ?? ''); ?>"
                   class="<?php echo isset($errors['birthdate']) && $errors['birthdate'] ? 'error' : ''; ?>">
        </div>
        
        <div class="form-group <?php echo isset($errors['gender']) && $errors['gender'] ? 'error' : ''; ?>" id="gender-group">
            <label>Пол *</label>
            <div class="radio-group">
                <input type="radio" id="male" name="gender" value="male" 
                       <?php echo (isset($values['gender']) && $values['gender'] == 'male') ? 'checked' : ''; ?>>
                <label for="male">Мужской</label>
            </div>
            <div class="radio-group">
                <input type="radio" id="female" name="gender" value="female" 
                       <?php echo (isset($values['gender']) && $values['gender'] == 'female') ? 'checked' : ''; ?>>
                <label for="female">Женский</label>
            </div>
        </div>
        
        <div class="form-group <?php echo isset($errors['languages']) && $errors['languages'] ? 'error' : ''; ?>">
            <label for="languages">Любимый язык программирования *</label>
            <select id="languages" name="languages[]" multiple size="6" 
                    class="<?php echo isset($errors['languages']) && $errors['languages'] ? 'error' : ''; ?>">
                <?php
                $languages = [
                    'pascal' => 'Pascal',
                    'c' => 'C',
                    'cpp' => 'C++',
                    'javascript' => 'JavaScript',
                    'php' => 'PHP',
                    'python' => 'Python',
                    'java' => 'Java',
                    'haskell' => 'Haskell',
                    'clojure' => 'Clojure',
                    'prolog' => 'Prolog',
                    'scala' => 'Scala',
                    'go' => 'Go'
                ];
                $selectedLanguages = isset($values['languages']) && is_array($values['languages']) ? $values['languages'] : [];
                foreach ($languages as $value => $label):
                ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" 
                        <?php echo in_array($value, $selectedLanguages) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="hint">Для выбора нескольких: Ctrl + клик</small>
        </div>
        
        <div class="form-group <?php echo isset($errors['message']) && $errors['message'] ? 'error' : ''; ?>">
            <label for="message">Биография *</label>
            <textarea id="message" 
                      name="message" 
                      class="<?php echo isset($errors['message']) && $errors['message'] ? 'error' : ''; ?>"><?php echo htmlspecialchars($values['message'] ?? ''); ?></textarea>
        </div>
        
        <div class="checkbox-group <?php echo isset($errors['contract']) && $errors['contract'] ? 'error' : ''; ?>">
            <input type="checkbox" id="contract" name="contract" 
                   <?php echo (isset($values['contract']) && $values['contract']) ? 'checked' : ''; ?>>
            <label for="contract">С контрактом ознакомлен(а) *</label>
        </div>
        
        <button type="submit" class="submit-btn">
            <?php echo $isAuthenticated ? 'Обновить данные' : 'Сохранить'; ?>
        </button>
    </form>
    
    <?php if (!$isAuthenticated): ?>
    <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #C2C5CE;">
        <p style="color: #566777; font-size: 0.9rem;">
            Уже есть логин и пароль? <a href="login.php" style="color: #566777; font-weight: bold;">Войти</a>
        </p>
    </div>
    <?php endif; ?>
</div>
    <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #C2C5CE;">
    <a href="admin.php" style="color: #dc3545; text-decoration: none;"> Администратор</a>
</div>
</body>
</html>