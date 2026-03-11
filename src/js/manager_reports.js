// /src/js/manager_reports.js
// VERSIÓN FINAL COMPLETA CON LÓGICA DE REPORTES Y MANEJO DE ERRORES.

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Inicialización de Elementos y Variables
    const productMixResults = document.getElementById('productMixResults');
    const serviceMetricsResults = document.getElementById('serviceMetricsResults');
    const serviceServerSelect = document.getElementById('serviceServerSelect');
    const cancellationResults = document.getElementById('cancellationResults'); 
    const rotationResults = document.getElementById('rotationResults');
    
    const clockContainer = document.getElementById('liveClockContainer');
    
    // Botones (Asegurarse que los IDs coincidan en el HTML)
    const btnRunProductMix = document.getElementById('btnRunProductMix');
    const btnRunServiceMetrics = document.getElementById('btnRunServiceMetrics');
    const btnRunCancellationReport = document.getElementById('btnRunCancellationReport');
    const btnRunRotationReport = document.getElementById('btnRunRotationReport');
    
    // URL APIs
    const API_ENDPOINT = '/src/api/manager/reports/reports_api.php';
    const SERVERS_ENDPOINT = '/src/api/manager/get_active_servers.php'; 
    
    // Función para obtener fechas en formato YYYY-MM-DD
    const formatToDateInput = (dateObj) => {
        const now = dateObj || new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    // Establecer fechas por defecto (últimos 7 días)
    const today = new Date();
    const lastWeek = new Date(today);
    lastWeek.setDate(today.getDate() - 6);

    const todayStr = formatToDateInput(today);
    const lastWeekStr = formatToDateInput(lastWeek);

    // Inicializar todos los campos de fecha
    document.querySelectorAll('input[type="date"]').forEach(input => {
        if(input.id.includes('EndDate')) input.value = todayStr;
        else input.value = lastWeekStr;
    });

    // Función de Reloj
    function updateClock() {
        if (!clockContainer) return;
        const now = new Date();
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const month = months[now.getMonth()];
        const day = now.getDate();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        clockContainer.textContent = `${month} ${day} ${hours}:${minutes}:${seconds}`;
    }
    
    // ------------------------------------------------------------------
    // FUNCIÓN AJAX GENÉRICA (fetchReport)
    // ------------------------------------------------------------------
    
    const fetchReport = async (action, data, resultElement) => {
        resultElement.innerHTML = '<p class="loading-message" style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Cargando reporte...</p>';
        
        let rawResponseText = '';

        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action, ...data })
            });

            // Leer la respuesta como texto plano para capturar cualquier carácter oculto.
            rawResponseText = await response.text();
            
            // Intentar decodificar el JSON.
            const result = JSON.parse(rawResponseText); 

            if (result.success) {
                return result.data;
            } else {
                resultElement.innerHTML = `<p class="no-results" style="color:red;"><i class="fas fa-exclamation-circle"></i> Error de API: ${result.message || 'Error desconocido.'}</p>`;
                return null;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            
            // Muestra el texto que causó el error de JSON
            let debugMessage = `Error de JSON/Red: ${error.message}`;
            if (rawResponseText) {
                debugMessage += `<br>El servidor respondió con (inicio): 
                    <strong style="color: black; background-color: #ffcdd2; padding: 5px; word-break: break-all;">
                    ${rawResponseText.substring(0, 100)}...
                    </strong>`;
            }

            resultElement.innerHTML = `<p class="no-results" style="color:red;"><i class="fas fa-network-wired"></i> ${debugMessage}</p>`;
            return null;
        }
    };

    // ------------------------------------------------------------------
    // 💡 FUNCIÓN CORREGIDA: Llenar Select de Meseros (Llama al nuevo Endpoint)
    // ------------------------------------------------------------------
    
    const loadServers = async () => {
        if(!serviceServerSelect || serviceServerSelect.options.length > 1) return;
        
        try {
            // Muestra el spinner
            serviceMetricsResults.innerHTML = '<p class="loading-message" style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Cargando meseros...</p>';
            
            // Llama directamente al nuevo endpoint con GET
            const response = await fetch(SERVERS_ENDPOINT); 
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                // Añadir la opción 'Todos los Meseros'
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Todos los Meseros';
                serviceServerSelect.appendChild(defaultOption);
                
                // Añadir los meseros obtenidos
                result.data.forEach(server => {
                    const option = document.createElement('option');
                    option.value = server.id; 
                    option.textContent = server.name; 
                    serviceServerSelect.appendChild(option);
                });
                
                // Limpiar el mensaje de carga o dejar que el reporte lo reemplace
                serviceMetricsResults.innerHTML = '';
            } else {
                serviceMetricsResults.innerHTML = `<p class="no-results" style="color:red;"><i class="fas fa-exclamation-circle"></i> Error al cargar meseros: ${result.message || 'No se encontraron meseros.'}</p>`;
            }
        } catch (error) {
            console.error('Error loading servers:', error);
            serviceMetricsResults.innerHTML = `<p class="no-results" style="color:red;"><i class="fas fa-network-wired"></i> Error de conexión al cargar meseros.</p>`;
        }
    };
    
    // ------------------------------------------------------------------
    // 2. Control de Pestañas y Carga
    // ------------------------------------------------------------------
    
    const tabsContainer = document.getElementById('reportTabs');
    const tabContents = document.querySelectorAll('.report-tab-content');

    tabsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('report-tab-link')) {
            const tabId = e.target.dataset.tab;

            document.querySelectorAll('.report-tab-link').forEach(link => link.classList.remove('active'));
            tabContents.forEach(content => content.style.display = 'none');

            e.target.classList.add('active');
            const contentElement = document.getElementById(tabId);
            if(contentElement) contentElement.style.display = 'block';

            if (tabId === 'service-metrics') {
                loadServers();
            }
        }
    });

    // ------------------------------------------------------------------
    // 3. RENDERING DE REPORTES
    // ------------------------------------------------------------------
    
    // Reporte 1: Productos Más Vendidos (Product Mix)
    const renderProductMixTable = (data) => {
        if (!data || data.length === 0) {
            return '<p class="no-results"><i class="fas fa-box-open"></i> No se encontraron productos vendidos en el rango seleccionado.</p>';
        }

        let html = '<table class="results-table-report">';
        html += '<thead><tr><th>Producto</th><th class="numeric">Cantidad Vendida</th><th class="numeric">Total Bruto ($)</th></tr></thead>';
        html += '<tbody>';
        
        data.forEach((item, index) => {
            const rowClass = index === 0 ? 'highlight-row' : '';
            const total = parseFloat(item.total_bruto).toFixed(2);
            html += `<tr class="${rowClass}">
                        <td>${item.product_name}</td>
                        <td class="numeric">${item.total_quantity}</td>
                        <td class="numeric">$${total}</td>
                    </tr>`;
        });

        html += '</tbody></table>';
        return html;
    };

    // Reporte 2: Cancelaciones
    const renderCancellationTable = (data) => {
        const totalLost = data.reduce((sum, item) => sum + parseFloat(item.lost_revenue), 0);
        if (!data || data.length === 0) {
            return '<p class="no-results"><i class="fas fa-ban"></i> No se registraron cancelaciones en el rango seleccionado.</p>';
        }
        
        let html = `
            <div style="margin-bottom: 20px; background-color: #ffebeb; padding: 15px; border-radius: 8px; border-left: 5px solid #e74c3c;">
                <h4 style="margin: 0; color: #c0392b;">Pérdida Total por Cancelaciones: <span style="font-size: 1.5em; font-weight: bold;">$${totalLost.toFixed(2)}</span></h4>
            </div>
            <table class="results-table-report">
                <thead>
                    <tr>
                        <th>Producto Cancelado</th>
                        <th>Razón</th>
                        <th class="numeric">Cantidad</th>
                        <th class="numeric">Pérdida ($)</th>
                    </tr>
                </thead>
                <tbody>`;
        
        data.forEach(item => {
            const revenue = parseFloat(item.lost_revenue).toFixed(2);
            html += `<tr>
                        <td>${item.product_name}</td>
                        <td>${item.cancellation_reason || 'Sin razón especificada'}</td>
                        <td class="numeric">${item.total_canceled_qty}</td>
                        <td class="numeric">$${revenue}</td>
                    </tr>`;
        });

        html += '</tbody></table>';
        return html;
    };

    // Reporte 3: Métricas de Servicio (Personas Atendidas)
    const renderServiceMetricsTable = (data, totalServed) => {
        if (!data || data.length === 0 || totalServed === 0) {
             return `<p class="no-results"><i class="fas fa-user-slash"></i> No se registraron clientes atendidos en el rango seleccionado.</p>`;
        }
        
        let html = `
            <div style="margin-bottom: 20px; background-color: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 5px solid #4CAF50;">
                <h4 style="margin: 0; color: #1b5e20;">Total de Personas Atendidas en el Período: <span style="font-size: 1.5em; font-weight: bold;">${totalServed}</span></h4>
            </div>
            <table class="results-table-report">
                <thead>
                    <tr>
                        <th>Mesero</th>
                        <th class="numeric">Personas Atendidas</th>
                        <th class="numeric">% del Total</th>
                    </tr>
                </thead>
                <tbody>`;

        data.forEach(item => {
            const percentage = totalServed > 0 ? (item.served_people / totalServed * 100).toFixed(2) : 0;
            html += `<tr>
                        <td>${item.server_name}</td>
                        <td class="numeric">${item.served_people}</td>
                        <td class="numeric">${percentage}%</td>
                    </tr>`;
        });

        html += '</tbody></table>';
        return html;
    };
    
    // Reporte 4: Rotación (Tiempo promedio ocupado)
    const renderRotationTable = (data) => {
        if (data.total_tables_closed === 0) {
            return `<p class="no-results"><i class="fas fa-clock"></i> No hay mesas cerradas en este período para calcular rotación.</p>`;
        }

        let html = `
            <div style="margin-bottom: 20px; background-color: #e6f7ff; padding: 15px; border-radius: 8px; border-left: 5px solid #3498db;">
                <h4 style="margin: 0; color: #1e40af;">Métricas de Eficiencia de Servicio</h4>
            </div>
            <table class="results-table-report">
                <thead>
                    <tr>
                        <th>Métrica</th>
                        <th class="numeric">Valor</th>
                    </tr>
                </thead>
                <tbody>`;
        
        html += `<tr class="highlight-row"><td>Tiempo Promedio de Ocupación por Mesa</td><td class="numeric">${data.avg_minutes_occupied} minutos</td></tr>`;
        html += `<tr><td>Total de Mesas Cerradas (Muestra)</td><td class="numeric">${data.total_tables_closed}</td></tr>`;

        html += '</tbody></table>';
        return html;
    };


    // ------------------------------------------------------------------
    // 4. EVENT LISTENERS
    // ------------------------------------------------------------------

    // Evento Productos Mix
    document.getElementById('btnRunProductMix').addEventListener('click', async () => {
        const start_date = document.getElementById('productMixStartDate').value;
        const end_date = document.getElementById('productMixEndDate').value;
        
        if (!start_date || !end_date) {
            productMixResults.innerHTML = '<p class="no-results" style="color: orange;"><i class="fas fa-exclamation-triangle"></i> Por favor, selecciona ambas fechas.</p>';
            return;
        }

        const data = await fetchReport('get_product_mix', { start_date, end_date }, productMixResults);
        if (data) {
            productMixResults.innerHTML = renderProductMixTable(data);
        }
    });

    // Evento Cancelaciones
    document.getElementById('btnRunCancellationReport').addEventListener('click', async () => {
        const start_date = document.getElementById('cancellationStartDate').value;
        const end_date = document.getElementById('cancellationEndDate').value;
        
        if (!start_date || !end_date) {
            cancellationResults.innerHTML = '<p class="no-results" style="color: orange;"><i class="fas fa-exclamation-triangle"></i> Selecciona ambas fechas.</p>';
            return;
        }

        const data = await fetchReport('get_cancellation_report', { start_date, end_date }, cancellationResults);
        if (data) {
            cancellationResults.innerHTML = renderCancellationTable(data);
        }
    });

    // Evento Métricas de Servicio
    document.getElementById('btnRunServiceMetrics').addEventListener('click', async () => {
        const start_date = document.getElementById('serviceStartDate').value;
        const end_date = document.getElementById('serviceEndDate').value;
        const server_id = document.getElementById('serviceServerSelect').value || null; 
        
        if (!start_date || !end_date) {
            serviceMetricsResults.innerHTML = '<p class="no-results" style="color: orange;"><i class="fas fa-exclamation-triangle"></i> Por favor, selecciona ambas fechas.</p>';
            return;
        }

        const data = await fetchReport('get_service_metrics', { start_date, end_date, server_id }, serviceMetricsResults);
        
        if (data && data.metrics) {
             const totalServed = data.total_served;
             serviceMetricsResults.innerHTML = renderServiceMetricsTable(data.metrics, totalServed);
        } else if (data) {
             serviceMetricsResults.innerHTML = `<p class="no-results"><i class="fas fa-user-slash"></i> No se registraron clientes atendidos en el rango seleccionado.</p>`;
        }
    });

    // Evento Rotación
    document.getElementById('btnRunRotationReport').addEventListener('click', async () => {
        const start_date = document.getElementById('rotationStartDate').value;
        const end_date = document.getElementById('rotationEndDate').value;
        
        if (!start_date || !end_date) {
            rotationResults.innerHTML = '<p class="no-results" style="color: orange;"><i class="fas fa-exclamation-triangle"></i> Por favor, selecciona ambas fechas.</p>';
            return;
        }
        
        const data = await fetchReport('get_table_rotation', { start_date, end_date }, rotationResults);
        
        if (data) {
             rotationResults.innerHTML = renderRotationTable(data);
        }
    });


    // ------------------------------------------------------------------
    // 5. Inicialización
    // ------------------------------------------------------------------
    const initialTab = document.querySelector('.report-tab-link.active');
    if (initialTab) {
        const tabId = initialTab.dataset.tab;
        const contentElement = document.getElementById(tabId);
        if(contentElement) contentElement.style.display = 'block';
        if (tabId === 'service-metrics') loadServers();
    }
    
    // Inicialización del reloj
    updateClock();
    setInterval(updateClock, 1000);
});
