# 📊 Módulo de Importación Masiva

## Descripción

El módulo de **Importación Masiva** permite cargar grandes cantidades de datos desde archivos Excel (.xlsx, .xls) para:
- ✅ **Marcas** de equipos
- ✅ **Modelos** de equipos
- ✅ **Equipos** completos

## 🚀 Características

### 1. **Interfaz Drag & Drop**
- Arrastre archivos directamente a la zona de carga
- O haga clic para seleccionar desde su sistema
- Validación automática de formato y tamaño

### 2. **Plantillas Excel Dinámicas**
- Descargue plantillas pre-formateadas con ejemplos
- Incluyen todas las columnas necesarias
- Validación de datos requeridos

### 3. **Previsualización de Datos**
- Vea las primeras 10 filas antes de importar
- Verifique que los datos sean correctos
- Total de registros a procesar

### 4. **Procesamiento Inteligente**
- **Inserción** de nuevos registros
- **Actualización** automática de registros existentes
- **Reporte detallado** de resultados
- **Manejo de errores** por fila

### 5. **Auditoría Completa**
- Registro de todas las importaciones
- Usuario, fecha y hora
- Cantidad de registros procesados

## 📋 Formatos de Importación

### A. Marcas
| Columna | Requerido | Descripción | Ejemplo |
|---------|-----------|-------------|---------|
| Nombre Marca | ✅ Sí | Nombre de la marca | HP |
| Descripción | ❌ No | Descripción adicional | Hewlett-Packard |

**Comportamiento:**
- Si la marca ya existe → Se actualiza
- Si no existe → Se crea nueva

---

### B. Modelos
| Columna | Requerido | Descripción | Ejemplo |
|---------|-----------|-------------|---------|
| Marca | ✅ Sí | Nombre de marca existente | HP |
| Modelo | ✅ Sí | Nombre del modelo | LaserJet Pro M404dn |
| Descripción | ❌ No | Descripción del modelo | Impresora láser monocromática |

**Importante:**
- ⚠️ La marca debe existir previamente en el sistema
- Si no existe, se reportará como error
- Si el modelo ya existe para esa marca → Se actualiza
- Si no existe → Se crea nuevo

---

### C. Equipos
| Columna | Requerido | Descripción | Ejemplo |
|---------|-----------|-------------|---------|
| Código Patrimonial | ✅ Sí | Identificador único | IMP-001 |
| Número de Serie | ❌ No | Serie del fabricante | SN123456789 |
| Marca | ❌ No | Nombre de marca existente | HP |
| Modelo | ❌ No | Nombre de modelo existente | LaserJet Pro M404dn |
| Clasificación | ❌ No | impresora o multifuncional | impresora |
| Ubicación Física | ❌ No | Ubicación física del equipo | Oficina Principal - Piso 3 |
| Observaciones | ❌ No | Notas adicionales | En buenas condiciones |
| Año Adquisición | ❌ No | Año (YYYY) | 2024 |
| Estado | ❌ No | Operativo, En Mantenimiento, etc. | Operativo |

**Comportamiento:**
- Si el código patrimonial ya existe → Se actualiza el equipo
- Si no existe → Se crea nuevo
- Marca y modelo se buscan automáticamente por nombre
- Si no se encuentran, se guardan como texto (no genera error)
- Clasificación por defecto: "impresora"
- Estado por defecto: "Operativo"

**Formatos aceptados:**
- Año: Solo el número (2024)
- Clasificación: "impresora" o "multifuncional" (case insensitive)

---

## 🎯 Flujo de Trabajo Recomendado

### 1️⃣ Importar Marcas Primero
```
1. Acceder a: Importación Masiva
2. Seleccionar tipo: Marcas
3. Descargar plantilla_marcas.xlsx
4. Completar datos de marcas
5. Subir archivo y procesar
```

### 2️⃣ Importar Modelos Segundo
```
1. Seleccionar tipo: Modelos
2. Descargar plantilla_modelos.xlsx
3. Completar con marcas YA existentes
4. Subir archivo y procesar
```

### 3️⃣ Importar Equipos Tercero
```
1. Seleccionar tipo: Equipos
2. Descargar plantilla_equipos.xlsx
3. Completar todos los datos
4. Usar marcas y modelos existentes
5. Subir archivo y procesar
```

## ⚙️ Requisitos Técnicos

### Extensiones PHP Necesarias
- ✅ `ext-gd` - Procesamiento de imágenes
- ✅ `ext-zip` - Descompresión de archivos Excel
- ✅ `ext-xml` - Lectura de XML interno
- ✅ `ext-xmlreader` - Lectura eficiente

### Dependencias Composer
```json
{
    "phpoffice/phpspreadsheet": "^1.29"
}
```

### Límites
- **Tamaño máximo:** 5 MB por archivo
- **Formatos:** .xlsx, .xls
- **Filas recomendadas:** Hasta 1000 por importación
- **Timeout:** 60 segundos

## 🛡️ Seguridad

### Validaciones Implementadas
1. ✅ Autenticación requerida
2. ✅ Validación de extensión de archivo
3. ✅ Validación de tamaño
4. ✅ Sanitización de nombres de archivo
5. ✅ Archivos temporales únicos (uniqid)
6. ✅ Limpieza automática después de procesar
7. ✅ Transacciones de base de datos con rollback

### Permisos de Directorio
```bash
uploads/temp/ → 0777 (se crea automáticamente)
```

## 📊 Resultados de Importación

Después de procesar, se muestra un reporte con:

```
✅ Registros insertados: 15
🔄 Registros actualizados: 3
📊 Total procesado: 18
⚠️ Errores encontrados: 2

Lista de errores (si los hay):
- Fila 5: Marca no existe. Créela primero.
- Fila 12: Fecha inválida. Use formato DD/MM/YYYY
```

## 🔧 Mantenimiento

### Limpiar Archivos Temporales Manualmente
```bash
cd c:\xampp\htdocs\impresoras\uploads\temp
del *.*
```

### Ver Logs de Auditoría
Acceder a: **Auditoría** → Filtrar por "Importación masiva"

## 📝 Ejemplos de Uso

### Ejemplo 1: Importar 50 Marcas
```excel
| Nombre Marca | Descripción              |
|--------------|--------------------------|
| HP           | Hewlett-Packard          |
| Epson        | Impresoras Epson         |
| Canon        | Impresoras Canon         |
| Brother      | Brother Industries       |
| ...          | ...                      |
```

### Ejemplo 2: Importar Modelos
```excel
| Marca  | Modelo            | Descripción                    |
|--------|-------------------|--------------------------------|
| HP     | LaserJet Pro M404 | Impresora láser monocromática |
| Epson  | EcoTank L3250     | Multifuncional tanque tinta   |
| Canon  | PIXMA G3160       | Multifuncional WiFi           |
```

### Ejemplo 3: Importar Equipos Completos
```excel
| Código | Serie       | Marca | Modelo         | Clasificación  | Ubicación           | Observaciones       | Año  | Estado    |
|--------|-------------|-------|----------------|----------------|---------------------|---------------------|------|-----------|
| IMP-01 | SN12345     | HP    | LaserJet M404  | impresora      | Piso 1 - Oficina 3  | Buen estado        | 2024 | Operativo |
| IMP-02 | SN67890     | Epson | EcoTank L3250  | multifuncional | Piso 2 - Sala 5     | Requiere revisión  | 2023 | Operativo |
```

## 🐛 Solución de Problemas

### Error: "Extensión gd no disponible"
```bash
# Editar php.ini
extension=gd

# Reiniciar Apache
net stop Apache2.4
net start Apache2.4
```

### Error: "Marca no existe"
- **Solución:** Importar primero las marcas antes de los modelos/equipos

### Error: "Archivo demasiado grande"
- **Solución:** Dividir el archivo en partes de 1000 filas máximo

### Error: "Formato de fecha inválido"
- **Solución:** Usar DD/MM/YYYY o YYYY-MM-DD
- Ejemplo correcto: `15/01/2024`
- Ejemplo incorrecto: `15-01-2024`

## 📞 Soporte

Para más información o reportar problemas:
- Contactar al administrador del sistema
- Revisar logs en: `/uploads/temp/`
- Revisar auditoría en el módulo correspondiente

---

**Versión:** 1.0.0  
**Fecha:** Diciembre 2025  
**Autor:** Sistema de Gestión de Impresoras
