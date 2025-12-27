-- ============================================
-- SCHEMA PARA MÓDULO DE REPUESTOS
-- Sistema de Control de Fotocopiadoras
-- ============================================

-- Tabla de repuestos (catálogo general)
CREATE TABLE IF NOT EXISTS `repuestos` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `codigo` VARCHAR(50) NOT NULL UNIQUE,
    `nombre` VARCHAR(200) NOT NULL,
    `descripcion` TEXT,
    `marca` VARCHAR(100),
    `modelo_compatible` VARCHAR(200),
    `stock_minimo` INT(11) DEFAULT 0,
    `stock_actual` INT(11) DEFAULT 0,
    `precio_unitario` DECIMAL(10,2) DEFAULT 0.00,
    `unidad_medida` VARCHAR(50) DEFAULT 'Unidad',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `id_usuario_registro` INT(11),
    PRIMARY KEY (`id`),
    KEY `idx_codigo` (`codigo`),
    KEY `idx_activo` (`activo`),
    KEY `idx_usuario` (`id_usuario_registro`),
    CONSTRAINT `fk_repuesto_usuario` FOREIGN KEY (`id_usuario_registro`) 
        REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla intermedia: repuestos utilizados en mantenimientos
CREATE TABLE IF NOT EXISTS `mantenimientos_repuestos` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `id_mantenimiento` INT(11) NOT NULL,
    `id_repuesto` INT(11) NOT NULL,
    `cantidad` INT(11) NOT NULL DEFAULT 1,
    `fecha_cambio` DATE NOT NULL,
    `parte_requerida` VARCHAR(200) NOT NULL COMMENT 'Descripción de la parte específica reemplazada',
    `observaciones` TEXT,
    `costo_total` DECIMAL(10,2) DEFAULT 0.00,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `id_usuario_registro` INT(11),
    PRIMARY KEY (`id`),
    KEY `idx_mantenimiento` (`id_mantenimiento`),
    KEY `idx_repuesto` (`id_repuesto`),
    KEY `idx_fecha_cambio` (`fecha_cambio`),
    CONSTRAINT `fk_mant_repuesto_mantenimiento` FOREIGN KEY (`id_mantenimiento`) 
        REFERENCES `mantenimientos`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mant_repuesto_repuesto` FOREIGN KEY (`id_repuesto`) 
        REFERENCES `repuestos`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_mant_repuesto_usuario` FOREIGN KEY (`id_usuario_registro`) 
        REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de movimientos de inventario de repuestos
CREATE TABLE IF NOT EXISTS `repuestos_movimientos` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `id_repuesto` INT(11) NOT NULL,
    `tipo_movimiento` ENUM('entrada', 'salida', 'ajuste') NOT NULL,
    `cantidad` INT(11) NOT NULL,
    `stock_anterior` INT(11) NOT NULL,
    `stock_nuevo` INT(11) NOT NULL,
    `motivo` VARCHAR(255),
    `referencia` VARCHAR(100) COMMENT 'ID de mantenimiento, orden de compra, etc.',
    `fecha_movimiento` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `id_usuario_registro` INT(11),
    PRIMARY KEY (`id`),
    KEY `idx_repuesto` (`id_repuesto`),
    KEY `idx_tipo` (`tipo_movimiento`),
    KEY `idx_fecha` (`fecha_movimiento`),
    CONSTRAINT `fk_movimiento_repuesto` FOREIGN KEY (`id_repuesto`) 
        REFERENCES `repuestos`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_movimiento_usuario` FOREIGN KEY (`id_usuario_registro`) 
        REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos iniciales de ejemplo
INSERT INTO `repuestos` (`codigo`, `nombre`, `descripcion`, `marca`, `modelo_compatible`, `stock_minimo`, `stock_actual`, `precio_unitario`, `unidad_medida`) VALUES
('REP-001', 'Tóner Negro', 'Tóner negro estándar', 'HP', 'LaserJet Pro', 5, 15, 85.50, 'Unidad'),
('REP-002', 'Tóner Cian', 'Tóner cian estándar', 'HP', 'LaserJet Pro', 3, 8, 90.00, 'Unidad'),
('REP-003', 'Tóner Magenta', 'Tóner magenta estándar', 'HP', 'LaserJet Pro', 3, 7, 90.00, 'Unidad'),
('REP-004', 'Tóner Amarillo', 'Tóner amarillo estándar', 'HP', 'LaserJet Pro', 3, 6, 90.00, 'Unidad'),
('REP-005', 'Fusor', 'Kit de fusor completo', 'HP', 'LaserJet', 2, 4, 250.00, 'Unidad'),
('REP-006', 'Rodillo de Transferencia', 'Rodillo de transferencia', 'Canon', 'ImageRunner', 2, 5, 120.00, 'Unidad'),
('REP-007', 'Kit de Mantenimiento', 'Kit de mantenimiento completo', 'Xerox', 'WorkCentre', 1, 3, 350.00, 'Kit'),
('REP-008', 'Tambor Fotoconductor', 'Tambor para impresora láser', 'Brother', 'HL Series', 2, 4, 180.00, 'Unidad'),
('REP-009', 'Bandeja de Papel', 'Bandeja adicional 500 hojas', 'HP', 'Universal', 1, 2, 150.00, 'Unidad'),
('REP-010', 'Cable de Red', 'Cable Ethernet Cat6 3m', 'Genérico', 'Universal', 10, 25, 5.50, 'Unidad');
