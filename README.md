# Sistema de Control de Fotocopiadoras e Impresoras

Sistema web desarrollado en PHP 8.2 y MySQL para el control y gestión integral de equipos de impresión (fotocopiadoras e impresoras multifuncionales) del Ministerio Público - Fiscalía de la Nación del Perú.

## 📋 Características Principales

### Gestión de Equipos
- ✅ Registro completo de equipos con imágenes
- ✅ Clasificación por tipo (impresora/multifuncional)
- ✅ Control de marcas y modelos
- ✅ Estados de equipos (operativo, en mantenimiento, inoperativo, etc.)
- ✅ Datos de garantía y año de adquisición
- ✅ Asignación a usuarios finales y ubicaciones
- ✅ **Importación masiva desde Excel**

### Gestión de Mantenimientos
- ✅ Registro detallado de mantenimientos preventivos y correctivos
- ✅ Control de repuestos utilizados con gestión de stock
- ✅ Tipos de demanda (preventivo, correctivo, emergencia)
- ✅ Seguimiento de técnicos responsables
- ✅ Historial completo por equipo
- ✅ Listado visual con contador de repuestos

### Gestión de Repuestos
- ✅ Catálogo completo de repuestos con stock
- ✅ Control de compatibilidad por marca y modelo
- ✅ Registro de movimientos (entrada/salida/ajuste)
- ✅ Validación de stock en mantenimientos
- ✅ Historial de uso por repuesto

### Importación Masiva (Nuevo)
- ✅ **Carga masiva de Marcas desde Excel**
- ✅ **Carga masiva de Modelos desde Excel**
- ✅ **Carga masiva de Equipos desde Excel**
- ✅ Interfaz drag-and-drop
- ✅ Plantillas Excel descargables con ejemplos
- ✅ Previsualización de datos antes de importar
- ✅ Actualización automática de registros existentes
- ✅ Reporte detallado con errores por fila

### Sistema de Reportes
- ✅ Reportes estadísticos con gráficos interactivos (ApexCharts)
- ✅ Distribución de equipos por sede, estado, marca y modelo
- ✅ Análisis de mantenimientos por período
- ✅ Equipos sin mantenimiento
- ✅ Exportación a Excel y PDF

### Administración
- ✅ Sistema de autenticación con roles (Administrador, Encargado, Usuario)
- ✅ Gestión de usuarios del sistema
- ✅ Auditoría completa de operaciones (crear, modificar, eliminar, importar)
- ✅ Dashboard con estadísticas en tiempo real
- ✅ Gestión de ubicaciones (distritos fiscales, sedes, despachos, macro procesos)

### Interfaz
- ✅ Diseño moderno y responsive (Bootstrap 5.3)
- ✅ Tema claro/oscuro
- ✅ DataTables para tablas interactivas
- ✅ Gráficos con Chart.js y ApexCharts
- ✅ Notificaciones con SweetAlert2

## 🛠️ Requisitos del Sistema

### Servidor
- **PHP:** 8.2 o superior
- **MySQL:** 5.7 o superior / MariaDB 10.4 o superior
- **Servidor Web:** Apache 2.4 (XAMPP recomendado para desarrollo)

### Extensiones PHP Requeridas
- ✅ PDO
- ✅ PDO_MySQL
- ✅ mbstring
- ✅ json
- ✅ **gd** (requerida para importación Excel)
- ✅ **zip** (requerida para importación Excel)
- ✅ **xml** (requerida para importación Excel)
- ✅ **xmlreader** (requerida para importación Excel)

### Dependencias PHP (Composer)
```json
{
    "phpoffice/phpspreadsheet": "^1.29"
}
```

Instalación:
```bash
cd c:\xampp\htdocs\impresoras
composer install
```

### Navegadores Soportados
- Chrome 90+
- Firefox 88+
- Edge 90+
- Safari 14+

## 📦 Instalación

### 1. Clonar el repositorio

```bash
# Opción 1: Clonar directamente en htdocs
cd c:\xampp\htdocs
git clone https://github.com/programforrever/repoimpresoras.git impresoras

# Opción 2: Si ya tienes el proyecto, inicializar Git
cd c:\xampp\htdocs\impresoras
git init
git remote add origin https://github.com/programforrever/repoimpresoras.git
```

### 2. Configurar archivos de configuración

```bash
# Copiar archivos de ejemplo
copy config\config.example.php config\config.php
copy config\database.example.php config\database.php
```

Editar `config/config.php` y ajustar:
```php
define('BASE_URL', 'http://localhost/impresoras'); // Cambiar según tu entorno
```

Editar `config/database.php` con tus credenciales:
```php
private $host = "localhost";
private $db_name = "sistema_impresoras";
private $username = "root";
private $password = ""; // Tu contraseña de MySQL
```

### 3. Crear la base de datos

1. Iniciar XAMPP (Apache y MySQL)
2. Abrir phpMyAdmin: http://localhost/phpmyadmin
3. Ejecutar en orden los siguientes scripts SQL:

```bash
database/schema.sql                    # Estructura de tablas
database/datos_configuracion.sql       # Datos iniciales
database/add_marcas_modelos.sql        # Marcas y modelos
database/auditoria_equipos.sql         # Sistema de auditoría
```

### 4. Configurar permisos de directorios

```bash
# En Windows (XAMPP), dar permisos de escritura a:
uploads/equipos/                       # Para imágenes de equipos
```

### 5. Acceder al sistema

```
URL: http://localhost/impresoras
Usuario: admin
Contraseña: admin123
```

**⚠️ IMPORTANTE:** Cambiar la contraseña de administrador después del primer login.

## 🗂️ Estructura del Proyecto

```php
private $host = "localhost";
private $db_name = "sistema_impresoras";
private $username = "root";
private $password = "";
```

### 4. Verificar configuración de URL

Editar `config/config.php` si tu instalación de XAMPP usa un puerto diferente:

```php
define('BASE_URL', 'http://localhost/impresoras');
```

### 5. Acceder al sistema

Abrir en el navegador:
```
http://localhost/impresoras
```

**Credenciales por defecto:**
- Usuario: `admin`
- Contraseña: `admin123`

## 📁 Estructura del Proyecto

```
impresoras/
├── assets/              # Recursos estáticos
│   ├── css/            # Estilos personalizados
│   ├── js/             # Scripts JavaScript
│   └── img/            # Imágenes
├── config/             # Configuración
│   ├── config.php      # Configuración general
│   └── database.php    # Conexión a BD
├── controllers/        # Controladores
│   └── auth.php        # Autenticación
├── database/           # Scripts SQL
│   └── schema.sql      # Esquema de base de datos
├── includes/           # Archivos incluidos
│   ├── header.php      # Header del layout
│   ├── footer.php      # Footer del layout
│   └── functions.php   # Funciones de utilidad
├── models/             # Modelos de datos
│   ├── Usuario.php
│   ├── Equipo.php
│   └── Mantenimiento.php
├── uploads/            # Archivos subidos
├── views/              # Vistas
│   ├── dashboard.php   # Dashboard principal
│   ├── login.php       # Inicio de sesión
│   ├── equipos/        # Gestión de equipos
│   ├── mantenimientos/ # Gestión de mantenimientos
│   ├── usuarios/       # Gestión de usuarios
│   ├── reportes/       # Reportes
│   └── configuracion/  # Configuración del sistema
├── .htaccess          # Configuración Apache
└── index.php          # Punto de entrada
```

## 🎯 Requerimientos Funcionales Implementados

### ✅ RF-01 - Autenticación de usuarios
Sistema de login con usuario y contraseña, validación de credenciales y control de sesiones.

### ✅ RF-02 - Gestión de usuarios del sistema
CRUD completo de usuarios con asignación de roles y activación/desactivación.

### ✅ RF-03 - Gestión de equipos
Registro completo de equipos con todos los campos requeridos:
- Código patrimonial
- Clasificación (impresora/multifuncional)
- Marca, modelo, número de serie
- Garantía, estado, estabilizador
- Año de adquisición

### ✅ RF-04 - Gestión de ubicación del equipo
Asociación de equipos con:
- Distrito fiscal, Sede, Macro proceso
- Ubicación física, Despacho
- Usuario final responsable

### ✅ RF-05 - Registro de mantenimiento
Sistema completo de registro de mantenimientos con:
- Tipo de demanda
- Fecha de mantenimiento
- Historial por equipo

### ✅ RF-06 - Gestión de repuestos
Registro de repuestos con:
- Parte requerida
- Fecha de cambio
- Asociación a mantenimientos

### ✅ RF-07 - Historial del equipo
Visualización completa de:
- Historial de mantenimientos
- Historial de repuestos
- Cambios de estado

### ✅ RF-08 - Búsqueda y filtrado
Búsqueda avanzada por:
- Código patrimonial, Marca, Modelo
- Clasificación, Estado, Ubicación

### ✅ RF-09 - Reportes
Generación de reportes de:
- Equipos por estado
- Equipos por sede
- Mantenimientos por período
- Repuestos más utilizados

### ✅ RF-10 - Actualización de estado del equipo
Actualización automática mediante triggers de base de datos.

### ✅ RF-11 - Integración con datos iniciales
Script SQL preparado para carga de datos iniciales.

### ✅ RF-12 - Auditoría básica
Sistema automático de auditoría que registra:
- Usuario que realizó la acción
- Fecha y hora
- IP del usuario
- Datos anteriores y nuevos

## 🔐 Roles del Sistema

### Administrador (ID: 1)
- Acceso completo al sistema
- Gestión de usuarios
- Configuración del sistema
- Consulta de auditoría

### Encargado (ID: 2)
- Gestión de equipos
- Registro de mantenimientos
- Gestión de repuestos
- Generación de reportes

### Usuario (ID: 3)
- Solo consulta de información
- Visualización de equipos
- Consulta de mantenimientos

## 🗄️ Base de Datos

### Tablas principales:
- `usuarios` - Usuarios del sistema
- `roles` - Roles de usuario
- `equipos` - Fotocopiadoras/Impresoras
- `estados_equipo` - Estados posibles de equipos
- `mantenimientos` - Registro de mantenimientos
- `repuestos` - Repuestos utilizados
- `auditoria` - Log de auditoría

### Tablas de soporte:
- `distritos_fiscales`
- `sedes`
- `macro_procesos`
- `despachos`
- `usuarios_finales`
- `tipos_demanda`

## 🚀 Próximos Pasos

Para continuar el desarrollo:

1. **Completar vistas de CRUD:**
   - Crear formularios de equipos (crear.php, editar.php)
   - Crear formularios de mantenimientos
   - Crear formularios de usuarios

2. **Implementar módulo de reportes:**
   - Reportes en PDF con TCPDF o mPDF
   - Exportación a Excel con PhpSpreadsheet

3. **Agregar funcionalidades:**
   - Importación de datos desde Excel
   - Notificaciones de mantenimientos programados
   - Historial de cambios en equipos

4. **Mejorar seguridad:**
   - Implementar tokens CSRF
   - Validación adicional de permisos
   - Logs de seguridad

## 📝 Notas Importantes

- **Cambiar contraseña del admin:** Después de la instalación, cambiar la contraseña por defecto
- **Configurar permisos:** Dar permisos de escritura a la carpeta `uploads/`
- **Producción:** En producción, desactivar `display_errors` en `config/config.php`
- **Backups:** Realizar backups periódicos de la base de datos

## 🐛 Solución de Problemas

### Error de conexión a base de datos
- Verificar que MySQL esté corriendo en XAMPP
- Revisar credenciales en `config/database.php`
- Verificar que la base de datos `sistema_impresoras` exista

### Error 404 en las rutas
- Verificar que mod_rewrite esté habilitado en Apache
- Revisar la configuración de BASE_URL en `config/config.php`

### Sesión no funciona
- Verificar permisos de escritura en la carpeta temporal de PHP
- Revisar configuración de sesiones en php.ini

## 📄 Licencia

Sistema desarrollado para control interno de fotocopiadoras.

## 👨‍💻 Soporte

Para soporte o consultas sobre el sistema, contactar al administrador del sistema.
