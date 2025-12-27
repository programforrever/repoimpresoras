-- Corregir tipos de demanda con caracteres UTF-8 correctos
-- Sistema de Control de Fotocopiadoras

-- Primero, asegurarse de que la tabla use UTF-8
ALTER TABLE `tipos_demanda` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Actualizar los registros con los textos correctos
UPDATE `tipos_demanda` SET `nombre` = 'Mantenimiento Preventivo', `descripcion` = 'Mantenimiento programado regular' WHERE `id` = 1;
UPDATE `tipos_demanda` SET `nombre` = 'Mantenimiento Correctivo', `descripcion` = 'Reparación por falla' WHERE `id` = 2;
UPDATE `tipos_demanda` SET `nombre` = 'Cambio de Repuesto', `descripcion` = 'Reemplazo de piezas' WHERE `id` = 3;
UPDATE `tipos_demanda` SET `nombre` = 'Instalación', `descripcion` = 'Instalación inicial del equipo' WHERE `id` = 4;
UPDATE `tipos_demanda` SET `nombre` = 'Traslado', `descripcion` = 'Cambio de ubicación del equipo' WHERE `id` = 5;
UPDATE `tipos_demanda` SET `nombre` = 'Calibración', `descripcion` = 'Ajustes y calibración del equipo' WHERE `id` = 6;

-- Verificar los cambios
SELECT * FROM `tipos_demanda`;
