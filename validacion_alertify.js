window.mostrarNotificacionError = function (mensaje, segundos) {
    segundos = segundos || 8;
    if (window.alertify && typeof alertify.error === 'function') {
        alertify.error(mensaje, segundos);
        return;
    }

    const mostrar = function () {
        const aviso = document.createElement('div');
        aviso.setAttribute('role', 'alert');
        aviso.textContent = mensaje;
        aviso.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:99999;max-width:min(420px,calc(100vw - 40px));padding:14px 18px;border-radius:8px;background:#b42318;color:#fff;box-shadow:0 4px 18px #0004;font:14px/1.45 sans-serif;';
        document.body.appendChild(aviso);
        window.setTimeout(function () { aviso.remove(); }, segundos * 1000);
    };
    if (document.body) mostrar();
    else document.addEventListener('DOMContentLoaded', mostrar, { once: true });
};

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
        form.noValidate = true;
        form.addEventListener('submit', function (event) {
            const campo = form.querySelector(':invalid');
            if (!campo) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            const etiqueta = campo.labels && campo.labels.length
                ? campo.labels[0].textContent.trim()
                : (campo.getAttribute('aria-label') || campo.name || 'Campo');
            const mensaje = etiqueta + ': ' + (campo.validationMessage || 'Revisa este valor.');
            window.mostrarNotificacionError(mensaje, 8);
            campo.focus();
        }, true);
    });
});
