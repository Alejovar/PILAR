// /src/js/manager_users.js

document.addEventListener('DOMContentLoaded', () => {
    
    const tableBody = document.getElementById('usersTableBody');
    const userModal = document.getElementById('userModal');
    const userForm = document.getElementById('userForm');
    const modalTitle = document.getElementById('modalTitle');
    const clockContainer = document.getElementById('liveClockContainer');
    
    // Inputs del formulario
    const inpId = document.getElementById('userId');
    const inpName = document.getElementById('userName');
    const inpNss = document.getElementById('userNSS');
    const inpPlant = document.getElementById('userPlant');
    const inpUser = document.getElementById('userLogin');
    const inpRole = document.getElementById('userRole');
    const inpPass = document.getElementById('userPassword');
    const inpSalaryDay = document.getElementById('userSalaryDay');
    const inpTaxRate = document.getElementById('userTaxRate');
    const inpOvertimeRate = document.getElementById('userOvertimeRate');
    const passHelp = document.getElementById('passHelpText');

    const API_ROUTES = {
        LIST: '/src/api/manager/users/get_all_users.php',
        SAVE: '/src/api/manager/users/save_user.php',
        TOGGLE: '/src/api/manager/users/toggle_user_status.php',
        ROLES: '/src/api/manager/users/get_roles.php'
    };

    function updateClock() {
        const now = new Date();
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const month = months[now.getMonth()];
        const day = now.getDate();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        if (clockContainer) clockContainer.textContent = `${month} ${day} ${hours}:${minutes}:${seconds}`;
    }
    
    updateClock();
    setInterval(updateClock, 1000);

    // 1. CARGAR ROLES
    async function loadRoles() {
        try {
            const res = await fetch(API_ROUTES.ROLES);
            const data = await res.json();
            if(data.success) {
                inpRole.innerHTML = '<option value="">-- Seleccionar Rol --</option>';
                data.roles.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = role.rol_name.charAt(0).toUpperCase() + role.rol_name.slice(1);
                    inpRole.appendChild(option);
                });
            }
        } catch (e) { console.error("Error roles", e); }
    }

    // 2. CARGAR USUARIOS
    async function loadUsers() {
        tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Cargando...</td></tr>';
        try {
            const res = await fetch(API_ROUTES.LIST);
            const data = await res.json();
            
            if (!data.success || data.users.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 20px;">No hay usuarios registrados.</td></tr>';
                return;
            }

            tableBody.innerHTML = '';
            data.users.forEach(u => {
                const tr = document.createElement('tr');
                
                const isActive = u.status === 'ACTIVO'; 
                const statusHtml = isActive 
                    ? '<span class="status-dot status-active"></span> Activo' 
                    : '<span class="status-dot status-inactive"></span> Inactivo';
                
                let roleClass = 'role-mesero';
                if(u.rol_name === 'gerente') roleClass = 'role-gerente';
                if(u.rol_name === 'cajero') roleClass = 'role-cajero';
                if(u.rol_name === 'jefe de cocina') roleClass = 'role-cocina';

                tr.innerHTML = `
                    <td>#${u.id}</td>
                    <td><strong>${u.name}</strong></td>
                    <td>${u.nss || '—'}</td>
                    <td>${u.plant || '—'}</td>
                    <td>${u.user}</td>
                    <td><span class="role-badge ${roleClass}">${u.rol_name}</span></td>
                    <td>${statusHtml}</td>
                    <td>${renderFaceCell(u)}</td>
                    <td>
                        <button class="btn-icon edit" title="Editar" data-json='${JSON.stringify(u)}'><i class="fas fa-edit"></i></button>
                        <button class="btn-icon toggle" title="${isActive ? 'Desactivar' : 'Activar'}" data-id="${u.id}" data-status="${u.status}">
                            <i class="fas ${isActive ? 'fa-toggle-on' : 'fa-toggle-off'}"></i>
                        </button>
                        ${renderFaceBtn(u)}
                    </td>
                `;
                tableBody.appendChild(tr);
            });
            
            document.querySelectorAll('.btn-icon.edit').forEach(btn => {
                btn.addEventListener('click', () => openEditModal(JSON.parse(btn.dataset.json)));
            });
            document.querySelectorAll('.btn-icon.toggle').forEach(btn => {
                btn.addEventListener('click', () => toggleUserStatus(btn.dataset.id, btn.dataset.status));
            });

        } catch (error) {
            console.error(error);
            tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; color:red;">Error al cargar datos.</td></tr>`;
        }
    }

    // 3. ABRIR MODAL
    function openEditModal(user = null) {
        userModal.classList.add('visible');
        resetPasswordVisibility();

        if (user) {
            modalTitle.textContent = "Editar Empleado";
            inpId.value = user.id;
            inpName.value = user.name;
            inpNss.value = user.nss || "";
            inpPlant.value = user.plant || "";
            inpUser.value = user.user;
            inpRole.value = user.rol_id;
            inpSalaryDay.value = user.salary_per_day || "";
            inpTaxRate.value = user.tax_rate || "";
            inpOvertimeRate.value = user.overtime_rate || "";
            inpPass.value = ""; 
            inpPass.placeholder = "(Sin cambios)";
            passHelp.style.display = "block";
        } else {
            modalTitle.textContent = "Registrar Nuevo Empleado";
            userForm.reset();
            inpId.value = "";
            inpPass.placeholder = "Obligatoria";
            passHelp.style.display = "none";
        }
    }

    // 4. GUARDAR
    userForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            id: inpId.value,
            name: inpName.value,
            nss: inpNss.value,
            plant: inpPlant.value,
            user: inpUser.value,
            role_id: inpRole.value,
            password: inpPass.value,
            salary_per_day: inpSalaryDay.value,
            tax_rate: inpTaxRate.value,
            overtime_rate: inpOvertimeRate.value
        };

        try {
            const res = await fetch(API_ROUTES.SAVE, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const result = await res.json();

            if (result.success) {
                window.appAlert("Usuario guardado correctamente.");
                userModal.classList.remove('visible');
                loadUsers();
            } else {
                window.appAlert("Error: " + result.message);
            }
        } catch (error) {
            window.appAlert("Error de red al guardar.");
        }
    });
    
    // 5. CAMBIAR ESTADO
    async function toggleUserStatus(id, currentStatus) {
        const newStatus = currentStatus === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        const action = newStatus === 'ACTIVO' ? "activar" : "desactivar";
        
        const confirmed = await window.appConfirm(`¿Estás seguro de ${action} a este usuario?`, 'Confirmar acción');
        if (!confirmed) return;

        try {
            const res = await fetch(API_ROUTES.TOGGLE, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id, status: newStatus })
            });
            const result = await res.json();
            
            if (result.success) {
                loadUsers();
            } else {
                window.appAlert("Error: " + result.message);
            }
        } catch (e) {
            window.appAlert("Error de red.");
        }
    }

    // 6. BUSCADOR
    const searchInput = document.getElementById('userSearchInput');
    
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const rows = tableBody.getElementsByTagName('tr');

            Array.from(rows).forEach(row => {
                const name = row.cells[1]?.textContent.toLowerCase() || '';
                const nss = row.cells[2]?.textContent.toLowerCase() || '';
                const user = row.cells[4]?.textContent.toLowerCase() || '';
                
                if (name.includes(term) || nss.includes(term) || user.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 7. TOGGLE PASSWORD
    const togglePassBtn = document.getElementById('togglePasswordBtn');
    
    if (togglePassBtn) {
        togglePassBtn.addEventListener('click', () => {
            const type = inpPass.getAttribute('type') === 'password' ? 'text' : 'password';
            inpPass.setAttribute('type', type);
            
            const icon = togglePassBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
        });
    }

    function resetPasswordVisibility() {
        if (inpPass && togglePassBtn) {
            inpPass.setAttribute('type', 'password');
            const icon = togglePassBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    document.getElementById('btnNewUser').addEventListener('click', () => openEditModal(null));
    document.getElementById('cancelUserModal').addEventListener('click', () => userModal.classList.remove('visible'));

    loadRoles();
    loadUsers();
});

// ── FACIAL: Indicador de rostro registrado ──
// Requiere que get_all_users.php devuelva `has_face` (0 o 1)
function renderFaceCell(user) {
    const hasFace = user.has_face == 1 || (user.face_descriptor && user.face_descriptor !== null);
    const cls  = hasFace ? 'yes' : 'no';
    const text = hasFace ? 'Registrado' : 'Sin rostro';
    return `<span><span class="face-badge ${cls}"></span>${text}</span>`;
}

// ── FACIAL: Botón de cámara para registrar/actualizar rostro ──
function renderFaceBtn(user) {
    const hasFace = user.has_face == 1 || (user.face_descriptor && user.face_descriptor !== null);
    const title   = hasFace ? 'Actualizar rostro' : 'Registrar rostro';
    return `
        <button
            data-face-btn="1"
            data-user-id="${user.id}"
            data-user-name="${user.name.replace(/"/g, '&quot;')}"
            data-has-face="${hasFace ? '1' : '0'}"
            title="${title}"
            style="background:none;border:1px solid #ccc;border-radius:6px;
                   padding:5px 9px;cursor:pointer;color:#7f00ff;font-size:13px;"
        >
            <i class="fas fa-camera"></i>
        </button>`;
}