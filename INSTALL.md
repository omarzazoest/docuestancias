# Sistema de Gestión de Estancias

Sistema web para la gestión de estancias estudiantiles con autenticación por matrícula y generación de documentos PDF.

## 🚀 Características Principales

- **Autenticación simple**: Solo requiere matrícula del estudiante
- **Registro automático**: Si el estudiante no existe, se registra automáticamente
- **Gestión completa de datos**: Formularios organizados por secciones
- **Generación de PDFs**: Cartas oficiales habilitadas según datos disponibles
- **Dashboard intuitivo**: Progreso visual y acciones rápidas
- **Sesión inteligente**: Carga automática de todos los datos del estudiante

## 📋 Requisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache/Nginx
- Extensiones PHP: PDO, MySQL

## 🛠️ Instalación

### 1. Clonar o descargar archivos
```bash
# Colocar los archivos en tu directorio web (ej: htdocs para XAMPP)
cd /xampp/htdocs/docuestancias
```

### 2. Configurar Base de Datos
```sql
-- Crear la base de datos
CREATE DATABASE docsestancias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Importar el dump de la base de datos
mysql -u root -p docsestancias < sql_config/docsestancias.dump
```

### 3. Configurar Conexión
Editar `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'docsestancias');
define('DB_USER', 'tu_usuario'); // Cambiar según tu configuración
define('DB_PASS', 'tu_password'); // Cambiar según tu configuración
```

### 4. Permisos
```bash
# Dar permisos de escritura al directorio logs
chmod 755 logs/
chmod 666 logs/*.log
```

## 🎯 Uso del Sistema

### Acceso al Sistema
1. Navegar a `http://localhost/docuestancias`
2. Ingresar matrícula de 10 dígitos
3. Si es primera vez, completar registro básico
4. Acceder al dashboard principal

### Flujo de Trabajo
1. **Completar Datos**: Llenar formulario de datos para cartas
2. **Verificar Progreso**: Ver barra de completado en dashboard
3. **Generar Documentos**: Usar botones de PDF según disponibilidad
4. **Gestionar Estancias**: Consultar historial y estado

### Generación de PDFs
Los PDFs se habilitan automáticamente cuando se cumplen los requisitos:

- **Carta de Presentación**: Datos básicos completos
- **Carta de Cooperación**: Datos básicos + empresa
- **Carta de Término**: Datos completos + fechas de inicio/fin
- **Constancia**: Datos completos + estancia registrada

## 📁 Estructura del Proyecto

```
docuestancias/
├── config/           # Configuración de base de datos
│   └── database.php
├── views/            # Páginas de la aplicación
│   ├── login.php     # Página de login
│   ├── registro.php  # Registro de nuevos usuarios
│   ├── dashboard.php # Panel principal
│   ├── datos_cartas.php # Formulario de datos
│   ├── generar_pdf.php # Generador de PDFs
│   └── logout.php    # Cerrar sesión
├── includes/         # Funciones y utilidades
│   ├── security.php  # Funciones de seguridad
│   └── user_data.php # Gestión de datos de usuario
├── assets/           # Recursos estáticos
│   ├── css/style.css # Estilos personalizados
│   └── js/main.js    # JavaScript del sistema
├── logs/             # Archivos de log
├── sql_config/       # Base de datos
│   └── docsestancias.dump
└── index.php         # Punto de entrada
```

## 🔧 Funcionalidades Principales

### Sistema de Autenticación
- Login por matrícula únicamente
- Registro automático si no existe
- Validación de formato de matrícula (10 dígitos)
- Protección contra intentos masivos

### Gestión de Datos
- Formularios organizados por secciones
- Validación en tiempo real
- Autocompletado desde datos existentes
- Actualización automática de sesión

### Dashboard Inteligente
- Barra de progreso del perfil
- Indicadores de estado en menús
- Botones de PDF habilitados condicionalmente
- Resumen de datos y estadísticas

### Generación de Documentos
- Carta de Presentación
- Carta de Cooperación
- Carta de Término
- Constancia de Estancia

## 🛡️ Seguridad Implementada

- Sanitización de datos de entrada
- Validación de tipos de datos
- Protección contra inyección SQL
- Headers de seguridad HTTP
- Logs de actividad del sistema
- Control de sesiones seguro

## 📊 Base de Datos

### Tablas Principales
- `alumnos`: Datos básicos de estudiantes
- `datos_cartas`: Información completa para documentos
- `estancias`: Registro de estancias realizadas
- `organizaciones`: Empresas/instituciones
- `cooperacion`: Proyectos de cooperación

## 🎨 Interfaz de Usuario

- **Framework**: Bootstrap 5
- **Iconos**: Font Awesome 6
- **Diseño**: Responsive y accesible
- **Colores**: Esquema profesional
- **UX**: Navegación intuitiva

## 🔄 Actualizaciones y Mantenimiento

### Logs del Sistema
- `logs/activity.log`: Actividades de usuarios
- `logs/login_attempts.json`: Intentos de login
- `logs/errors.log`: Errores del sistema

### Backup Automático
El sistema incluye funciones para backup de la base de datos.

## 📞 Soporte

Para reportar problemas o sugerir mejoras:
1. Revisar logs de errores
2. Verificar configuración de base de datos
3. Comprobar permisos de archivos
4. Consultar documentación de PHP/MySQL

## 📝 Notas de Desarrollo

- Usar librerías profesionales de PDF en producción (TCPDF, DomPDF)
- Implementar autenticación más robusta según necesidades
- Agregar módulo de administración para gestión completa
- Considerar cache de sesión para mejor rendimiento

## 🔐 Usuario de Prueba

Con el dump incluido:
- **Matrícula**: 1323141370
- **Datos**: Omar Esteban Muñoz Albarran
- **Estado**: Con algunos datos de cartas precargados