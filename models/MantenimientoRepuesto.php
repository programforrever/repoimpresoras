<?php
/**
 * Modelo de MantenimientoRepuesto
 * Gestiona la relación entre mantenimientos y repuestos
 */

class MantenimientoRepuesto {
    private $conn;
    private $table_name = "mantenimientos_repuestos";
    
    /**
     * Constructor
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener repuestos de un mantenimiento
     */
    public function getByMantenimiento($id_mantenimiento) {
        $query = "SELECT mr.*,
                        r.codigo,
                        r.nombre as nombre_repuesto,
                        r.marca,
                        r.modelo_compatible,
                        r.unidad_medida,
                        u.nombre_completo as usuario_registro
                 FROM " . $this->table_name . " mr
                 INNER JOIN repuestos r ON mr.id_repuesto = r.id
                 LEFT JOIN usuarios u ON mr.id_usuario_registro = u.id
                 WHERE mr.id_mantenimiento = :id_mantenimiento
                 ORDER BY mr.fecha_cambio DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mantenimiento', $id_mantenimiento);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener historial de uso de un repuesto
     */
    public function getByRepuesto($id_repuesto, $limite = null) {
        $query = "SELECT mr.*,
                        m.fecha_mantenimiento,
                        m.descripcion as descripcion_mantenimiento,
                        m.tecnico_responsable,
                        e.codigo_patrimonial as equipo,
                        e.marca as marca_equipo,
                        e.modelo as modelo_equipo,
                        e.numero_serie as serie,
                        u.nombre_completo as usuario_registro
                 FROM " . $this->table_name . " mr
                 INNER JOIN mantenimientos m ON mr.id_mantenimiento = m.id
                 INNER JOIN equipos e ON m.id_equipo = e.id
                 LEFT JOIN usuarios u ON mr.id_usuario_registro = u.id
                 WHERE mr.id_repuesto = :id_repuesto
                 ORDER BY mr.fecha_cambio DESC";
        
        if ($limite) {
            $query .= " LIMIT :limite";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_repuesto', $id_repuesto);
        
        if ($limite) {
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Agregar repuesto a un mantenimiento
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . "
                 (id_mantenimiento, id_repuesto, cantidad, fecha_cambio, 
                  parte_requerida, observaciones, costo_total, id_usuario_registro)
                 VALUES 
                 (:id_mantenimiento, :id_repuesto, :cantidad, :fecha_cambio,
                  :parte_requerida, :observaciones, :costo_total, :id_usuario_registro)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id_mantenimiento', $data['id_mantenimiento']);
        $stmt->bindParam(':id_repuesto', $data['id_repuesto']);
        $stmt->bindParam(':cantidad', $data['cantidad']);
        $stmt->bindParam(':fecha_cambio', $data['fecha_cambio']);
        $stmt->bindParam(':parte_requerida', $data['parte_requerida']);
        $stmt->bindParam(':observaciones', $data['observaciones']);
        $stmt->bindParam(':costo_total', $data['costo_total']);
        $stmt->bindParam(':id_usuario_registro', $data['id_usuario_registro']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Actualizar registro
     */
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . "
                 SET cantidad = :cantidad,
                     fecha_cambio = :fecha_cambio,
                     parte_requerida = :parte_requerida,
                     observaciones = :observaciones,
                     costo_total = :costo_total
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':cantidad', $data['cantidad']);
        $stmt->bindParam(':fecha_cambio', $data['fecha_cambio']);
        $stmt->bindParam(':parte_requerida', $data['parte_requerida']);
        $stmt->bindParam(':observaciones', $data['observaciones']);
        $stmt->bindParam(':costo_total', $data['costo_total']);
        
        return $stmt->execute();
    }
    
    /**
     * Eliminar registro
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    /**
     * Obtener por ID
     */
    public function getById($id) {
        $query = "SELECT mr.*,
                        r.codigo,
                        r.nombre as nombre_repuesto,
                        r.marca,
                        r.modelo_compatible,
                        r.unidad_medida,
                        r.precio_unitario,
                        u.nombre_completo as usuario_registro
                 FROM " . $this->table_name . " mr
                 INNER JOIN repuestos r ON mr.id_repuesto = r.id
                 LEFT JOIN usuarios u ON mr.id_usuario_registro = u.id
                 WHERE mr.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener estadísticas de uso de repuestos
     */
    public function getEstadisticas($fecha_desde = null, $fecha_hasta = null) {
        $query = "SELECT 
                    r.id,
                    r.codigo,
                    r.nombre,
                    r.marca,
                    COUNT(mr.id) as total_usos,
                    SUM(mr.cantidad) as cantidad_total,
                    SUM(mr.costo_total) as costo_total_acumulado,
                    MAX(mr.fecha_cambio) as ultima_utilizacion
                 FROM repuestos r
                 LEFT JOIN " . $this->table_name . " mr ON r.id = mr.id_repuesto";
        
        $conditions = ["r.activo = 1"];
        
        if ($fecha_desde) {
            $conditions[] = "mr.fecha_cambio >= :fecha_desde";
        }
        if ($fecha_hasta) {
            $conditions[] = "mr.fecha_cambio <= :fecha_hasta";
        }
        
        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " GROUP BY r.id, r.codigo, r.nombre, r.marca
                   ORDER BY total_usos DESC, cantidad_total DESC";
        
        $stmt = $this->conn->prepare($query);
        
        if ($fecha_desde) {
            $stmt->bindParam(':fecha_desde', $fecha_desde);
        }
        if ($fecha_hasta) {
            $stmt->bindParam(':fecha_hasta', $fecha_hasta);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener costo total de repuestos por mantenimiento
     */
    public function getCostoTotal($id_mantenimiento) {
        $query = "SELECT COALESCE(SUM(costo_total), 0) as costo_total
                 FROM " . $this->table_name . "
                 WHERE id_mantenimiento = :id_mantenimiento";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mantenimiento', $id_mantenimiento);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['costo_total'];
    }
}
?>
