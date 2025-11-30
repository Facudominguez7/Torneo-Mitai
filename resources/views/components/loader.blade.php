<div id="loader" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50 hidden">
    <div class="w-16 h-16 border-4 border-blue-500 border-dashed rounded-full animate-spin"></div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const loader = document.getElementById('loader');

        // Escuchar el evento de envío en todos los formularios
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            form.addEventListener('submit', function () {
                // Mostrar el loader
                loader.classList.remove('hidden');
            });
        });
    });
</script>