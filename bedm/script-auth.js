document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return;   // en el login no hay nada que interceptar: el form viaja por POST

    const nickname = document.getElementById('nickname');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const errorMessage = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');

    const marcar = (color) => {
        password.style.borderColor = color;
        confirmPassword.style.borderColor = color;
    };
    const mostrarError = (msg) => {
        errorText.textContent = msg;
        errorMessage.classList.remove('show');
        void errorMessage.offsetWidth;            // reinicia la animación shake
        errorMessage.classList.add('show');
    };

    registerForm.addEventListener('submit', (e) => {
        const nick = nickname.value.trim().toLowerCase().replace(/^@/, '');

        if (!/^[a-z0-9._]{3,20}$/.test(nick)) {
            e.preventDefault();
            return mostrarError('El nickname debe tener 3 a 20 caracteres: letras, números, punto o guion bajo.');
        }
        if (password.value.length < 8) {
            e.preventDefault();
            marcar('var(--error-red)');
            return mostrarError('La contraseña debe tener al menos 8 caracteres.');
        }
        if (!document.getElementById('terminos').checked) {
            e.preventDefault();
            return mostrarError('Para crear la cuenta tenés que aceptar los términos y condiciones.');
        }
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            marcar('var(--error-red)');
            return mostrarError('Las contraseñas no coinciden.');
        }
        // Todo OK: no se hace preventDefault → el formulario se envía a register.php (PHP guarda en la base)
    });

    [nickname, password, confirmPassword].forEach(input => {
        input.addEventListener('input', () => {
            errorMessage.classList.remove('show');
            marcar('var(--card-border)');
        });
    });
});