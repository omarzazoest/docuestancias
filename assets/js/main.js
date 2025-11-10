// JavaScript para el sistema de estancias

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Validación de formularios
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Auto-resize para textareas
    var textareas = document.querySelectorAll('textarea');
    textareas.forEach(function(textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });

    // Confirmación para acciones importantes
    var deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de que deseas eliminar este elemento?')) {
                e.preventDefault();
            }
        });
    });

    // Formateo automático de campos
    formatearCampos();
    
    // Validaciones en tiempo real
    setupValidacionesTiempoReal();
});

// Función para formatear campos automáticamente
function formatearCampos() {
    // Formatear matrícula (solo números)
    var matriculaInputs = document.querySelectorAll('input[name="matricula"]');
    matriculaInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });
    });

    // Formatear teléfono (solo números)
    var telefonoInputs = document.querySelectorAll('input[type="tel"]');
    telefonoInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substring(0, 12);
        });
    });

    // Capitalizar nombres
    var nombreInputs = document.querySelectorAll('input[name*="nombres"], input[name*="paterno"], input[name*="materno"]');
    nombreInputs.forEach(function(input) {
        input.addEventListener('blur', function(e) {
            this.value = capitalizarTexto(this.value);
        });
    });
}

// Función para capitalizar texto
function capitalizarTexto(texto) {
    return texto.toLowerCase().replace(/\b\w/g, function(l) {
        return l.toUpperCase();
    });
}

// Validaciones en tiempo real
function setupValidacionesTiempoReal() {
    // Validar email
    var emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(function(input) {
        input.addEventListener('blur', function(e) {
            validarEmail(this);
        });
    });

    // Validar matrícula
    var matriculaInputs = document.querySelectorAll('input[name="matricula"]');
    matriculaInputs.forEach(function(input) {
        input.addEventListener('blur', function(e) {
            validarMatricula(this);
        });
    });
}

// Validar email
function validarEmail(input) {
    var email = input.value;
    var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !regex.test(email)) {
        mostrarError(input, 'El formato del correo electrónico no es válido');
        return false;
    } else {
        limpiarError(input);
        return true;
    }
}

// Validar matrícula
function validarMatricula(input) {
    var matricula = input.value;
    var regex = /^[0-9]{10}$/;
    
    if (matricula && !regex.test(matricula)) {
        mostrarError(input, 'La matrícula debe tener exactamente 10 dígitos');
        return false;
    } else {
        limpiarError(input);
        return true;
    }
}

// Mostrar error en campo
function mostrarError(input, mensaje) {
    limpiarError(input);
    
    input.classList.add('is-invalid');
    
    var errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = mensaje;
    
    input.parentNode.appendChild(errorDiv);
}

// Limpiar error de campo
function limpiarError(input) {
    input.classList.remove('is-invalid');
    
    var errorDiv = input.parentNode.querySelector('.invalid-feedback');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// Función para mostrar loading
function mostrarLoading(elemento) {
    var spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm me-2';
    spinner.setAttribute('role', 'status');
    
    elemento.disabled = true;
    elemento.insertBefore(spinner, elemento.firstChild);
    
    return spinner;
}

// Función para ocultar loading
function ocultarLoading(elemento, spinner) {
    elemento.disabled = false;
    if (spinner && spinner.parentNode) {
        spinner.remove();
    }
}

// Función para copiar texto al portapapeles
function copiarAlPortapapeles(texto) {
    navigator.clipboard.writeText(texto).then(function() {
        mostrarNotificacion('Copiado al portapapeles', 'success');
    }).catch(function(err) {
        console.error('Error al copiar: ', err);
        mostrarNotificacion('Error al copiar al portapapeles', 'danger');
    });
}

// Función para mostrar notificaciones toast
function mostrarNotificacion(mensaje, tipo = 'info') {
    var toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    var toastId = 'toast-' + Date.now();
    var toastHTML = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto text-${tipo}">
                    ${tipo === 'success' ? '✓' : tipo === 'danger' ? '✗' : 'ℹ'} 
                    ${tipo === 'success' ? 'Éxito' : tipo === 'danger' ? 'Error' : 'Información'}
                </strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${mensaje}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    var toastElement = document.getElementById(toastId);
    var toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    
    toast.show();
    
    // Eliminar el toast del DOM después de que se oculte
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}

// Función para confirmar acciones
function confirmarAccion(mensaje, callback) {
    if (confirm(mensaje)) {
        callback();
    }
}

// Función para validar formulario completo antes del envío
function validarFormulario(formulario) {
    var esValido = true;
    var primerCampoInvalido = null;
    
    // Validar campos requeridos
    var camposRequeridos = formulario.querySelectorAll('[required]');
    camposRequeridos.forEach(function(campo) {
        if (!campo.value.trim()) {
            mostrarError(campo, 'Este campo es obligatorio');
            esValido = false;
            if (!primerCampoInvalido) {
                primerCampoInvalido = campo;
            }
        }
    });
    
    // Validar emails
    var emails = formulario.querySelectorAll('input[type="email"]');
    emails.forEach(function(email) {
        if (!validarEmail(email)) {
            esValido = false;
            if (!primerCampoInvalido) {
                primerCampoInvalido = email;
            }
        }
    });
    
    // Validar matrículas
    var matriculas = formulario.querySelectorAll('input[name="matricula"]');
    matriculas.forEach(function(matricula) {
        if (!validarMatricula(matricula)) {
            esValido = false;
            if (!primerCampoInvalido) {
                primerCampoInvalido = matricula;
            }
        }
    });
    
    // Enfocar el primer campo inválido
    if (primerCampoInvalido) {
        primerCampoInvalido.focus();
        primerCampoInvalido.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    return esValido;
}

// Función para guardar datos en localStorage
function guardarEnStorage(clave, datos) {
    try {
        localStorage.setItem(clave, JSON.stringify(datos));
        return true;
    } catch (e) {
        console.error('Error al guardar en localStorage:', e);
        return false;
    }
}

// Función para cargar datos desde localStorage
function cargarDeStorage(clave) {
    try {
        var datos = localStorage.getItem(clave);
        return datos ? JSON.parse(datos) : null;
    } catch (e) {
        console.error('Error al cargar de localStorage:', e);
        return null;
    }
}

// Función para limpiar datos del localStorage
function limpiarStorage(clave) {
    try {
        localStorage.removeItem(clave);
        return true;
    } catch (e) {
        console.error('Error al limpiar localStorage:', e);
        return false;
    }
}

// Exportar funciones para uso global
window.EstanciasApp = {
    mostrarLoading: mostrarLoading,
    ocultarLoading: ocultarLoading,
    mostrarNotificacion: mostrarNotificacion,
    confirmarAccion: confirmarAccion,
    validarFormulario: validarFormulario,
    copiarAlPortapapeles: copiarAlPortapapeles,
    guardarEnStorage: guardarEnStorage,
    cargarDeStorage: cargarDeStorage,
    limpiarStorage: limpiarStorage
};