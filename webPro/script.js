document.addEventListener('DOMContentLoaded', function() {
    
    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const feedbackModal = document.getElementById('feedbackModal');
    const feedbackForm = document.getElementById('feedbackForm');
    const responseMessage = document.getElementById('responseMessage');
    const submitBtn = document.getElementById('submitBtn');
    
    const API_URL = 'rest-api.php';
    
    function validateFullName(name) {
        return /^[a-zA-Zа-яА-ЯёЁ\s\-]+$/.test(name) && name.length <= 150;
    }
    
    function validatePhone(phone) {
        const digitsOnly = phone.replace(/[^0-9]/g, '');
        return /^[\d\s\+\-\(\)]+$/.test(phone) && digitsOnly.length >= 10 && digitsOnly.length <= 11;
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email) && email.length <= 100;
    }
    
    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        const formGroup = field.closest('.form-group');
        if (!formGroup) return;
        const existingError = formGroup.querySelector('.field-error');
        if (existingError) existingError.remove();
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.textContent = message;
        formGroup.appendChild(errorElement);
        field.style.borderColor = '#dc3545';
    }
    
    function clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        const formGroup = field.closest('.form-group');
        if (!formGroup) return;
        const existingError = formGroup.querySelector('.field-error');
        if (existingError) existingError.remove();
        field.style.borderColor = '#C2C5CE';
    }
    
    function validateArtists() {
        const artistsSelect = document.getElementById('artists');
        const selectedCount = artistsSelect ? artistsSelect.selectedOptions.length : 0;
        
        if (selectedCount === 0) {
            const formGroup = artistsSelect?.closest('.form-group');
            if (formGroup && !formGroup.querySelector('.field-error')) {
                const errorElement = document.createElement('div');
                errorElement.className = 'field-error';
                errorElement.textContent = 'Выберите хотя бы одного исполнителя (Ctrl + клик для выбора нескольких)';
                formGroup.appendChild(errorElement);
            }
            return false;
        }
        
        const formGroup = artistsSelect?.closest('.form-group');
        const existingError = formGroup?.querySelector('.field-error');
        if (existingError) existingError.remove();
        return true;
    }
    
    function validateForm() {
        const fullName = document.getElementById('fullName')?.value.trim() || '';
        const email = document.getElementById('email')?.value.trim() || '';
        const phone = document.getElementById('phone')?.value.trim() || '';
        const message = document.getElementById('message')?.value.trim() || '';
        const privacyPolicy = document.getElementById('privacyPolicy')?.checked || false;
        
        let isValid = true;
        
        if (!fullName) {
            showFieldError('fullName', 'ФИО обязательно для заполнения');
            isValid = false;
        } else if (!validateFullName(fullName)) {
            showFieldError('fullName', 'ФИО может содержать только буквы, пробелы и дефисы');
            isValid = false;
        } else {
            clearFieldError('fullName');
        }
        
        if (!email) {
            showFieldError('email', 'Email обязателен для заполнения');
            isValid = false;
        } else if (!isValidEmail(email)) {
            showFieldError('email', 'Введите корректный email адрес');
            isValid = false;
        } else {
            clearFieldError('email');
        }
        
        if (!phone) {
            showFieldError('phone', 'Телефон обязателен для заполнения');
            isValid = false;
        } else if (!validatePhone(phone)) {
            showFieldError('phone', 'Телефон должен содержать 10-11 цифр');
            isValid = false;
        } else {
            clearFieldError('phone');
        }
        
        if (!message) {
            showFieldError('message', 'Сообщение обязательно для заполнения');
            isValid = false;
        } else if (message.length < 4) {
            showFieldError('message', 'Сообщение должно содержать не менее 4 символов');
            isValid = false;
        } else {
            clearFieldError('message');
        }
        
        if (!validateArtists()) {
            isValid = false;
        }
        
        if (!privacyPolicy) {
            const checkboxGroup = document.querySelector('.checkbox-group');
            if (checkboxGroup && !checkboxGroup.querySelector('.field-error')) {
                const errorElement = document.createElement('div');
                errorElement.className = 'field-error';
                errorElement.textContent = 'Необходимо согласиться с политикой обработки данных';
                checkboxGroup.appendChild(errorElement);
            }
            isValid = false;
        } else {
            const checkboxError = document.querySelector('.checkbox-group .field-error');
            if (checkboxError) checkboxError.remove();
        }
        
        return isValid;
    }
    
    function setLoadingState(isLoading) {
        if (!submitBtn) return;
        submitBtn.disabled = isLoading;
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        if (btnText && btnLoading) {
            if (isLoading) {
                btnText.style.display = 'none';
                btnLoading.style.display = 'block';
            } else {
                btnText.style.display = 'block';
                btnLoading.style.display = 'none';
            }
        }
    }
    
    function showMessage(text, type) {
        if (!responseMessage) return;
        responseMessage.textContent = text;
        responseMessage.className = 'message ' + type;
        responseMessage.style.display = 'block';
        setTimeout(() => {
            responseMessage.style.display = 'none';
        }, 5000);
    }
    
    function showRegistrationResult(data) {
        const resultDiv = document.getElementById('registrationResult');
        if (resultDiv) {
            document.getElementById('userLogin').textContent = data.login;
            document.getElementById('userPassword').textContent = data.password;
            document.getElementById('profileUrl').href = data.profile_url;
            resultDiv.style.display = 'block';
            
            localStorage.setItem('vinyl_login', data.login);
            localStorage.setItem('vinyl_password', data.password);
            localStorage.setItem('vinyl_user_id', data.user_id);
            
            const loginSection = document.getElementById('loginSection');
            if (loginSection) {
                loginSection.style.display = 'block';
                document.getElementById('loginUsername').value = data.login;
                document.getElementById('loginPassword').value = data.password;
            }
        }
    }
    
    async function doLogin() {
        const login = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        const loginResult = document.getElementById('loginResult');
        
        if (!login || !password) {
            loginResult.textContent = 'Введите логин и пароль';
            loginResult.className = 'message error';
            loginResult.style.display = 'block';
            return;
        }
        
        try {
            const response = await fetch(`${API_URL}?action=login`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Basic ' + btoa(login + ':' + password)
                }
            });
            
            if (response.ok) {
                loginResult.textContent = 'Авторизация успешна! Теперь вы можете изменять данные.';
                loginResult.className = 'message success';
                loginResult.style.display = 'block';
                
                localStorage.setItem('vinyl_login', login);
                localStorage.setItem('vinyl_password', password);
                
                await loadUserData();
            } else {
                loginResult.textContent = 'Неверный логин или пароль';
                loginResult.className = 'message error';
                loginResult.style.display = 'block';
            }
        } catch (error) {
            console.error('Login error:', error);
            loginResult.textContent = 'Ошибка авторизации';
            loginResult.className = 'message error';
            loginResult.style.display = 'block';
        }
    }
    
    async function loadUserData() {
        const userId = localStorage.getItem('vinyl_user_id');
        if (!userId) return;
        
        try {
            const response = await fetch(`${API_URL}?id=${userId}`, {
                headers: {
                    'Authorization': 'Basic ' + btoa(localStorage.getItem('vinyl_login') + ':' + localStorage.getItem('vinyl_password'))
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.user) {
                    document.getElementById('fullName').value = data.user.fio || '';
                    document.getElementById('email').value = data.user.email || '';
                    document.getElementById('phone').value = data.user.phone || '';
                    document.getElementById('message').value = data.user.bio || '';
                    
                    // Отмечаем выбранных исполнителей
                    if (data.user.artists) {
                        const artistsSelect = document.getElementById('artists');
                        for (let i = 0; i < artistsSelect.options.length; i++) {
                            const option = artistsSelect.options[i];
                            if (data.user.artists.includes(option.value)) {
                                option.selected = true;
                            }
                        }
                    }
                    
                    const btnText = submitBtn?.querySelector('.btn-text');
                    if (btnText) btnText.textContent = 'Обновить данные';
                    
                    showMessage('Данные загружены. Вы можете их отредактировать.', 'success');
                }
            }
        } catch (error) {
            console.error('Load user data error:', error);
        }
    }
    
    if (feedbackForm) {
        feedbackForm.setAttribute('data-js-enabled', 'true');
        
        feedbackForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                showMessage('Пожалуйста, исправьте ошибки в форме', 'error');
                return;
            }
            
            setLoadingState(true);
            
            const artistsSelect = document.getElementById('artists');
            const selectedArtists = Array.from(artistsSelect.selectedOptions).map(opt => opt.value);
            
            const formData = {
                fullName: document.getElementById('fullName').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                message: document.getElementById('message').value.trim(),
                artists: selectedArtists
            };
            
            const userId = localStorage.getItem('vinyl_user_id');
            const isAuthenticated = !!localStorage.getItem('vinyl_login');
            
            let url = API_URL;
            let method = 'POST';
            
            if (isAuthenticated && userId) {
                url = `${API_URL}?id=${userId}`;
                method = 'PUT';
            }
            
            try {
                const headers = { 'Content-Type': 'application/json' };
                if (isAuthenticated) {
                    headers['Authorization'] = 'Basic ' + btoa(localStorage.getItem('vinyl_login') + ':' + localStorage.getItem('vinyl_password'));
                }
                
                const response = await fetch(url, {
                    method: method,
                    headers: headers,
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    showMessage(data.message || 'Операция выполнена успешно!', 'success');
                    
                    if (data.login && data.password) {
                        showRegistrationResult(data);
                    }
                    
                    if (!data.login && !data.password && !isAuthenticated) {
                        setTimeout(() => closeFeedbackModal(), 2000);
                    }
                    
                    if (!isAuthenticated && data.user_id) {
                        localStorage.setItem('vinyl_user_id', data.user_id);
                    }
                } else if (data.errors) {
                    const errorMessages = Object.values(data.errors).join(', ');
                    showMessage(errorMessages, 'error');
                } else {
                    showMessage(data.error || 'Ошибка при отправке данных', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Произошла ошибка при отправке', 'error');
            } finally {
                setLoadingState(false);
            }
        });
    }
    
    function openFeedbackModal() {
        if (feedbackModal) {
            feedbackModal.style.display = 'flex';
            history.pushState({ modalOpen: true }, '', '#feedback');
            
            if (localStorage.getItem('vinyl_login')) {
                const loginSection = document.getElementById('loginSection');
                if (loginSection) loginSection.style.display = 'block';
                loadUserData();
            }
        }
    }
    
    function closeFeedbackModal() {
        if (feedbackModal) {
            feedbackModal.style.display = 'none';
            if (location.hash === '#feedback') history.back();
        }
    }
    
    if (openModalBtn) openModalBtn.addEventListener('click', openFeedbackModal);
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeFeedbackModal);
    
    if (feedbackModal) {
        feedbackModal.addEventListener('click', function(e) {
            if (e.target === feedbackModal) closeFeedbackModal();
        });
    }
    
    const doLoginBtn = document.getElementById('doLoginBtn');
    if (doLoginBtn) doLoginBtn.addEventListener('click', doLogin);
    
    // Валидация при вводе
    const fullNameInput = document.getElementById('fullName');
    if (fullNameInput) {
        fullNameInput.addEventListener('input', function() {
            if (this.value && !validateFullName(this.value)) {
                showFieldError('fullName', 'ФИО может содержать только буквы');
            } else {
                clearFieldError('fullName');
            }
        });
    }
    
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            if (this.value && !isValidEmail(this.value)) {
                showFieldError('email', 'Введите корректный email');
            } else {
                clearFieldError('email');
            }
        });
    }
    
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            if (this.value && !validatePhone(this.value)) {
                showFieldError('phone', 'Телефон должен содержать 10-11 цифр');
            } else {
                clearFieldError('phone');
            }
        });
    }
    
    const messageInput = document.getElementById('message');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            if (this.value && this.value.length < 4) {
                showFieldError('message', 'Минимум 4 символа');
            } else {
                clearFieldError('message');
            }
        });
    }
    
    const artistsSelect = document.getElementById('artists');
    if (artistsSelect) {
        artistsSelect.addEventListener('change', function() {
            if (this.selectedOptions.length > 0) {
                const formGroup = this.closest('.form-group');
                const existingError = formGroup?.querySelector('.field-error');
                if (existingError) existingError.remove();
            }
        });
    }
    
    function initSlider() {
        const sliderSlides = document.querySelector('.slider-slides');
        const sliderPrev = document.querySelector('.slider-prev');
        const sliderNext = document.querySelector('.slider-next');
        if (!sliderSlides) return;
        
        let currentSlide = 0;
        const totalSlides = document.querySelectorAll('.slider-slide').length;
        
        function updateSlider() {
            sliderSlides.style.transform = `translateX(-${currentSlide * 100}%)`;
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            updateSlider();
        }
        
        function prevSlide() {
            currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
            updateSlider();
        }
        
        if (sliderPrev) sliderPrev.addEventListener('click', prevSlide);
        if (sliderNext) sliderNext.addEventListener('click', nextSlide);
        setInterval(nextSlide, 5000);
    }
    
    initSlider();
    
    const buyButtons = document.querySelectorAll('.buy-btn');
    buyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const card = this.closest('.bouquet-card');
            if (card) {
                const title = card.querySelector('h3')?.textContent || 'Товар';
                alert(`Товар добавлен в корзину!\n${title}`);
            }
        });
    });
});

function animateContacts() {
    alert('📞 Наши контакты:\nТелефон: +7 (495) 123-45-67\nEmail: info@vinylt.ru\nАдрес: Москва, ул. Виниловая, 33');
}
