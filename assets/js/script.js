// Custom JavaScript for nutrition clinic login page
// Bootstrap 5 handles most interactions now, but we can add custom enhancements here

// Optional: Add smooth scrolling or other effects if needed
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any custom functionality

    // Example: Auto-focus on the first input of the active tab
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');

    if (loginTab && registerTab) {
        loginTab.addEventListener('shown.bs.tab', function() {
            document.getElementById('loginEmail').focus();
        });

        registerTab.addEventListener('shown.bs.tab', function() {
            document.getElementById('registerName').focus();
        });
    }

    // Optional: Add loading state to buttons on form submit
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
                submitBtn.disabled = true;
            }
        });
    });
    // Enhance tables (sorting + global filter) for tables with class 'enhance-table'
    if (typeof enhanceTables === 'function') {
        enhanceTables();
    }
});

// Table enhancements: global filter + column sorting
function enhanceTables() {
    document.querySelectorAll('table.enhance-table').forEach(table => {
        // Determine whether table uses an external filter input
        const externalSelector = table.getAttribute('data-external-filter');
        const hasExternal = table.classList.contains('external-filter') || !!externalSelector;

        // If no external filter provided, create toolbar with global search
        let search = null;
        if (!hasExternal) {
            const toolbar = document.createElement('div');
            toolbar.style.display = 'flex';
            toolbar.style.justifyContent = 'space-between';
            toolbar.style.alignItems = 'center';
            toolbar.style.margin = '8px 0';

            search = document.createElement('input');
            search.type = 'search';
            search.placeholder = 'Buscar...';
            search.className = 'form-control';
            search.style.maxWidth = '320px';
            toolbar.appendChild(search);
            table.parentNode.insertBefore(toolbar, table);
        } else {
            // Use external input if selector provided
            if (externalSelector) {
                try {
                    const ext = document.querySelector(externalSelector);
                    if (ext && (ext.tagName === 'INPUT' || ext.tagName === 'SELECT')) {
                        search = ext;
                    }
                } catch (e) {
                    // invalid selector - ignore
                }
            }
        }

        // If we found an input to use for filtering, attach listener
        if (search) {
            search.addEventListener('input', () => {
                const q = search.value.trim().toLowerCase();
                table.querySelectorAll('tbody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = q === '' ? '' : (text.indexOf(q) === -1 ? 'none' : '');
                });
            });
        }

        // Make headers sortable
        const tbody = table.tBodies[0];
        if (!tbody) return;
        const headers = table.querySelectorAll('thead th');
        headers.forEach((th, idx) => {
            th.style.cursor = 'pointer';
            const sorter = document.createElement('span');
            sorter.style.marginLeft = '6px';
            sorter.innerHTML = '\u2195'; // up-down arrow
            th.appendChild(sorter);

            let asc = true;
            th.addEventListener('click', () => {
                const rows = Array.from(tbody.rows);
                rows.sort((a, b) => {
                    const aText = (a.cells[idx] && a.cells[idx].textContent.trim()) || '';
                    const bText = (b.cells[idx] && b.cells[idx].textContent.trim()) || '';
                    // numeric compare if both are numbers
                    const aNum = parseFloat(aText.replace(/[^0-9.-]+/g, ''));
                    const bNum = parseFloat(bText.replace(/[^0-9.-]+/g, ''));
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return asc ? aNum - bNum : bNum - aNum;
                    }
                    return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
                });
                // Re-attach rows
                rows.forEach(r => tbody.appendChild(r));
                asc = !asc;
                // Update arrow
                sorter.innerHTML = asc ? '\u2191' : '\u2193';
            });
        });
    });
}
