// assets/js/inactivity.js
// Detecta inactividad (2 minutos) y muestra modal con contador de 10 segundos.
(function(){
    // MODO PRUEBA: reducido a 10s. Cambiar a 2*60*1000 para producción.
    const INACTIVITY_LIMIT = 10 * 1000; // 10 segundos (prueba)
    const COUNTDOWN_START = 10; // segundos

    let inactivityTimer = null;
    let countdownTimer = null;
    let countdown = COUNTDOWN_START;
    let modalVisible = false;

    function getEl(id){ return document.getElementById(id); }
    const modal = getEl('inactivity-modal');
    const countEl = getEl('inactivity-count');
    const yesBtn = getEl('inactivity-yes');
    const logoutBtn = getEl('inactivity-logout');

    function startInactivityTimer(){
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => { showModal(); }, INACTIVITY_LIMIT);
    }

    function resetInactivityTimer(){
        if (modalVisible) return; // no reiniciamos mientras modal esté visible
        startInactivityTimer();
    }

    function showModal(){
        // Si estamos dentro de un iframe, pedimos al padre que muestre el modal
        if (window.top && window.top !== window.self) {
            try {
                console.log('[inactivity] posting message to parent to show modal, countdown=', COUNTDOWN_START);
                window.top.postMessage({ type: 'inactivity-show', countdown: COUNTDOWN_START }, '*');
                modalVisible = true;
            } catch (e) {
                console.warn('[inactivity] postMessage to parent failed:', e);
            }
            return;
        }

        // If we're the top window and the parent-level modal exists, trigger
        // the same message handler used for child->parent communication so
        // the modal rendering is consistent.
        if (document.getElementById('parent-inactivity-modal')) {
            try {
                console.log('[inactivity] top window — triggering parent modal via postMessage');
                window.postMessage({ type: 'inactivity-show', countdown: COUNTDOWN_START }, '*');
                modalVisible = true;
                return;
            } catch (e) {
                console.warn('[inactivity] postMessage to self failed:', e);
            }
        }

        if (!modal) return;
        modal.style.display = 'block';
        modalVisible = true;
        countdown = COUNTDOWN_START;
        if (countEl) countEl.textContent = String(countdown);

        countdownTimer = setInterval(() => {
            countdown -= 1;
            if (countEl) countEl.textContent = String(Math.max(0, countdown));
            if (countdown <= 0) {
                clearInterval(countdownTimer);
                doLogout();
            }
        }, 1000);
    }

    function hideModal(){
        if (!modal) return;
        modal.style.display = 'none';
        modalVisible = false;
        clearInterval(countdownTimer);
        countdown = COUNTDOWN_START;
        if (countEl) countEl.textContent = String(countdown);
    }

    function doLogout(){
        console.log('[inactivity] doLogout() — redirecting to index.php');
        try {
            top.location.href = 'index.php';
        } catch (e) {
            // Fallback if cross-origin or top not accessible
            window.location.href = 'index.php';
        }
    }

    // Eventos de actividad
    ['mousemove','mousedown','click','keydown','touchstart'].forEach(evt => {
        window.addEventListener(evt, resetInactivityTimer, { passive: true });
    });

    // Botones
    if (yesBtn) yesBtn.addEventListener('click', function(e){ e.preventDefault(); console.log('[inactivity] local YES clicked — hiding modal and restarting timer'); hideModal(); startInactivityTimer(); });
    if (logoutBtn) logoutBtn.addEventListener('click', function(e){ e.preventDefault(); console.log('[inactivity] local LOGOUT clicked'); doLogout(); });

    // Escuchar mensajes del padre (cuando el padre gestione el modal)
    window.addEventListener('message', function(ev){
        const m = ev && ev.data;
        if (!m || typeof m !== 'object') return;
        if (m.type === 'inactivity-cancel') {
            // Padre indica que se canceló la inactividad (presionó Sí)
            hideModal();
            startInactivityTimer();
        }
    });

    // Inicia inmediatamente (y como fallback en DOMContentLoaded)
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        startInactivityTimer();
    } else {
        document.addEventListener('DOMContentLoaded', function(){ startInactivityTimer(); });
    }
})();
