            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (opcional, para algunas funcionalidades) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Script personalizado -->
    <script>
        // Confirmación para eliminar
        function confirmarEliminar(mensaje = '¿Está seguro de que desea eliminar este registro?') {
            return confirm(mensaje);
        }
        
        // Mostrar mensajes toast
        function mostrarMensaje(mensaje, tipo = 'success') {
            const toast = `
                <div class="toast align-items-center text-white bg-${tipo} border-0 position-fixed bottom-0 end-0 m-3" 
                     role="alert" style="z-index: 9999;">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${mensaje}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                                data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', toast);
            const toastElement = document.body.lastElementChild;
            const bsToast = new bootstrap.Toast(toastElement);
            bsToast.show();
            
            setTimeout(() => toastElement.remove(), 5000);
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Mobile sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            if (sidebarToggle && sidebar && sidebarOverlay) {
                // Toggle sidebar on button click
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                });
                
                // Close sidebar when clicking overlay
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                });
                
                // Close sidebar when clicking a link (for mobile)
                const sidebarLinks = sidebar.querySelectorAll('.nav-link');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 769) {
                            sidebar.classList.remove('show');
                            sidebarOverlay.classList.remove('show');
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
