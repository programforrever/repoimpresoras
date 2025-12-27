<?php
/**
 * Modelo de Mantenimiento
 */

class Mantenimiento {
    private $conn;
    private $table = 'mantenimientos';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obtener todos los mantenimientos
     */
    public function getAll($filters = [], $limit = null, $offset = 0) {
        // Verificar si existe la columna activo
        $hasActivoColumn = $this->hasActivoColumn();
        
        $sql = "SELECT m.*, 
                e.codigo_patrimonial, e.marca, e.modelo,
                td.nombre as tipo_demanda,
                ea.nombre as estado_anterior,
                en.nombre as estado_nuevo,
                u.nombre_completo as registrado_por,
                (SELECT COUNT(*) FROM mantenimientos_repuestos WHERE id_mantenimiento = m.id) as cantidad_repuestos
                FROM " . $this->table . " m
                INNER JOIN equipos e ON m.id_equipo = e.id
                LEFT JOIN tipos_demanda td ON m.id_tipo_demanda = td.id
                LEFT JOIN estados_equipo ea ON m.id_estado_anterior = ea.id
                LEFT JOIN estados_equipo en ON m.id_estado_nuevo = en.id
                LEFT JOIN usuarios u ON m.id_usuario_registro = u.id
                WHERE 1=1";
        
        // Solo agregar filtro de activo si la columna existe
        if ($hasActivoColumn) {
            $sql .= " AND m.activo = 1";
        }
        
        // Aplicar filtros
        if (!empty($filters['id_equipo'])) {
            $sql .= " AND m.id_equipo = :id_equipo";
        }
        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND m.fecha_mantenimiento >= :fecha_desde";
        }
        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND m.fecha_mantenimiento <= :fecha_hasta";
        }
        if (!empty($filters['id_tipo_demanda'])) {
            $sql .= " AND m.id_tipo_demanda = :id_tipo_demanda";
        }
        
        $sql .= " ORDER BY m.fecha_mantenimiento DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        // Bind filtros
        if (!empty($filters['id_equipo'])) {
            $stmt->bindParam(':id_equipo', $filters['id_equipo']);
        }
        if (!empty($filters['fecha_desde'])) {
            $stmt->bindParam(':fecha_desde', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $stmt->bindParam(':fecha_hasta', $filters['fecha_hasta']);
        }
        if (!empty($filters['id_tipo_demanda'])) {
            $stmt->bindParam(':id_tipo_demanda', $filters['id_tipo_demanda']);
        }
        
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verificar si existe la columna activo
     */
    private function hasActivoColumn() {
        try {
            $sql = "SHOW COLUMNS FROM " . $this->table . " LIKE 'activo'";
            $stmt = $this->conn->query($sql);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Contar mantenimientos
     */
    public function count($filters = []) {
        // Verificar si existe la columna activo
        $hasActivoColumn = $this->hasActivoColumn();
        
        $sql = "SELECT COUNT(*) FROM " . $this->table . " m WHERE 1=1";
        
        // Solo agregar filtro de activo si la columna existe
        if ($hasActivoColumn) {
            $sql .= " AND m.activo = 1";
        }
        
        if (!empty($filters['id_equipo'])) {
            $sql .= " AND m.id_equipo = :id_equipo";
        }
        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND m.fecha_mantenimiento >= :fecha_desde";
        }
        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND m.fecha_mantenimiento <= :fecha_hasta";
        }
        if (!empty($filters['id_tipo_demanda'])) {
            $sql .= " AND m.id_tipo_demanda = :id_tipo_demanda";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($filters['id_equipo'])) {
            $stmt->bindParam(':id_equipo', $filters['id_equipo']);
        }
        if (!empty($filters['fecha_desde'])) {
            $stmt->bindParam(':fecha_desde', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $stmt->bindParam(':fecha_hasta', $filters['fecha_hasta']);
        }
        if (!empty($filters['id_tipo_demanda'])) {
            $stmt->bindParam(':id_tipo_demanda', $filters['id_tipo_demanda']);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    /**
     * Obtener mantenimiento por ID
     */
    public function getById($id) {
        $sql = "SELECT m.*, 
                e.codigo_patrimonial, 
                e.marca, 
                e.modelo,
                td.nombre as tipo_demanda,
                ea.nombre as estado_anterior,
                en.nombre as estado_nuevo,
                u.nombre_completo as usuario_registro,
                m.fecha_creacion as fecha_registro
                FROM " . $this->table . " m
                INNER JOIN equipos e ON m.id_equipo = e.id
                LEFT JOIN tipos_demanda td ON m.id_tipo_demanda = td.id
                LEFT JOIN estados_equipo ea ON m.id_estado_anterior = ea.id
                LEFT JOIN estados_equipo en ON m.id_estado_nuevo = en.id
                LEFT JOIN usuarios u ON m.id_usuario_registro = u.id
                WHERE m.id = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crear mantenimiento
     */
    public function create($data) {
        // Obtener estado actual del equipo
        $sql_estado = "SELECT id_estado FROM equipos WHERE id = :id_equipo";
        $stmt_estado = $this->conn->prepare($sql_estado);
        $stmt_estado->bindParam(':id_equipo', $data['id_equipo']);
        $stmt_estado->execute();
        $estado_actual = $stmt_estado->fetchColumn();
        
        $sql = "INSERT INTO " . $this->table . " 
                (id_equipo, id_tipo_demanda, fecha_mantenimiento, descripcion, 
                 tecnico_responsable, observaciones, id_estado_anterior, id_estado_nuevo, id_usuario_registro) 
                VALUES 
                (:id_equipo, :id_tipo_demanda, :fecha_mantenimiento, :descripcion, 
                 :tecnico_responsable, :observaciones, :id_estado_anterior, :id_estado_nuevo, :id_usuario_registro)";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':id_equipo', $data['id_equipo']);
        $stmt->bindParam(':id_tipo_demanda', $data['id_tipo_demanda']);
        $stmt->bindParam(':fecha_mantenimiento', $data['fecha_mantenimiento']);
        $stmt->bindParam(':descripcion', $data['descripcion']);
        $stmt->bindParam(':tecnico_responsable', $data['tecnico_responsable']);
        $stmt->bindParam(':observaciones', $data['observaciones']);
        $stmt->bindParam(':id_estado_anterior', $estado_actual);
        $stmt->bindParam(':id_estado_nuevo', $data['id_estado_nuevo']);
        $stmt->bindParam(':id_usuario_registro', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }

    /**
     * Actualizar mantenimiento
     */
    public function update($id, $data) {
        $sql = "UPDATE " . $this->table . " SET 
                id_tipo_demanda = :id_tipo_demanda,
                fecha_mantenimiento = :fecha_mantenimiento,
                descripcion = :descripcion,
                tecnico_responsable = :tecnico_responsable,
                observaciones = :observaciones,
                id_estado_nuevo = :id_estado_nuevo
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':id_tipo_demanda', $data['id_tipo_demanda']);
        $stmt->bindParam(':fecha_mantenimiento', $data['fecha_mantenimiento']);
        $stmt->bindParam(':descripcion', $data['descripcion']);
        $stmt->bindParam(':tecnico_responsable', $data['tecnico_responsable']);
        $stmt->bindParam(':observaciones', $data['observaciones']);
        $stmt->bindParam(':id_estado_nuevo', $data['id_estado_nuevo']);
        
        return $stmt->execute();
    }

    /**
     * Obtener tipos de demanda
     */
    public function getTiposDemanda() {
        $sql = "SELECT * FROM tipos_demanda WHERE activo = 1 ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener repuestos de un mantenimiento
     */
    public function getRepuestos($id_mantenimiento) {
        $sql = "SELECT r.*, u.nombre_completo as registrado_por
                FROM repuestos r
                LEFT JOIN usuarios u ON r.id_usuario_registro = u.id
                WHERE r.id_mantenimiento = :id_mantenimiento
                ORDER BY r.fecha_cambio DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_mantenimiento', $id_mantenimiento);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agregar repuesto a un mantenimiento
     */
    public function addRepuesto($data) {
        $sql = "INSERT INTO repuestos 
                (id_mantenimiento, parte_requerida, descripcion, cantidad, fecha_cambio, costo, proveedor, id_usuario_registro) 
                VALUES 
                (:id_mantenimiento, :parte_requerida, :descripcion, :cantidad, :fecha_cambio, :costo, :proveedor, :id_usuario_registro)";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':id_mantenimiento', $data['id_mantenimiento']);
        $stmt->bindParam(':parte_requerida', $data['parte_requerida']);
        $stmt->bindParam(':descripcion', $data['descripcion']);
        $stmt->bindParam(':cantidad', $data['cantidad']);
        $stmt->bindParam(':fecha_cambio', $data['fecha_cambio']);
        $stmt->bindParam(':costo', $data['costo']);
        $stmt->bindParam(':proveedor', $data['proveedor']);
        $stmt->bindParam(':id_usuario_registro', $_SESSION['user_id']);
        
        return $stmt->execute();
    }

    /**
     * Eliminar repuesto
     */
    public function deleteRepuesto($id) {
        $sql = "DELETE FROM repuestos WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
