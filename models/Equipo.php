<?php
/**
 * Modelo de Equipo
 */

class Equipo {
    private $conn;
    private $table = 'equipos';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obtener todos los equipos con filtros
     */
    public function getAll($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT e.*, 
                est.nombre as estado,
                est.color as estado_color,
                m.nombre as marca,
                mo.nombre as modelo,
                d.nombre as distrito_nombre,
                s.nombre as sede_nombre,
                mp.nombre as macro_proceso_nombre,
                desp.nombre as despacho_nombre,
                uf.nombre_completo as usuario_final_nombre
                FROM " . $this->table . " e
                LEFT JOIN estados_equipo est ON e.id_estado = est.id
                LEFT JOIN marcas m ON e.id_marca = m.id
                LEFT JOIN modelos mo ON e.id_modelo = mo.id
                LEFT JOIN distritos_fiscales d ON e.id_distrito = d.id
                LEFT JOIN sedes s ON e.id_sede = s.id
                LEFT JOIN macro_procesos mp ON e.id_macro_proceso = mp.id
                LEFT JOIN despachos desp ON e.id_despacho = desp.id
                LEFT JOIN usuarios_finales uf ON e.id_usuario_final = uf.id
                WHERE e.activo = 1";
        
        // Aplicar filtros
        if (!empty($filters['codigo_patrimonial'])) {
            $sql .= " AND e.codigo_patrimonial LIKE :codigo_patrimonial";
        }
        if (!empty($filters['marca'])) {
            $sql .= " AND e.marca LIKE :marca";
        }
        if (!empty($filters['modelo'])) {
            $sql .= " AND e.modelo LIKE :modelo";
        }
        if (!empty($filters['clasificacion'])) {
            $sql .= " AND e.clasificacion = :clasificacion";
        }
        if (!empty($filters['id_estado'])) {
            $sql .= " AND e.id_estado = :id_estado";
        }
        if (!empty($filters['id_sede'])) {
            $sql .= " AND e.id_sede = :id_sede";
        }
        if (!empty($filters['id_distrito'])) {
            $sql .= " AND e.id_distrito = :id_distrito";
        }
        
        $sql .= " ORDER BY e.fecha_creacion DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        // Bind filtros
        if (!empty($filters['codigo_patrimonial'])) {
            $param = "%{$filters['codigo_patrimonial']}%";
            $stmt->bindParam(':codigo_patrimonial', $param);
        }
        if (!empty($filters['marca'])) {
            $param = "%{$filters['marca']}%";
            $stmt->bindParam(':marca', $param);
        }
        if (!empty($filters['modelo'])) {
            $param = "%{$filters['modelo']}%";
            $stmt->bindParam(':modelo', $param);
        }
        if (!empty($filters['clasificacion'])) {
            $stmt->bindParam(':clasificacion', $filters['clasificacion']);
        }
        if (!empty($filters['id_estado'])) {
            $stmt->bindParam(':id_estado', $filters['id_estado']);
        }
        if (!empty($filters['id_sede'])) {
            $stmt->bindParam(':id_sede', $filters['id_sede']);
        }
        if (!empty($filters['id_distrito'])) {
            $stmt->bindParam(':id_distrito', $filters['id_distrito']);
        }
        
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar equipos con filtros
     */
    public function count($filters = []) {
        $sql = "SELECT COUNT(*) FROM " . $this->table . " e WHERE e.activo = 1";
        
        if (!empty($filters['codigo_patrimonial'])) {
            $sql .= " AND e.codigo_patrimonial LIKE :codigo_patrimonial";
        }
        if (!empty($filters['marca'])) {
            $sql .= " AND e.marca LIKE :marca";
        }
        if (!empty($filters['modelo'])) {
            $sql .= " AND e.modelo LIKE :modelo";
        }
        if (!empty($filters['clasificacion'])) {
            $sql .= " AND e.clasificacion = :clasificacion";
        }
        if (!empty($filters['id_estado'])) {
            $sql .= " AND e.id_estado = :id_estado";
        }
        if (!empty($filters['id_sede'])) {
            $sql .= " AND e.id_sede = :id_sede";
        }
        if (!empty($filters['id_distrito'])) {
            $sql .= " AND e.id_distrito = :id_distrito";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($filters['codigo_patrimonial'])) {
            $param = "%{$filters['codigo_patrimonial']}%";
            $stmt->bindParam(':codigo_patrimonial', $param);
        }
        if (!empty($filters['marca'])) {
            $param = "%{$filters['marca']}%";
            $stmt->bindParam(':marca', $param);
        }
        if (!empty($filters['modelo'])) {
            $param = "%{$filters['modelo']}%";
            $stmt->bindParam(':modelo', $param);
        }
        if (!empty($filters['clasificacion'])) {
            $stmt->bindParam(':clasificacion', $filters['clasificacion']);
        }
        if (!empty($filters['id_estado'])) {
            $stmt->bindParam(':id_estado', $filters['id_estado']);
        }
        if (!empty($filters['id_sede'])) {
            $stmt->bindParam(':id_sede', $filters['id_sede']);
        }
        if (!empty($filters['id_distrito'])) {
            $stmt->bindParam(':id_distrito', $filters['id_distrito']);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    /**
     * Obtener equipo por ID
     */
    public function getById($id) {
        $sql = "SELECT e.*, 
                est.nombre as estado_nombre,
                d.nombre as distrito_nombre,
                s.nombre as sede_nombre,
                mp.nombre as macro_proceso_nombre,
                desp.nombre as despacho_nombre,
                uf.nombre_completo as usuario_final_nombre
                FROM " . $this->table . " e
                LEFT JOIN estados_equipo est ON e.id_estado = est.id
                LEFT JOIN distritos_fiscales d ON e.id_distrito = d.id
                LEFT JOIN sedes s ON e.id_sede = s.id
                LEFT JOIN macro_procesos mp ON e.id_macro_proceso = mp.id
                LEFT JOIN despachos desp ON e.id_despacho = desp.id
                LEFT JOIN usuarios_finales uf ON e.id_usuario_final = uf.id
                WHERE e.id = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crear equipo
     */
    public function create($data) {
        $sql = "INSERT INTO " . $this->table . " 
                (codigo_patrimonial, clasificacion, marca, modelo, id_marca, id_modelo, 
                 numero_serie, garantia, id_estado, tiene_estabilizador, anio_adquisicion, 
                 id_distrito, id_sede, id_macro_proceso, ubicacion_fisica, id_despacho, 
                 id_usuario_final, observaciones, imagen, id_usuario_creacion) 
                VALUES 
                (:codigo_patrimonial, :clasificacion, :marca, :modelo, :id_marca, :id_modelo, 
                 :numero_serie, :garantia, :id_estado, :tiene_estabilizador, :anio_adquisicion, 
                 :id_distrito, :id_sede, :id_macro_proceso, :ubicacion_fisica, :id_despacho, 
                 :id_usuario_final, :observaciones, :imagen, :id_usuario_creacion)";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':codigo_patrimonial', $data['codigo_patrimonial']);
        $stmt->bindParam(':clasificacion', $data['clasificacion']);
        $stmt->bindParam(':marca', $data['marca']);
        $stmt->bindParam(':modelo', $data['modelo']);
        $id_marca = $data['id_marca'] ?? null;
        $id_modelo = $data['id_modelo'] ?? null;
        $stmt->bindParam(':id_marca', $id_marca);
        $stmt->bindParam(':id_modelo', $id_modelo);
        $stmt->bindParam(':numero_serie', $data['numero_serie']);
        $stmt->bindParam(':garantia', $data['garantia']);
        $stmt->bindParam(':id_estado', $data['id_estado']);
        $stmt->bindParam(':tiene_estabilizador', $data['tiene_estabilizador']);
        $stmt->bindParam(':anio_adquisicion', $data['anio_adquisicion']);
        $stmt->bindParam(':id_distrito', $data['id_distrito']);
        $stmt->bindParam(':id_sede', $data['id_sede']);
        $stmt->bindParam(':id_macro_proceso', $data['id_macro_proceso']);
        $stmt->bindParam(':ubicacion_fisica', $data['ubicacion_fisica']);
        $stmt->bindParam(':id_despacho', $data['id_despacho']);
        $stmt->bindParam(':id_usuario_final', $data['id_usuario_final']);
        $stmt->bindParam(':observaciones', $data['observaciones']);
        $imagen = $data['imagen'] ?? null;
        $stmt->bindParam(':imagen', $imagen);
        $stmt->bindParam(':id_usuario_creacion', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }

    /**
     * Actualizar equipo
     */
    public function update($id, $data) {
        // Construir SQL dinámicamente según si hay imagen
        $sql = "UPDATE " . $this->table . " SET 
                codigo_patrimonial = :codigo_patrimonial,
                clasificacion = :clasificacion,
                marca = :marca,
                modelo = :modelo,
                id_marca = :id_marca,
                id_modelo = :id_modelo,
                numero_serie = :numero_serie,
                garantia = :garantia,
                id_estado = :id_estado,
                tiene_estabilizador = :tiene_estabilizador,
                anio_adquisicion = :anio_adquisicion,
                id_distrito = :id_distrito,
                id_sede = :id_sede,
                id_macro_proceso = :id_macro_proceso,
                ubicacion_fisica = :ubicacion_fisica,
                id_despacho = :id_despacho,
                id_usuario_final = :id_usuario_final,
                observaciones = :observaciones,
                id_usuario_actualizacion = :id_usuario_actualizacion";
        
        // Agregar imagen si está presente
        if (isset($data['imagen'])) {
            $sql .= ", imagen = :imagen";
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':codigo_patrimonial', $data['codigo_patrimonial']);
        $stmt->bindParam(':clasificacion', $data['clasificacion']);
        $stmt->bindParam(':marca', $data['marca']);
        $stmt->bindParam(':modelo', $data['modelo']);
        $id_marca = $data['id_marca'] ?? null;
        $id_modelo = $data['id_modelo'] ?? null;
        $stmt->bindParam(':id_marca', $id_marca);
        $stmt->bindParam(':id_modelo', $id_modelo);
        $stmt->bindParam(':numero_serie', $data['numero_serie']);
        $stmt->bindParam(':garantia', $data['garantia']);
        $stmt->bindParam(':id_estado', $data['id_estado']);
        $stmt->bindParam(':tiene_estabilizador', $data['tiene_estabilizador']);
        $stmt->bindParam(':anio_adquisicion', $data['anio_adquisicion']);
        $stmt->bindParam(':id_distrito', $data['id_distrito']);
        $stmt->bindParam(':id_sede', $data['id_sede']);
        $stmt->bindParam(':id_macro_proceso', $data['id_macro_proceso']);
        $stmt->bindParam(':ubicacion_fisica', $data['ubicacion_fisica']);
        $stmt->bindParam(':id_despacho', $data['id_despacho']);
        $stmt->bindParam(':id_usuario_final', $data['id_usuario_final']);
        $stmt->bindParam(':observaciones', $data['observaciones']);
        $stmt->bindParam(':id_usuario_actualizacion', $_SESSION['user_id']);
        
        if (isset($data['imagen'])) {
            $stmt->bindParam(':imagen', $data['imagen']);
        }
        
        return $stmt->execute();
    }

    /**
     * Eliminar equipo (soft delete)
     */
    public function delete($id) {
        $sql = "UPDATE " . $this->table . " SET activo = 0, id_usuario_actualizacion = :id_usuario WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':id_usuario', $_SESSION['user_id']);
        return $stmt->execute();
    }

    /**
     * Cambiar estado del equipo
     */
    public function cambiarEstado($id, $id_estado) {
        $sql = "UPDATE " . $this->table . " SET id_estado = :id_estado WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':id_estado', $id_estado);
        return $stmt->execute();
    }

    /**
     * Verificar si existe código patrimonial
     */
    public function codigoExists($codigo, $exclude_id = null) {
        $sql = "SELECT COUNT(*) FROM " . $this->table . " WHERE codigo_patrimonial = :codigo AND activo = 1";
        
        if ($exclude_id) {
            $sql .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':codigo', $codigo);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Obtener estados de equipo
     */
    public function getEstados() {
        $sql = "SELECT * FROM estados_equipo WHERE activo = 1 ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener marcas
     */
    public function getMarcas() {
        $sql = "SELECT * FROM marcas WHERE activo = 1 ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener modelos
     */
    public function getModelos($id_marca = null) {
        $sql = "SELECT m.*, marc.nombre as marca_nombre 
                FROM modelos m 
                LEFT JOIN marcas marc ON m.id_marca = marc.id 
                WHERE m.activo = 1";
        if ($id_marca) {
            $sql .= " AND m.id_marca = :id_marca";
        }
        $sql .= " ORDER BY marc.nombre, m.nombre";
        
        $stmt = $this->conn->prepare($sql);
        if ($id_marca) {
            $stmt->bindParam(':id_marca', $id_marca);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener distritos fiscales
     */
    public function getDistritos() {
        $sql = "SELECT * FROM distritos_fiscales WHERE activo = 1 ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener sedes
     */
    public function getSedes($id_distrito = null) {
        $sql = "SELECT * FROM sedes WHERE activo = 1";
        if ($id_distrito) {
            $sql .= " AND id_distrito = :id_distrito";
        }
        $sql .= " ORDER BY nombre";
        
        $stmt = $this->conn->prepare($sql);
        if ($id_distrito) {
            $stmt->bindParam(':id_distrito', $id_distrito);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener macro procesos
     */
    public function getMacroProcesos() {
        $sql = "SELECT * FROM macro_procesos WHERE activo = 1 ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener despachos
     */
    public function getDespachos($id_sede = null) {
        $sql = "SELECT * FROM despachos WHERE activo = 1";
        if ($id_sede) {
            $sql .= " AND id_sede = :id_sede";
        }
        $sql .= " ORDER BY nombre";
        
        $stmt = $this->conn->prepare($sql);
        if ($id_sede) {
            $stmt->bindParam(':id_sede', $id_sede);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener usuarios finales
     */
    public function getUsuariosFinales() {
        $sql = "SELECT * FROM usuarios_finales WHERE activo = 1 ORDER BY nombre_completo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener historial de mantenimientos de un equipo
     */
    public function getHistorialMantenimientos($id_equipo) {
        $sql = "SELECT m.*, td.nombre as tipo_demanda, 
                u.nombre_completo as registrado_por,
                ea.nombre as estado_anterior,
                en.nombre as estado_nuevo
                FROM mantenimientos m
                LEFT JOIN tipos_demanda td ON m.id_tipo_demanda = td.id
                LEFT JOIN usuarios u ON m.id_usuario_registro = u.id
                LEFT JOIN estados_equipo ea ON m.id_estado_anterior = ea.id
                LEFT JOIN estados_equipo en ON m.id_estado_nuevo = en.id
                WHERE m.id_equipo = :id_equipo
                ORDER BY m.fecha_mantenimiento DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_equipo', $id_equipo);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener estadísticas de equipos
     */
    public function getEstadisticas() {
        $stats = [];
        
        // Total de equipos
        $sql = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE activo = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Equipos por estado
        $sql = "SELECT e.nombre as estado, COUNT(eq.id) as cantidad, e.color
                FROM estados_equipo e
                LEFT JOIN equipos eq ON e.id = eq.id_estado AND eq.activo = 1
                GROUP BY e.id
                ORDER BY e.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $stats['por_estado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Equipos por clasificación
        $sql = "SELECT clasificacion, COUNT(*) as cantidad 
                FROM " . $this->table . " 
                WHERE activo = 1 
                GROUP BY clasificacion";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $stats['por_clasificacion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * Obtener historial de auditoría de un equipo
     */
    public function getAuditoria($id_equipo) {
        $sql = "SELECT a.*, 
                       u.nombre_completo as usuario_nombre_completo
                FROM auditoria_equipos a
                LEFT JOIN usuarios u ON a.id_usuario = u.id
                WHERE a.id_equipo = :id_equipo
                ORDER BY a.fecha_hora DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id_equipo', $id_equipo);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
