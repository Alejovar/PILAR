<<<<<<< HEAD
# KitchenLink 🍽️

<p align="center">
  <img src="documentation/images/logo.png" alt="Logo KitchenLink" width="300"/>
</p>

**KitchenLink** es un **ERP Transaccional en Tiempo Real** diseñado para la gestión operativa y financiera de restaurantes. No es un simple gestor de pedidos; es un sistema robusto que maneja concurrencia de usuarios, inventario volátil (85/86), flujos financieros complejos y comunicación instantánea entre áreas.

Actualmente el sistema **ya está desplegado y en funcionamiento** en la siguiente dirección:

🔗 **[KitchenLink — Sistema en línea](https://kitchenlink.host.adellya.my.id/KitchenLink/index.php)**

> ⚠️ **Nota:** Para registrar un nuevo usuario, se requieren las **credenciales de un gerente**.

---

## 🚀 Arquitectura y Características Técnicas Destacadas

Lo que diferencia a KitchenLink es su ingeniería interna optimizada para **alta disponibilidad en servidores de recursos limitados**.

### 🔒 Seguridad y Gestión de Sesiones
- **Single-Session Enforcement:** Implementación de **Sesión Única**. Si un usuario inicia sesión en otro dispositivo, el sistema detecta el nuevo token y **expulsa activamente** la sesión anterior.
- **Protección Anti-Bots:** Capa de seguridad en PHP para bloquear tráfico automatizado.
- **Row-Level Security:** Los meseros solo tienen permisos de escritura sobre sus propias mesas.
- **Bloqueo de Concurrencia (Table Locking):** Sistema de semáforos para evitar que el mesero y el gerente modifiquen la misma mesa simultáneamente.

### ⚡ Motor de Tiempo Real (Stock 85/86)
- **Inventario Vivo:** Sistema de "85" (Conteo) y "86" (Agotado).
- **Broadcast Sincronizado:** Si el Gerente marca que quedan "5 Hamburguesas", cada venta descuenta el stock y **actualiza las pantallas de todos los meseros en tiempo real**. Al llegar a cero, el producto se bloquea automáticamente en todos los dispositivos conectados.

---

## 🧩 Módulos y Funcionalidades Detalladas

### 1. 🍽️ Orders — Interfaz de Mesero 
La interfaz **Orders** permite a los meseros gestionar sus mesas y órdenes activas. Actualmente ya se encuentra totalmente implementada y funcional, con comunicación en tiempo real entre meseros, cocina y barra.

#### 🪑 Interfaz principal
- Permite **crear mesas nuevas**, mostrando el **tiempo en minutos** desde su creación y el **número de personas**.
- Incluye una **opción para editar mesa**, donde el mesero puede:
  - Agregar platillos y **dividirlos por tiempos** (Entrada, Plato fuerte, etc.).
  - **Agregar comentarios** individuales a los productos.
  - Usar una **barra de búsqueda** para localizar productos fácilmente.
  - **Agregar múltiples unidades** de un producto a la vez.
  - **Enviar la orden a cocina y/o barra**, regresando automáticamente a la vista principal.

#### ⚙️ Opciones avanzadas de mesa
Desde la selección de una mesa activa, el mesero puede:
- 🔢 **Cambiar número de mesa** o cantidad de personas.
- 🔁 **Mover productos** entre mesas.
- ❌ **Cancelar productos** individuales.
- 👨‍🍳 **Reasignar el mesero** responsable de una mesa.

#### ⏳ Órdenes pendientes
Nueva interfaz dinámica donde cada mesero visualiza sus órdenes pendientes en tiempo real.
- Cada producto cambia automáticamente de estado según el progreso en cocina o barra (**Pendiente → Preparando → Listo**).
- Muestra el **tiempo en minutos** desde que la orden fue enviada.
- Cuando una orden está completamente lista y el mesero la entrega, puede presionar **“Marcar como Entregado”**, eliminándola de su lista.

---

### 2. 👨‍🍳 Interfaz de Cocina 
Muestra todas las órdenes enviadas por los meseros en **tiempo real**, incluyendo:
- Nombre del mesero que la envió.
- Hora exacta de envío y **minutos transcurridos** desde entonces.
- Detalle completo de la orden (tiempos, productos y comentarios).

**Gestión de Estados:**
- Cada producto puede marcarse con un clic: 🕐 **Pendiente → Preparando → Listo**.
- Cuando todos los productos están listos, la orden completa pasa automáticamente al **Historial de Cocina**.

#### 🧾 Historial de Cocina
- Permite consultar órdenes completadas por fecha.
- Visualiza todos los productos procesados ese día junto con su hora y mesero correspondiente.

---

### 3. 🍹 Interfaz de Barra 
La interfaz de barra funciona de forma análoga a la de cocina, pero únicamente muestra productos pertenecientes a la barra.
- Gestiona el estado de cada bebida en tiempo real (**Pendiente → Preparando → Listo**).
- Cuando todas las bebidas de una orden están listas, se envía al **Historial de Barra**, con las mismas opciones de filtrado por fecha.

---

### 4. 🔔 Comunicación en Tiempo Real 
En la interfaz de Barra y Cocina, al momento de poner productos como "Listos":
- **Se muestra una notificación estética** por platillo o bebida en la pantalla principal de los meseros.
- **Justificación de UX:** No fue suficiente con solo verlo en la pantalla de órdenes pendientes; es más intuitivo recibir una alerta visual en la pantalla principal para agilizar el servicio.

---

### 5. 💳 Interfaz Principal de Caja 
Módulo financiero con lógica de negocio crítica.

#### Funciones de Cobro y Bloqueo
- **Imprimir Pre-ticket:**
  - Al imprimir un ticket, la mesa pasa a estar **bloqueada**.
  - En la interfaz del mesero cambia estéticamente y permite seguir accediendo al TPV pero **sin dejar ingresar más productos**.
  - **Seguridad:** Si el mesero intenta mandar una nueva orden en una mesa bloqueada, el sistema lo impide avisando que la mesa ya solicitó el ticket.
  
#### Finanzas y Cierre
- **Descuentos:** Permite agregar descuentos ya sea en monto decimal o en porcentajes.
- **Métodos de Pago:** Al cobrar la cuenta permite varios métodos, calculando el **cambio** (efectivo) o calculando la **propina** automáticamente si el pago en tarjeta excede el total.
- **Migración a Historial:** Al terminar el cobro, la mesa se borra de las tablas principales y se manda a las tablas de historial para futuros reportes y generación del ticket final.

---

### 6. 👨‍💼 Gerente (Administración y Operación Global)
El rol con mayores privilegios, diseñado para la supervisión y resolución de problemas en tiempo real.

- **Gestión Avanzada de Menú:** CRUD completo de Categorías y Productos. Incluye la creación de **Grupos de Modificadores** y modificadores específicos con costos extra (ej. "Con Queso +$10").
- **Control de Stock en Tiempo Real (85/86):** Panel de control para definir disponibilidad. Si un producto se marca como agotado, se bloquea instantáneamente en todos los dispositivos conectados.
- **Gestión de Usuarios:** Altas, bajas, edición de datos y asignación de roles para el personal.

#### 👁️ Supervisión y "Modo Dios"
- **Visión Global:** Acceso total a **todas las mesas activas** del restaurante, independientemente de qué mesero las tenga asignadas.
- **Asignación Flexible:** Capacidad de abrir mesas para sí mismo o **crear mesas y asignarlas directamente a otros meseros** (Delegación de tareas).
- **Acceso Privilegiado:** Ingreso directo al TPV y a las **Opciones Avanzadas** (cancelaciones, movimientos, cambios de comensales) **sin necesidad de re-ingresar contraseña**, permitiendo una gestión fluida y rápida durante la operación.

---

#### 📆 Gestión de Reservaciones
- **CRUD Completo:** Registro de nuevas reservaciones con validación de fecha, hora, número de personas (PAX) y datos del cliente.
- **Asignación Visual:** Interfaz interactiva para seleccionar mesas específicas basada en la disponibilidad real del mapa del restaurante.
- **Estado en Tiempo Real:** Visualización inmediata de mesas ocupadas, libres o reservadas para evitar empalmes (Overbooking).
- **Agenda del Día:** Consulta rápida de todas las reservaciones activas filtradas por el turno actual.
- **Historial:** Registro de reservaciones completadas, canceladas o No-Show para análisis posterior.

#### 📋 Lista de Espera Dinámica
- **Gestión de Walk-ins:** Registro rápido de clientes sin reservación que llegan al establecimiento.
- **Cálculo de Tiempos:** Estimación automática del tiempo de espera para informar al cliente.
- **Asignación Directa (Seat Client):** Funcionalidad para "Sentar" a un cliente de la lista directamente en una mesa liberada; esto crea la mesa en el sistema y cambia su estado a "Ocupada" automáticamente.
- **Gestión de Abandonos:** Registro de cancelaciones si el cliente decide no esperar, manteniendo limpia la cola de espera activa.
---

## 🛠️ Tecnologías utilizadas

- **Frontend:** `HTML5`, `CSS3`, `JavaScript (Vanilla)`  
- **Backend:** `PHP`  
- **Base de datos:** `MySQL`  
- **Servidor requerido:** Hosting o servidor con soporte para **PHP + MySQL** - **Compatibilidad:** Funciona en cualquier entorno con Apache, Nginx o similar.

---

## 🚀 Despliegue en servidor

Si deseas desplegar **KitchenLink** por tu cuenta, sigue estos pasos:

### 1. Requisitos
- Servidor web con soporte para:  
  - **PHP 7.4+ o superior** - **MySQL 5.7+ o MariaDB** - Archivos estáticos (`HTML`, `CSS`, `JS`)  
- Acceso a **phpMyAdmin** o consola MySQL para importar la base de datos.

> ⚠️ **Importante:** KitchenLink **no se ejecuta en local** (XAMPP/WAMP) por defecto; requiere un **servidor web real o hosting** compatible con PHP y MySQL.

### 2. Configuración de la base de datos
1. Crea una base de datos llamada `kitchenlink_db`.  
2. Importa el contenido del archivo `documentation/tables.txt` (estructura y datos base).

### 3. Configuración del proyecto
1. Sube la carpeta `KitchenLink` al directorio público de tu hosting (`public_html`, `htdocs`, etc.).  
2. Edita `KitchenLink/src/php/db_connection.php` con tus credenciales MySQL (usuario, contraseña y nombre de la BD).  
3. Accede desde tu navegador con la URL de tu dominio:  
`https://tusitio.com/KitchenLink/index.php`

---

## 📋 Notas importantes

### Creación de usuarios iniciales
Para registrar usuarios por primera vez (modo despliegue):  
1. Edita `KitchenLink/src/php/generar_hash.php` con la contraseña deseada.  
2. Ejecuta el archivo desde el navegador:  
`https://tusitio.com/KitchenLink/src/php/generar_hash.php`  
3. Copia el hash generado e insértalo manualmente en la base de datos junto con un nombre de usuario y rol.

### Registro de usuario 'Gerente'
- Insertar registro en la base de datos con el rol de **Gerente** y la password hasheada ya previamente generada, además de los otros datos.
- *Esto es necesario para poder acceder a los módulos del gerente (incluyendo el módulo de usuarios) y, de esta forma, poder crear más usuarios con distintos roles desde la interfaz. El paso anterior solo se hace manualmente cuando el sistema se despliega por primera vez.*

---

## 📚 Documentación del proyecto

Toda la documentación técnica, manuales, diagramas y archivos complementarios se encuentran disponibles en la carpeta:  

`/documentation/docs/`  

Ahí se incluyen los archivos PDF correspondientes al desarrollo, configuración, estructura de la base de datos y guías de usuario. La documentación se irá ampliando conforme avanza el proyecto.

---

## 💬 Contacto y soporte

Si deseas probar el sistema, solicitar acceso o recibir ayuda para desplegarlo:  
📩 **DM a [@imalejovar](https://www.instagram.com/imalejovar)** en Instagram.

---

<p align="center">
  <img src="documentation/images/logo.png" alt="Logo KitchenLink" width="600"/>
</p>
=======
# KitchenLink



## Getting started

To make it easy for you to get started with GitLab, here's a list of recommended next steps.

Already a pro? Just edit this README.md and make it your own. Want to make it easy? [Use the template at the bottom](#editing-this-readme)!

## Add your files

* [Create](https://docs.gitlab.com/ee/user/project/repository/web_editor.html#create-a-file) or [upload](https://docs.gitlab.com/ee/user/project/repository/web_editor.html#upload-a-file) files
* [Add files using the command line](https://docs.gitlab.com/topics/git/add_files/#add-files-to-a-git-repository) or push an existing Git repository with the following command:

```
cd existing_repo
git remote add origin https://gitlab.com/Alejovar/kitchenlink.git
git branch -M main
git push -uf origin main
```

## Integrate with your tools

* [Set up project integrations](https://gitlab.com/Alejovar/kitchenlink/-/settings/integrations)

## Collaborate with your team

* [Invite team members and collaborators](https://docs.gitlab.com/ee/user/project/members/)
* [Create a new merge request](https://docs.gitlab.com/ee/user/project/merge_requests/creating_merge_requests.html)
* [Automatically close issues from merge requests](https://docs.gitlab.com/ee/user/project/issues/managing_issues.html#closing-issues-automatically)
* [Enable merge request approvals](https://docs.gitlab.com/ee/user/project/merge_requests/approvals/)
* [Set auto-merge](https://docs.gitlab.com/user/project/merge_requests/auto_merge/)

## Test and Deploy

Use the built-in continuous integration in GitLab.

* [Get started with GitLab CI/CD](https://docs.gitlab.com/ee/ci/quick_start/)
* [Analyze your code for known vulnerabilities with Static Application Security Testing (SAST)](https://docs.gitlab.com/ee/user/application_security/sast/)
* [Deploy to Kubernetes, Amazon EC2, or Amazon ECS using Auto Deploy](https://docs.gitlab.com/ee/topics/autodevops/requirements.html)
* [Use pull-based deployments for improved Kubernetes management](https://docs.gitlab.com/ee/user/clusters/agent/)
* [Set up protected environments](https://docs.gitlab.com/ee/ci/environments/protected_environments.html)

***

# Editing this README

When you're ready to make this README your own, just edit this file and use the handy template below (or feel free to structure it however you want - this is just a starting point!). Thanks to [makeareadme.com](https://www.makeareadme.com/) for this template.

## Suggestions for a good README

Every project is different, so consider which of these sections apply to yours. The sections used in the template are suggestions for most open source projects. Also keep in mind that while a README can be too long and detailed, too long is better than too short. If you think your README is too long, consider utilizing another form of documentation rather than cutting out information.

## Name
Choose a self-explaining name for your project.

## Description
Let people know what your project can do specifically. Provide context and add a link to any reference visitors might be unfamiliar with. A list of Features or a Background subsection can also be added here. If there are alternatives to your project, this is a good place to list differentiating factors.

## Badges
On some READMEs, you may see small images that convey metadata, such as whether or not all the tests are passing for the project. You can use Shields to add some to your README. Many services also have instructions for adding a badge.

## Visuals
Depending on what you are making, it can be a good idea to include screenshots or even a video (you'll frequently see GIFs rather than actual videos). Tools like ttygif can help, but check out Asciinema for a more sophisticated method.

## Installation
Within a particular ecosystem, there may be a common way of installing things, such as using Yarn, NuGet, or Homebrew. However, consider the possibility that whoever is reading your README is a novice and would like more guidance. Listing specific steps helps remove ambiguity and gets people to using your project as quickly as possible. If it only runs in a specific context like a particular programming language version or operating system or has dependencies that have to be installed manually, also add a Requirements subsection.

## Usage
Use examples liberally, and show the expected output if you can. It's helpful to have inline the smallest example of usage that you can demonstrate, while providing links to more sophisticated examples if they are too long to reasonably include in the README.

## Support
Tell people where they can go to for help. It can be any combination of an issue tracker, a chat room, an email address, etc.

## Roadmap
If you have ideas for releases in the future, it is a good idea to list them in the README.

## Contributing
State if you are open to contributions and what your requirements are for accepting them.

For people who want to make changes to your project, it's helpful to have some documentation on how to get started. Perhaps there is a script that they should run or some environment variables that they need to set. Make these steps explicit. These instructions could also be useful to your future self.

You can also document commands to lint the code or run tests. These steps help to ensure high code quality and reduce the likelihood that the changes inadvertently break something. Having instructions for running tests is especially helpful if it requires external setup, such as starting a Selenium server for testing in a browser.

## Authors and acknowledgment
Show your appreciation to those who have contributed to the project.

## License
For open source projects, say how it is licensed.

## Project status
If you have run out of energy or time for your project, put a note at the top of the README saying that development has slowed down or stopped completely. Someone may choose to fork your project or volunteer to step in as a maintainer or owner, allowing your project to keep going. You can also make an explicit request for maintainers.
>>>>>>> b88ce2e20752da11ae3bf9bc0f9bb0ffc14b7a95
