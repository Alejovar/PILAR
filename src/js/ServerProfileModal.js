// /src/js/ServerProfileModal.js
// Módulo que gestiona el modal flotante de perfil del mesero.
// Se importa en orders.js y se inicializa con: new ServerProfileModal().init()

export class ServerProfileModal {

    constructor() {
        this.overlay    = document.getElementById('serverProfileModal');
        this.closeBtn   = document.getElementById('spCloseBtn');
        this.trigger    = document.querySelector('.user-details');

        this.loadingEl  = document.getElementById('spLoading');
        this.bodyEl     = document.getElementById('spBody');
        this.errorEl    = document.getElementById('spError');
        this.errorMsgEl = document.getElementById('spErrorMsg');

        // Campos de datos
        this.fields = {
            name:     document.getElementById('sp-title'),
            propinas: document.getElementById('sp-propinas'),
            venta:    document.getElementById('sp-venta'),
            cuentas:  document.getElementById('sp-cuentas'),
            clientes: document.getElementById('sp-clientes'),
            ticket:   document.getElementById('sp-ticket'),
            mesas:    document.getElementById('sp-mesas'),
            shift:    document.getElementById('sp-shift-start'),
        };

        this._loaded = false;
    }

    init() {
        if (!this.overlay || !this.trigger) return;

        // Abrir al hacer clic en .user-details
        this.trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            this._open();
        });

        // Cerrar con el botón X
        this.closeBtn.addEventListener('click', () => this._close());

        // Cerrar al hacer clic fuera de la tarjeta
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) this._close();
        });

        // Cerrar con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this._close();
        });
    }

    _open() {
        this.overlay.classList.add('sp-visible');
        if (!this._loaded) {
            this._fetchProfile();
        }
    }

    _close() {
        this.overlay.classList.remove('sp-visible');
    }

    _showLoading() {
        this.loadingEl.style.display = 'block';
        this.bodyEl.style.display    = 'none';
        this.errorEl.style.display   = 'none';
    }

    _showBody() {
        this.loadingEl.style.display = 'none';
        this.bodyEl.style.display    = 'block';
        this.errorEl.style.display   = 'none';
    }

    _showError(msg) {
        this.loadingEl.style.display  = 'none';
        this.bodyEl.style.display     = 'none';
        this.errorEl.style.display    = 'block';
        if (this.errorMsgEl) this.errorMsgEl.textContent = msg;
    }

    async _fetchProfile() {
        this._showLoading();
        try {
            const res  = await fetch('/src/api/orders/get_server_profile.php');
            const data = await res.json();

            if (!data.success) {
                this._showError(data.message || 'Error al cargar el perfil.');
                return;
            }

            this._render(data);
            this._loaded = true;

        } catch (err) {
            console.error('[ServerProfileModal] Error de red:', err);
            this._showError('No se pudo conectar con el servidor.');
        }
    }

    _fmt(value) {
        return '$' + parseFloat(value).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    _fmtDate(isoStr) {
        if (!isoStr) return '—';
        const d = new Date(isoStr);
        const pad = n => String(n).padStart(2, '0');
        return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    _render(data) {
        this.fields.name.textContent     = data.server_name      ?? '—';
        this.fields.propinas.textContent = this._fmt(data.propinas_tarjeta);
        this.fields.venta.textContent    = this._fmt(data.venta_total);
        this.fields.cuentas.textContent  = data.cuentas_cerradas ?? 0;
        this.fields.clientes.textContent = data.clientes_atendidos ?? 0;
        this.fields.ticket.textContent   = this._fmt(data.ticket_promedio);
        this.fields.mesas.textContent    = data.mesas_abiertas   ?? 0;
        this.fields.shift.textContent    = this._fmtDate(data.shift_start);
        this._showBody();
    }
}