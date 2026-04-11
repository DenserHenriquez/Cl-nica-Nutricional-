document.addEventListener('DOMContentLoaded', function() {
    function showToast(message, type = 'danger') {
        var container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'toast-message ' + (type === 'success' ? 'success' : 'danger');
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function() { toast.classList.add('show'); }, 20);
        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.remove(); }, 250);
        }, 3600);
    }

    function initFormIntegrity(form) {
        var fields = Array.from(form.querySelectorAll('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])'));
        if (!fields.length) return;

        var originalValues = new Map();
        var userInteracted = new Map();

        fields.forEach(function(field) {
            var key = field.name || field.id;
            if (!key) return;
            var originalValue = field.dataset.originalValue !== undefined ? field.dataset.originalValue : field.value || '';
            originalValues.set(field, originalValue);
            userInteracted.set(field, false);

            var markInteracted = function() {
                userInteracted.set(field, true);
                field.classList.remove('integrity-failed');
            };
            ['input', 'change', 'focus', 'keydown', 'paste', 'mousedown', 'touchstart'].forEach(function(eventName) {
                field.addEventListener(eventName, markInteracted);
            });
        });

        form.addEventListener('submit', function(event) {
            var manipulated = fields.filter(function(field) {
                var originalValue = originalValues.get(field) || '';
                var currentValue = field.value || '';
                var touched = userInteracted.get(field);
                return currentValue !== originalValue && !touched;
            });

            if (manipulated.length > 0) {
                event.preventDefault();
                manipulated.forEach(function(field) {
                    field.classList.add('integrity-failed');
                });
                showToast('No se puede guardar: se detectó manipulación desde la consola del navegador.', 'danger');
            }
        });
    }

    document.querySelectorAll('form[method="post"]').forEach(initFormIntegrity);
});
