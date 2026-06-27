# PILAR — Sistema de Control de Asistencia

Sistema web PHP/MySQL para control de asistencia de empleados con cálculo de horas trabajadas por catorcena.

## Stack
- **Backend**: PHP 8.2 + MySQL 8.0
- **Frontend**: HTML/CSS/JS vanilla (sin frameworks) + FontAwesome 6
- **Contenedores**: Docker + Docker Compose
- **Paleta**: Oscuro (#0f1117) + Amarillo (#F5C400) + Verde (#22C55E)

---

## Estructura

```
PILAR/
├── login.php                    ← Login admin/RRHH
├── checador.php                 ← Checador PÚBLICO (sin login, responsivo)
├── dashboard.php                ← Panel de control
├── index.php                    ← Redirige a login
├── docker/
│   └── schema_pilar.sql         ← Schema completo con datos de prueba
├── docker-compose.yml
├── Dockerfile
├── src/
│   ├── css/
│   │   ├── pilar.css            ← Design system completo (dark/amarillo/verde)
│   │   └── checador.css         ← Estilos del checador público
│   └── php/
│       ├── sidebar.php          ← Sidebar compartido
│       ├── logout.php
│       ├── db_connection.php
│       ├── plantas.php          ← Módulo CRUD plantas
│       ├── empleados.php        ← Módulo empleados + áreas/puestos
│       ├── historial.php        ← Reporte catorcena + exportar Excel
│       ├── historial_empleado.php ← Historial detallado por empleado
│       ├── login_handler.php
│       ├── security/
│       │   ├── check_session.php
│       │   └── check_session_api.php
│       └── api/
│           ├── dashboard_stats.php
│           ├── asistencia/
│           │   ├── buscar_empleado.php   ← Público
│           │   ├── estado_hoy.php        ← Público
│           │   └── registrar.php         ← Público
│           ├── plantas/
│           │   ├── get_plantas.php
│           │   └── save_planta.php
│           ├── areas_puestos/
│           │   ├── get_areas.php
│           │   ├── save_area.php
│           │   └── save_puesto.php
│           ├── empleados/
│           │   ├── get_empleados.php
│           │   ├── buscar.php
│           │   └── save_empleado.php
│           └── historial/
│               ├── get_catorcena.php
│               └── get_empleado.php
```

---

## Levantamiento rápido

```bash
# 1. Clona / copia el proyecto
cd PILAR

# 2. Levantar contenedores
docker compose up -d --build

# 3. Esperar ~15s a que MySQL inicialice, luego:
# http://localhost:8080/login.php

# Credenciales de prueba:
# Usuario: admin
# Contraseña: Admin1234
```

---

## Módulos

### 🟡 Checador público (`/checador.php`)
- Accesible **sin login** — para tablets/celulares en planta
- Busca empleado por **NSS** o **nombre**
- Registra 4 eventos en secuencia:  
  `Entrada → Salida comida → Regreso comida → Salida`
- Validación de secuencia en backend
- Confirmación antes de registrar + animación de éxito
- 100% responsivo (móvil primero)

### 🏭 Plantas (`/src/php/plantas.php`)
- CRUD completo: crear, editar, activar/desactivar
- Campos: nombre, código, ubicación, estado
- Buscador por nombre o código

### 👷 Empleados (`/src/php/empleados.php`)
- Tabla con NSS, nombre, área, puesto, planta, estado
- Filtros: nombre/NSS, área, puesto, activo/inactivo
- **Modal 1** — Nuevo/Editar empleado:  
  Nombre, apellido pat/mat, NSS, RFC, CURP, email, área, puesto, planta, estado
- **Modal 2** — Áreas y Puestos:  
  Crear áreas; crear puestos asociados a un área

### 📊 Historial catorcena (`/src/php/historial.php`)
- Período de **14 días** (seleccionas inicio, fin se calcula automático)
- Filtros: empleado, área, puesto
- Tabla: NSS, nombre, planta, área, puesto, horas trabajadas, horas extra
- **90 h = catorcena normal** | Horas extra = total - 90 (si > 90)
- Exporta Excel con **SheetJS** (sin servidor)

### 👤 Historial por empleado (`/src/php/historial_empleado.php`)
- Búsqueda por NSS o nombre
- Rango de fechas libre
- Tarjetas: días trabajados, total horas, horas extra
- Tabla día a día: Entrada | Salida comida | Regreso comida | Salida | Horas del día

---

## Lógica de horas

```
Horas_día = (salida_comida - entrada) + (salida - regreso_comida)
Total_catorcena = Σ horas_día de los 14 días
Horas_extra = MAX(0, total_catorcena - 90)
```

Tiempo por tiempo: si un día trabajas 10h y otro 6h, el sistema acumula todo.  
Solo se marcan como **extra** las horas que exceden las 90 de la catorcena completa.

---

## Variables de entorno

| Variable  | Default         |
|-----------|-----------------|
| DB_HOST   | db              |
| DB_USER   | pilar_user      |
| DB_PASS   | pilar_pass      |
| DB_NAME   | pilar_db        |

---

## Usuario admin por defecto

- **Username**: `admin`  
- **Password**: `Admin1234`  
- Cambiar el hash en `docker/schema_pilar.sql` antes de producción:

```php
echo password_hash('TuContraseña', PASSWORD_BCRYPT);
```
