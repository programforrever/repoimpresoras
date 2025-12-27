-- Agregar columna activo a la tabla mantenimientos
-- Fecha: 2025-12-26

USE sistema_impresoras;

-- Agregar columna activo si no existe
ALTER TABLE `mantenimientos` 
ADD COLUMN IF NOT EXISTS `activo` TINYINT(1) NOT NULL DEFAULT 1 
AFTER `id_usuario_registro`;

-- Actualizar todos los registros existentes a activo = 1
UPDATE `mantenimientos` SET `activo` = 1 WHERE `activo` IS NULL;

-- Agregar comentario a la columna
ALTER TABLE `mantenimientos` 
MODIFY COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Activo, 0=Eliminado';

SELECT 'Columna activo agregada exitosamente a la tabla mantenimientos' AS resultado;
