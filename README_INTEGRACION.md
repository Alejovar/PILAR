# KitchenLink — Sistema de Reconocimiento Facial y Checador
## Guía de Integración Completa

---

## 📁 Estructura de Archivos Entregados

```
KitchenLink_facial/
├── docker/
│   └── migration_facial_attendance.sql      ← Ejecutar en BD primero
│
├── login.php                                 ← Reemplaza login.php raíz
│
├── src/
│   ├── css/
│   │   └── login_facial.css                 ← Nuevo CSS (importar en login.php, ya incluido)
│   │
│   ├── js/
│   │   ├── face_login.js                    ← Facial en login
│   │   ├── checador_widget.js               ← Widget del checador en login
│   │   ├── manager_users_face.js            ← Facial en gestión de usuarios
│   │   ├── manager_users_face_patch.js      ← Instrucciones para parchear manager_users.js
│   │   └── manager_checador.js              ← Reporte de asistencia del gerente
│   │
│   ├── php/
│   │   ├── manager_users.php               ← Reemplaza el existente (nueva columna Rostro)
│   │   └── manager_checador.php            ← Nuevo módulo del gerente
│   │
│   └── api/
│       ├── face/
│       │   ├── get_descriptors.php          ← Devuelve descriptores para matching
│       │   └── facial_login.php             ← Crea sesión tras match facial
│       │
│       ├── attendance/
│       │   ├── record_attendance.php        ← Registra entrada/salida
│       │   ├── get_attendance.php           ← Historial (empleado o gerente)
│       │   └── get_employees_list.php       ← Lista para filtro del gerente
│       │
│       └── manager/users/
│           ├── save_face.php                ← Guarda/borra descriptor facial
│           └── get_all_users.php            ← Actualizado con campo has_face
```

---

## 🗄️ PASO 1: Migración de Base de Datos

Ejecuta en tu MySQL/MariaDB:

```sql
-- migration_facial_attendance.sql
ALTER TABLE `users`
  ADD COLUMN `face_descriptor` TEXT DEFAULT NULL AFTER `session_token`;

CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`   INT(11)      NOT NULL,
  `type`      ENUM('ENTRADA','SALIDA') NOT NULL,
  `method`    ENUM('FACIAL','MANUAL') NOT NULL DEFAULT 'FACIAL',
  `timestamp` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment`   VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`, `timestamp`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

---

## 🤖 PASO 2: Modelos de face-api.js

Descarga los modelos **tiny** de face-api.js y colócalos en `/src/face-models/`:

```
https://github.com/vladmandic/face-api/tree/master/model
```

Archivos necesarios (pesa ~3 MB en total):
```
/src/face-models/
├── tiny_face_detector_model-shard1
├── tiny_face_detector_model-weights_manifest.json
├── face_landmark_68_tiny_model-shard1
├── face_landmark_68_tiny_model-weights_manifest.json
├── face_recognition_model-shard1
├── face_recognition_model-shard2
└── face_recognition_model-weights_manifest.json
```

> Los modelos "tiny" son más rápidos que los normales, ideales para tablets de meseros.

---

## 📋 PASO 3: Copiar Archivos

| Archivo entregado | Destino en el proyecto |
|---|---|
| `login.php` | `/login.php` (raíz) |
| `src/css/login_facial.css` | `/src/css/login_facial.css` |
| `src/js/face_login.js` | `/src/js/face_login.js` |
| `src/js/checador_widget.js` | `/src/js/checador_widget.js` |
| `src/js/manager_users_face.js` | `/src/js/manager_users_face.js` |
| `src/js/manager_checador.js` | `/src/js/manager_checador.js` |
| `src/php/manager_users.php` | `/src/php/manager_users.php` |
| `src/php/manager_checador.php` | `/src/php/manager_checador.php` |
| `src/api/face/get_descriptors.php` | `/src/api/face/get_descriptors.php` |
| `src/api/face/facial_login.php` | `/src/api/face/facial_login.php` |
| `src/api/attendance/record_attendance.php` | `/src/api/attendance/record_attendance.php` |
| `src/api/attendance/get_attendance.php` | `/src/api/attendance/get_attendance.php` |
| `src/api/attendance/get_employees_list.php` | `/src/api/attendance/get_employees_list.php` |
| `src/api/manager/users/save_face.php` | `/src/api/manager/users/save_face.php` |
| `src/api/manager/users/get_all_users.php` | `/src/api/manager/users/get_all_users.php` |

---

## ✏️ PASO 4: Parchear manager_users.js

Abre `/src/js/manager_users.js` y localiza la función que renderiza las filas de la tabla.
Agrega las funciones `renderFaceCell(u)` y `renderFaceBtn(u)` del archivo `manager_users_face_patch.js`.

**En el `<thead>` de la tabla**, añade una columna:
```html
<th>Rostro</th>
```

**En el template de cada `<tr>`**, añade:
```js
<td>${renderFaceCell(u)}</td>
// Y en la columna de acciones:
${renderFaceBtn(u)}
```

---

## 🔒 PASO 5: Nginx — rutas nuevas (si aplica)

Si usas el `nginx.conf` del proyecto, asegúrate de que las rutas `/src/api/face/` y `/src/api/attendance/` pasen a PHP:

```nginx
location ~ \.php$ {
    fastcgi_pass php:9000;
    # ... resto de config existente
}
```
Las rutas son archivos `.php` estándar, no necesitan configuración especial extra.

---

## 🔑 Cómo funciona el reconocimiento facial

### Seguridad del modelo
- El **matching** sucede **en el cliente** (JavaScript + face-api.js).
- Solo se envía al servidor el **user_id** identificado — nunca la imagen ni el video.
- El servidor crea la sesión PHP normal y redirige por rol.
- El descriptor facial (128 floats ~500 bytes) se guarda en `TEXT` en MySQL.

### Umbral de coincidencia
El umbral (`THRESHOLD = 0.48`) controla la sensibilidad:
- **0.4** = muy estricto (puede rechazar al mismo usuario con diferente luz)
- **0.5** = equilibrado (recomendado)
- **0.6** = permisivo (mayor riesgo de confundir usuarios)

Ajústalo en `face_login.js` y `checador_widget.js`.

---

## 📱 Uso en Tablets (Meseros)

La cámara frontal funciona en Chrome/Safari en iOS y Android.  
Asegúrate de que el servidor sirva por **HTTPS** (requerido para acceder a `getUserMedia` en móviles).

---

## 🎯 Flujo Completo

### Login con reconocimiento facial:
1. El usuario abre `login.php`
2. La cámara se activa automáticamente
3. face-api.js detecta el rostro y computa el descriptor (128 floats)
4. Se compara contra todos los descriptores en BD
5. Al detectar 2 coincidencias consecutivas (ajustable), se autentica
6. Si falla la cámara → formulario de usuario/contraseña automáticamente

### Registrar rostro (Gerente):
1. Ir a `Gestión de Personal` → columna `Rostro`
2. Click en el ícono de cámara del empleado
3. El empleado mira a la cámara → Pulsar "Capturar Rostro"
4. El descriptor se guarda en BD

### Checador:
1. En `login.php`, click en **"Ir al Checador"** (panel morado)
2. Animación → el checador aparece en el panel blanco
3. Empleado mira a la cámara → Pulsar **ENTRADA** o **SALIDA**
4. Se imprime ticket automáticamente
5. El empleado puede ver su historial filtrando por fechas

### Reporte del Gerente:
- `Gestión → Checador de Asistencia`
- Filtra por empleado + rango de fechas
- Exporta a CSV

---

## ⚠️ Notas Importantes

- Los modelos de face-api.js deben estar en `/src/face-models/` (no hay CDN para los pesos del modelo).
- El video se procesa **localmente** — no se sube a ningún servidor.
- Para producción, considera firmar las peticiones de `facial_login.php` con un token de un solo uso.
- El checador **no requiere sesión iniciada** — es un módulo público pensado para una tablet fija.
