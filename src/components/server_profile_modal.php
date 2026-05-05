<!-- /src/components/server_profile_modal.php -->
<!-- Modal flotante de perfil del mesero. Se activa desde .user-details en orders.php -->

<div id="serverProfileModal" class="sp-overlay" role="dialog" aria-modal="true" aria-labelledby="sp-title">
    <div class="sp-card">
        <button class="sp-close-btn" id="spCloseBtn" title="Cerrar">
            <i class="fas fa-times"></i>
        </button>

        <!-- Cabecera -->
        <div class="sp-header">
            <div class="sp-avatar">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="sp-header-info">
                <h2 class="sp-name" id="sp-title">—</h2>
                <span class="sp-badge"><i class="fas fa-circle"></i> Turno activo</span>
            </div>
        </div>

        <!-- Estado de carga -->
        <div class="sp-loading" id="spLoading">
            <i class="fas fa-spinner fa-spin"></i> Cargando estadísticas...
        </div>

        <!-- Cuerpo: estadísticas -->
        <div class="sp-body" id="spBody" style="display:none;">

            <div class="sp-section-label">Turno actual</div>
            <div class="sp-stats-grid">

                <div class="sp-stat-card sp-highlight">
                    <i class="fas fa-coins"></i>
                    <div class="sp-stat-value" id="sp-propinas">$0.00</div>
                    <div class="sp-stat-label">Propinas en tarjeta</div>
                </div>

                <div class="sp-stat-card">
                    <i class="fas fa-receipt"></i>
                    <div class="sp-stat-value" id="sp-venta">$0.00</div>
                    <div class="sp-stat-label">Venta total</div>
                </div>

                <div class="sp-stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="sp-stat-value" id="sp-cuentas">0</div>
                    <div class="sp-stat-label">Cuentas cerradas</div>
                </div>

                <div class="sp-stat-card">
                    <i class="fas fa-users"></i>
                    <div class="sp-stat-value" id="sp-clientes">0</div>
                    <div class="sp-stat-label">Clientes atendidos</div>
                </div>

                <div class="sp-stat-card">
                    <i class="fas fa-chart-line"></i>
                    <div class="sp-stat-value" id="sp-ticket">$0.00</div>
                    <div class="sp-stat-label">Ticket promedio</div>
                </div>

                <div class="sp-stat-card">
                    <i class="fas fa-door-open"></i>
                    <div class="sp-stat-value" id="sp-mesas">0</div>
                    <div class="sp-stat-label">Mesas abiertas</div>
                </div>

            </div>

            <div class="sp-shift-time">
                <i class="fas fa-clock"></i>
                Turno desde: <strong id="sp-shift-start">—</strong>
            </div>

            <div class="sp-section-label sp-section-label-table">Desglose por origen</div>
            <div class="sp-breakdown-wrap">
                <table class="sp-breakdown-table" aria-label="Desglose de métricas por origen">
                    <thead>
                        <tr>
                            <th>Métrica</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Propinas en tarjeta</td>
                            <td id="sp-row-propinas">$0.00</td>
                        </tr>
                        <tr>
                            <td>Venta total</td>
                            <td id="sp-row-venta">$0.00</td>
                        </tr>
                        <tr>
                            <td>Cuentas cerradas</td>
                            <td id="sp-row-cuentas">0</td>
                        </tr>
                        <tr>
                            <td>Clientes atendidos</td>
                            <td id="sp-row-clientes">0</td>
                        </tr>
                        <tr>
                            <td>Ticket promedio</td>
                            <td id="sp-row-ticket">$0.00</td>
                        </tr>
                        <tr>
                            <td>Mesas abiertas</td>
                            <td id="sp-row-mesas">0</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Error -->
        <div class="sp-error" id="spError" style="display:none;">
            <i class="fas fa-exclamation-circle"></i>
            <span id="spErrorMsg">No se pudieron cargar las estadísticas.</span>
        </div>
    </div>
</div>