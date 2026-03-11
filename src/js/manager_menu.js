// /src/js/manager_menu.js

document.addEventListener('DOMContentLoaded', () => {
    
    // --- ELEMENTOS DEL DOM ---
    const categoriesList = document.getElementById('categoriesList');
    const productsGrid = document.getElementById('productsGrid');
    const currentCategoryTitle = document.getElementById('currentCategoryTitle');
    const btnAddCategory = document.getElementById('btnAddCategory');
    const btnAddProduct = document.getElementById('btnAddProduct');
    const productSearch = document.getElementById('productSearch');
    const clockContainer = document.getElementById('liveClockContainer');

    // Modales Principales
    const categoryModal = document.getElementById('categoryModal');
    const categoryForm = document.getElementById('categoryForm');
    const productModal = document.getElementById('productModal');
    const productForm = document.getElementById('productForm');

    // Modales de Modificadores
    const modifiersModal = document.getElementById('modifiersManagerModal');
    const modGroupsList = document.getElementById('modGroupsList');
    const modOptionsList = document.getElementById('modOptionsList');
    const selectedGroupNameTitle = document.getElementById('selectedGroupName');
    const btnAddModOption = document.getElementById('btnAddModOption');
    const modGroupFormModal = document.getElementById('modGroupFormModal');
    const modOptionFormModal = document.getElementById('modOptionFormModal');

    // Inputs (incluyendo el nuevo moStock)
    const catId = document.getElementById('catId');
    const catName = document.getElementById('catName');
    const catArea = document.getElementById('catArea');
    const prodId = document.getElementById('prodId');
    const prodCategoryId = document.getElementById('prodCategoryId');
    const prodName = document.getElementById('prodName');
    const prodPrice = document.getElementById('prodPrice');
    const prodModifierGroup = document.getElementById('prodModifierGroup');
    const prodStock = document.getElementById('prodStock');

    // Variables de Estado
    let activeCategoryId = null;
    let activeModGroupId = null;
    let allProductsCache = []; 
    let globalProductsCache = []; // Para búsqueda universal

    // Rutas API
    const API = {
        GET_CATS: '/src/api/manager/menu/get_categories.php',
        GET_PRODS: '/src/api/manager/menu/get_products_by_category.php',
        SAVE_CAT: '/src/api/manager/menu/save_category.php',
        SAVE_PROD: '/src/api/manager/menu/save_product.php',
        TOGGLE_PROD: '/src/api/manager/menu/toggle_product_status.php',
        GET_MODIFIERS: '/src/api/manager/menu/get_modifier_groups.php',
        SAVE_MOD_GROUP: '/src/api/manager/menu/save_modifier_group.php',
        GET_MODS_BY_GROUP: '/src/api/manager/menu/get_modifiers_by_group.php',
        SAVE_MOD: '/src/api/manager/menu/save_modifier.php',
        TOGGLE_MOD: '/src/api/manager/menu/toggle_modifier_status.php',
        GET_ALL_PRODS_FLAT: '/src/api/manager/menu/get_all_products_flat.php' // Para el buscador global
    };

    // --- RELOJ ---
    function updateClock() {
        if(clockContainer) {
            const now = new Date();
            clockContainer.textContent = now.toLocaleDateString('es-MX', {month:'short', day:'numeric'}) + ' ' + now.toLocaleTimeString('en-US', {hour12: false});
        }
    }
    setInterval(updateClock, 1000); updateClock();

    // ===========================================================
    // 0. CARGA INICIAL UNIVERSAL
    // ===========================================================
    async function loadGlobalCache() {
        try {
            const res = await fetch(API.GET_ALL_PRODS_FLAT);
            const data = await res.json();
            if (data.success) {
                globalProductsCache = data.products;
            }
        } catch (e) {
            console.error("Fallo al cargar caché global para búsqueda.");
        }
    }


    // ===========================================================
    // 1. GESTIÓN DE CATEGORÍAS
    // ===========================================================

    async function loadCategories() {
        categoriesList.innerHTML = '<p style="text-align:center; padding:20px; color:#999;">Cargando...</p>';
        try {
            const res = await fetch(API.GET_CATS);
            const data = await res.json();
            
            if (data.success) {
                renderCategories(data.categories);
                
                if (!activeCategoryId && data.categories.length > 0) {
                    selectCategory(data.categories[0].category_id, data.categories[0].category_name);
                }
            } else {
                categoriesList.innerHTML = '<p style="text-align:center; padding:20px;">Error al cargar.</p>';
            }
        } catch (e) { console.error(e); }
    }

    function renderCategories(categories) {
        categoriesList.innerHTML = '';
        categories.forEach(cat => {
            const div = document.createElement('div');
            div.className = `category-item-row ${activeCategoryId == cat.category_id ? 'active' : ''}`;
            div.dataset.id = cat.category_id;
            div.innerHTML = `<span>${cat.category_name}</span>
                             <div class="cat-actions"><button class="btn-cat-edit"><i class="fas fa-pen"></i></button></div>`;
            div.addEventListener('click', (e) => {
                if (e.target.closest('.btn-cat-edit')) { openCategoryModal(cat); return; }
                selectCategory(cat.category_id, cat.category_name);
            });
            categoriesList.appendChild(div);
        });
    }

    function selectCategory(id, name) {
        activeCategoryId = id;
        currentCategoryTitle.textContent = name;
        btnAddProduct.disabled = false;
        document.querySelectorAll('.category-item-row').forEach(row => row.classList.toggle('active', row.dataset.id == id));
        loadProducts(id);
    }

    // ===========================================================
    // 2. GESTIÓN DE PRODUCTOS
    // ===========================================================
    async function loadProducts(categoryId) {
        productsGrid.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando productos...</p></div>';
        try {
            const res = await fetch(`${API.GET_PRODS}?category_id=${categoryId}`);
            const data = await res.json();
            if (data.success) {
                allProductsCache = data.products; 
                renderProducts(allProductsCache);
            } else {
                productsGrid.innerHTML = '<div class="empty-state"><p>No hay productos.</p></div>';
            }
        } catch (e) { productsGrid.innerHTML = '<div class="empty-state"><p>Error de conexión.</p></div>'; }
    }

    function renderProducts(products) {
        productsGrid.innerHTML = '';
        if (products.length === 0) {
            productsGrid.innerHTML = '<div class="empty-state"><i class="fas fa-box-open"></i><p>Categoría vacía.</p></div>';
            return;
        }

        products.forEach(prod => {
            const isActive = prod.is_available == 1;
            // Lógica visual de Stock (85)
            let stockBadge = '';
            if (prod.stock_quantity !== null) {
                const qty = parseInt(prod.stock_quantity);
                const colorClass = qty > 5 ? 'stock-ok' : (qty > 0 ? 'stock-low' : 'stock-out');
                stockBadge = `<span class="stock-badge ${colorClass}">Stock: ${qty}</span>`;
            }

            const card = document.createElement('div');
            card.className = `product-card ${isActive ? '' : 'inactive'}`;
            card.innerHTML = `
                <div class="prod-header">
                    <div class="prod-name">${prod.name}</div>
                    ${stockBadge}
                </div>
                <div class="prod-price">$${parseFloat(prod.price).toFixed(2)}</div>
                <div class="prod-details">
                    ${prod.modifier_group_name ? `<i class="fas fa-layer-group"></i> ${prod.modifier_group_name}` : ''}
                </div>
                <div class="prod-footer">
                    <label class="switch">
                        <input type="checkbox" class="toggle-prod-status" data-id="${prod.product_id}" ${isActive ? 'checked' : ''}>
                        <span class="slider"></span>
                    </label>
                    <button class="btn-icon edit btn-edit-prod" data-prod-id="${prod.product_id}"><i class="fas fa-pen"></i></button>
                </div>
            `;

            card.querySelector('.btn-edit-prod').addEventListener('click', () => openProductModal(prod));
            
            card.querySelector('.toggle-prod-status').addEventListener('change', (e) => {
                toggleProductStatus(prod.product_id, e.target.checked);
                if(e.target.checked) card.classList.remove('inactive'); else card.classList.add('inactive');
            });
            productsGrid.appendChild(card);
        });
    }

    // --- Buscador Universal (Utiliza la caché global) ---
    productSearch.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        
        // Si el término es muy corto, restauramos la vista de la categoría activa
        if (term.length < 3 && activeCategoryId !== null) {
             renderProducts(allProductsCache); // Vuelve a la caché de la categoría activa
             currentCategoryTitle.textContent = allProductsCache.length > 0 ? `Búsqueda en ${currentCategoryTitle.textContent}` : "Selecciona una categoría";
             btnAddProduct.disabled = false;
             return;
        } else if (term.length < 3) {
             productsGrid.innerHTML = '<div class="empty-state"><p>Ingresa al menos 3 letras para buscar.</p></div>';
             btnAddProduct.disabled = true;
             return;
        }

        const filtered = globalProductsCache.filter(p => p.name.toLowerCase().includes(term));
        renderProducts(filtered); 
        currentCategoryTitle.textContent = `Resultados de búsqueda (${filtered.length})`;
        
        // Deshabilitar la adición de productos cuando estamos en modo búsqueda universal
        btnAddProduct.disabled = true; 
    });


    // ===========================================================
    // 3. GESTOR DE MODIFICADORES Y FORMULARIOS
    // ===========================================================
    
    document.getElementById('btnManageModifiers').addEventListener('click', () => {
        modifiersModal.classList.add('visible');
        loadModGroups();
    });
    
    document.getElementById('closeModManager').addEventListener('click', () => modifiersModal.classList.remove('visible'));

    async function loadModGroups() {
        modGroupsList.innerHTML = '<p style="padding:10px; color:#999">Cargando...</p>';
        try {
            const res = await fetch(API.GET_MODIFIERS);
            const data = await res.json();
            if (data.success) {
                modGroupsList.innerHTML = '';
                data.groups.forEach(g => {
                    const div = document.createElement('div');
                    div.className = `mod-list-item ${activeModGroupId == g.group_id ? 'active' : ''}`;
                    div.innerHTML = `<span>${g.group_name}</span><div class="mod-item-actions"><button class="btn-icon edit btn-edit-group"><i class="fas fa-pen"></i></button></div>`;
                    div.addEventListener('click', (e) => {
                        if (e.target.closest('.btn-edit-group')) { openGroupForm(g); return; }
                        selectModGroup(g.group_id, g.group_name);
                    });
                    modGroupsList.appendChild(div);
                });
            }
        } catch(e) { console.error(e); }
    }

    function selectModGroup(id, name) {
        activeModGroupId = id;
        selectedGroupNameTitle.textContent = name;
        btnAddModOption.disabled = false;
        document.querySelectorAll('.mod-list-item').forEach(el => el.classList.remove('active'));
        loadModOptions(id);
    }

    async function loadModOptions(groupId) {
        modOptionsList.innerHTML = '<p style="padding:20px;">Cargando opciones...</p>';
        try {
            const res = await fetch(`${API.GET_MODS_BY_GROUP}?group_id=${groupId}`);
            const data = await res.json();
            if(data.success && data.modifiers.length > 0) {
                modOptionsList.innerHTML = '';
                data.modifiers.forEach(mod => {
                    const isActive = mod.is_active == 1;
                    const stockText = mod.stock_quantity !== null ? ` (Stock: ${mod.stock_quantity})` : '';
                    
                    const div = document.createElement('div');
                    div.className = 'option-item';
                    div.innerHTML = `
                        <div class="option-info ${isActive ? '' : 'inactive'}" style="${isActive ? '' : 'opacity:0.5'}">
                            <span class="option-name">${mod.modifier_name}</span>
                            <span class="option-price">+$${parseFloat(mod.modifier_price).toFixed(2)} ${stockText}</span>
                        </div>
                        <div class="option-controls">
                            <label class="switch">
                                <input type="checkbox" class="toggle-mod-status" data-id="${mod.modifier_id}" ${isActive ? 'checked' : ''}>
                                <span class="slider"></span>
                            </label>
                            <button class="btn-icon edit btn-edit-mod"><i class="fas fa-pen"></i></button>
                        </div>
                    `;
                    div.querySelector('.btn-edit-mod').addEventListener('click', () => openOptionForm(mod));
                    div.querySelector('.toggle-mod-status').addEventListener('change', (e) => toggleModStatus(mod.modifier_id, e.target.checked));
                    modOptionsList.appendChild(div);
                });
            } else {
                modOptionsList.innerHTML = '<div class="empty-state-small">Sin opciones aún.</div>';
            }
        } catch(e) { console.error(e); }
    }

    // ===========================================================
    // 4. FORMULARIOS Y LOGICA COMÚN
    // ===========================================================
    
    async function sendData(url, data, callback) {
        try {
            const res = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) });
            const result = await res.json();
            if (result.success) callback(); else alert("Error: " + result.message);
        } catch (e) { alert("Error de red."); }
    }

    async function toggleProductStatus(id, status) {
        try { await fetch(API.TOGGLE_PROD, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id: id, status: status ? 1 : 0 }) }); } catch(e) {}
    }
    async function toggleModStatus(id, status) {
        try { await fetch(API.TOGGLE_MOD, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id: id, status: status ? 1 : 0 }) }); } catch(e) {}
    }

    async function loadModifierGroupsSelect() {
        try {
            const res = await fetch(API.GET_MODIFIERS);
            const data = await res.json();
            if(data.success && prodModifierGroup) {
                prodModifierGroup.innerHTML = '<option value="">-- Ninguno --</option>';
                data.groups.forEach(g => {
                    const opt = document.createElement('option');
                    opt.value = g.group_id;
                    opt.textContent = g.group_name;
                    prodModifierGroup.appendChild(opt);
                });
            }
        } catch(e) {}
    }

    // -- Modales (Abrir/Cerrar) --
    function openCategoryModal(cat) {
        categoryModal.classList.add('visible');
        if (cat) {
            document.getElementById('catModalTitle').textContent = "Editar Categoría";
            catId.value = cat.category_id; catName.value = cat.category_name; catArea.value = cat.preparation_area || 'COCINA';
        } else {
            document.getElementById('catModalTitle').textContent = "Nueva Categoría";
            categoryForm.reset(); catId.value = "";
        }
    }

    function openProductModal(prod) {
        productModal.classList.add('visible');
        prodCategoryId.value = activeCategoryId;
        if (prod) {
            document.getElementById('prodModalTitle').textContent = "Editar Producto";
            prodId.value = prod.product_id; prodName.value = prod.name; prodPrice.value = prod.price;
            prodModifierGroup.value = prod.modifier_group_id || "";
            prodStock.value = prod.stock_quantity !== null ? prod.stock_quantity : "";
        } else {
            document.getElementById('prodModalTitle').textContent = "Nuevo Producto";
            productForm.reset(); prodId.value = ""; prodModifierGroup.value = "";
        }
    }

    function openGroupForm(group) {
        modGroupFormModal.classList.add('visible');
        const form = document.getElementById('modGroupForm'); form.reset();
        if(group) { document.getElementById('mgId').value = group.group_id; document.getElementById('mgName').value = group.group_name; }
        else document.getElementById('mgId').value = '';
    }

    function openOptionForm(mod) {
        modOptionFormModal.classList.add('visible');
        const form = document.getElementById('modOptionForm'); form.reset();
        document.getElementById('moGroupId').value = activeModGroupId;
        
        const moId = document.getElementById('moId');
        const moName = document.getElementById('moName');
        const moPrice = document.getElementById('moPrice');
        const moStock = document.getElementById('moStock'); 
        
        if(mod) {
            moId.value = mod.modifier_id;
            moName.value = mod.modifier_name;
            moPrice.value = mod.modifier_price;
            moStock.value = mod.stock_quantity !== null ? mod.stock_quantity : ""; 
        } else {
            moId.value = '';
            moStock.value = '';
        }
    }

    // Listeners de Modales
    categoryForm.addEventListener('submit', e => { e.preventDefault(); sendData(API.SAVE_CAT, { id: catId.value, name: catName.value, preparation_area: catArea.value }, () => { categoryModal.classList.remove('visible'); loadCategories(); }); });
    productForm.addEventListener('submit', e => { e.preventDefault(); sendData(API.SAVE_PROD, { id: prodId.value, category_id: prodCategoryId.value, name: prodName.value, price: prodPrice.value, modifier_group_id: prodModifierGroup.value, stock_quantity: prodStock.value }, () => { productModal.classList.remove('visible'); loadProducts(activeCategoryId); }); });
    document.getElementById('modGroupForm').addEventListener('submit', e => { e.preventDefault(); sendData(API.SAVE_MOD_GROUP, { id: document.getElementById('mgId').value, name: document.getElementById('mgName').value }, () => { modGroupFormModal.classList.remove('visible'); loadModGroups(); loadModifierGroupsSelect(); }); });
    document.getElementById('modOptionForm').addEventListener('submit', e => { e.preventDefault(); sendData(API.SAVE_MOD, { id: document.getElementById('moId').value, group_id: document.getElementById('moGroupId').value, name: document.getElementById('moName').value, price: document.getElementById('moPrice').value, stock_quantity: document.getElementById('moStock').value }, () => { modOptionFormModal.classList.remove('visible'); loadModOptions(activeModGroupId); }); });

    // Botones de Apertura y Cierre
    document.getElementById('btnAddCategory').addEventListener('click', () => openCategoryModal(null));
    document.getElementById('btnAddProduct').addEventListener('click', () => openProductModal(null));
    document.getElementById('btnAddModGroup').addEventListener('click', () => openGroupForm(null));
    document.getElementById('btnAddModOption').addEventListener('click', () => openOptionForm(null));
    document.getElementById('btnManageModifiers').addEventListener('click', () => { modifiersModal.classList.add('visible'); loadModGroups(); });

    document.querySelectorAll('.cancel-btn').forEach(btn => btn.addEventListener('click', () => {
        productModal.classList.remove('visible'); categoryModal.classList.remove('visible');
        modGroupFormModal.classList.remove('visible'); modOptionFormModal.classList.remove('visible');
    }));

    loadModifierGroupsSelect();
    loadCategories();
    loadGlobalCache();
});
