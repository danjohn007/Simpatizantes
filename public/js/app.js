/**
 * JavaScript Principal
 * Sistema de Validación de Simpatizantes
 */

// Validación de CURP
function validarCURP(curp) {
    const pattern = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/;
    return pattern.test(curp.toUpperCase());
}

// Validación de Clave de Elector
function validarClaveElector(clave) {
    const pattern = /^[A-Z]{6}[0-9]{8}[HM][0-9]{3}$/;
    return pattern.test(clave.toUpperCase());
}

// Validación en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    // Validar CURP
    const curpInput = document.querySelector('input[name="curp"]');
    if (curpInput) {
        curpInput.addEventListener('blur', function() {
            const curp = this.value.trim();
            if (curp && !validarCURP(curp)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
                
                let feedback = this.nextElementSibling;
                if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                    feedback = document.createElement('div');
                    feedback.classList.add('invalid-feedback');
                    this.parentNode.appendChild(feedback);
                }
                feedback.textContent = 'Formato de CURP inválido (18 caracteres)';
            } else if (curp) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
        
        // Convertir a mayúsculas automáticamente
        curpInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    // Validar Clave de Elector
    const claveInput = document.querySelector('input[name="clave_elector"]');
    if (claveInput) {
        claveInput.addEventListener('blur', function() {
            const clave = this.value.trim();
            if (clave && !validarClaveElector(clave)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
                
                let feedback = this.nextElementSibling;
                if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                    feedback = document.createElement('div');
                    feedback.classList.add('invalid-feedback');
                    this.parentNode.appendChild(feedback);
                }
                feedback.textContent = 'Formato de Clave de Elector inválido';
            } else if (clave) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
        
        // Convertir a mayúsculas automáticamente
        claveInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    // Validar email
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else if (email) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });
    
    // Preview de imágenes
    const fileInputs = document.querySelectorAll('input[type="file"][accept="image/*"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validar tamaño (5MB max)
                if (file.size > 5242880) {
                    alert('El archivo no debe superar los 5MB');
                    this.value = '';
                    return;
                }
                
                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = input.parentNode.querySelector('.preview-image');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.classList.add('preview-image', 'mt-2', 'img-thumbnail');
                        preview.style.maxWidth = '200px';
                        input.parentNode.appendChild(preview);
                    }
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    });
});

// Validar email
function isValidEmail(email) {
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return pattern.test(email);
}

// Confirmar eliminación
function confirmarEliminar(mensaje = '¿Está seguro de que desea eliminar este registro?') {
    return confirm(mensaje);
}

// Mostrar loading overlay
function showLoading() {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.classList.add('loading-overlay');
    overlay.innerHTML = '<div class="loading-spinner"></div>';
    document.body.appendChild(overlay);
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

// Detectar ubicación
function obtenerUbicacion() {
    if (navigator.geolocation) {
        showLoading();
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latitud').value = position.coords.latitude.toFixed(8);
                document.getElementById('longitud').value = position.coords.longitude.toFixed(8);
                hideLoading();
                mostrarMensaje('Ubicación detectada correctamente', 'success');
            },
            function() {
                hideLoading();
                mostrarMensaje('No se pudo obtener la ubicación', 'danger');
            }
        );
    } else {
        mostrarMensaje('Geolocalización no soportada por este navegador', 'danger');
    }
}

// Mostrar mensajes toast
function mostrarMensaje(mensaje, tipo = 'success') {
    const toastContainer = document.createElement('div');
    toastContainer.style.position = 'fixed';
    toastContainer.style.bottom = '20px';
    toastContainer.style.right = '20px';
    toastContainer.style.zIndex = '9999';
    
    const toast = document.createElement('div');
    toast.className = `alert alert-${tipo} alert-dismissible fade show`;
    toast.role = 'alert';
    toast.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    toastContainer.appendChild(toast);
    document.body.appendChild(toastContainer);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toastContainer.remove(), 300);
    }, 5000);
}

// Auto-submit en cambio de filtros
function autoSubmit(element) {
    element.form.submit();
}

// Formatear números
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Copiar al portapapeles
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        mostrarMensaje('Copiado al portapapeles', 'success');
    }, function() {
        mostrarMensaje('Error al copiar', 'danger');
    });
}

// Exportar tabla a CSV
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let row of rows) {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        for (let col of cols) {
            rowData.push('"' + col.textContent.replace(/"/g, '""') + '"');
        }
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    }
}

// Confirmar acción con SweetAlert (si está disponible)
function confirmarAccion(mensaje, callback) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Está seguro?',
            text: mensaje,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed && callback) {
                callback();
            }
        });
    } else {
        if (confirm(mensaje) && callback) {
            callback();
        }
    }
}

// Validar formularios antes de submit
function validarFormulario(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let valid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            valid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return valid;
}

// Contador de caracteres
function setupCharCounter(inputId, counterId, maxLength) {
    const input = document.getElementById(inputId);
    const counter = document.getElementById(counterId);
    
    if (input && counter) {
        input.addEventListener('input', function() {
            const remaining = maxLength - this.value.length;
            counter.textContent = remaining + ' caracteres restantes';
            
            if (remaining < 0) {
                counter.classList.add('text-danger');
            } else {
                counter.classList.remove('text-danger');
            }
        });
    }
}

// Inicializar tooltips de Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
