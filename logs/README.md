# Logs Directory

Este directorio contiene los archivos de log del sistema.

## Archivos de Log

- `activity.log` - Registro de actividades del sistema
- `login_attempts.json` - Intentos de login para control de seguridad
- `errors.log` - Errores del sistema

## Permisos

Asegurar que este directorio tenga permisos de escritura para el servidor web:

```bash
chmod 755 logs/
```

## Mantenimiento

Se recomienda limpiar o rotar los logs periódicamente para evitar que crezcan demasiado.