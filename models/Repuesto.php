<?php
/**
 * Modelo de Repuesto
 * Gestiona las operaciones CRUD de repuestos
 */

class Repuesto {
    private $conn;
    private $table_name = "repuestos";
    
    // Propiedades
    public $id;
    public $codigo;
    public $nombre;
    public $descripcion;
    public $marca;
    public $modelo_compatible;
    public $stock_minimo;
    public $stock_actual;
    public $precio_unitario;
    public $unidad_medida;
    public $activo;
    public $fecha_registro;
    public $id_usuario_registro;
    
    /**
     * Constructor
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todos los repuestos activos
     */
    public function getAll($incluir_inactivos = false) {
        $query = "SELECT r.*,
                        u.nombre_completo as usuario_registro,
                        CASE 
                            WHEN r.stock_actual <= 0 THEN 'Sin Stock'
                            WHEN r.stock_actual <= r.stock_minimo THEN 'Stock Bajo'
                            ELSE 'Stock Normal'
                        END as estado_stock
                 FROM " . $this->table_name . " r
                 LEFT JOIN usuarios u ON r.id_usuario_registro = u.id";
        
        if (!$incluir_inactivos) {
            $query .= " WHERE r.activo = 1";
        }
        
        $query .= " ORDER BY r.nombre ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener repuesto por ID
     */
    public function getById($id) {
        $query = "SELECT r.*,
                        u.nombre_completo as usuario_registro,
                        CASE 
                            WHEN r.stock_actual <= 0 THEN 'Sin Stock'
                            WHEN r.stock_actual <= r.stock_minimo THEN 'Stock Bajo'
                            ELSE 'Stock Normal'
                        END as estado_stock
                 FROM " . $this->table_name . " r
                 LEFT JOIN usuarios u ON r.id_usuario_registro = u.id
                 WHERE r.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener repuesto por código
     */
    public function getByCodigo($codigo) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE codigo = :codigo";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nuevo repuesto
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . "
                 (codigo, nombre, descripcion, marca, modelo_compatible, 
                  stock_minimo, stock_actual, precio_unitario, unidad_medida, 
                  id_usuario_registro)
                 VALUES 
                 (:codigo, :nombre, :descripcion, :marca, :modelo_compatible, 
                  :stock_minimo, :stock_actual, :precio_unitario, :unidad_medida,
                  :id_usuario_registro)";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $data['codigo'] = htmlspecialchars(strip_tags($data['codigo']));
        $data['nombre'] = htmlspecialchars(strip_tags($data['nombre']));
        
        // Bind
        $stmt->bindParam(':codigo', $data['codigo']);
        $stmt->bindParam(':nombre', $data['nombre']);
        $stmt->bindParam(':descripcion', $data['descripcion']);
        $stmt->bindParam(':marca', $data['marca']);
        $stmt->bindParam(':modelo_compatible', $data['modelo_compatible']);
        $stmt->bindParam(':stock_minimo', $data['stock_minimo']);
        $stmt->bindParam(':stock_actual', $data['stock_actual']);
        $stmt->bindParam(':precio_unitario', $data['precio_unitario']);
        $stmt->bindParam(':unidad_medida', $data['unidad_medida']);
        $stmt->bindParam(':id_usuario_registro', $data['id_usuario_registro']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Actualizar repuesto
     */
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . "
                 SET codigo = :codigo,
                     nombre = :nombre,
                     descripcion = :descripcion,
                     marca = :marca,
                     modelo_compatible = :modelo_compatible,
                     stock_minimo = :stock_minimo,
                     precio_unitario = :precio_unitario,
                     unidad_medida = :unidad_medida
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':codigo', $data['codigo']);
        $stmt->bindParam(':nombre', $data['nombre']);
        $stmt->bindParam(':descripcion', $data['descripcion']);
        $stmt->bindParam(':marca', $data['marca']);
        $stmt->bindParam(':modelo_compatible', $data['modelo_compatible']);
        $stmt->bindParam(':stock_minimo', $data['stock_minimo']);
        $stmt->bindParam(':precio_unitario', $data['precio_unitario']);
        $stmt->bindParam(':unidad_medida', $data['unidad_medida']);
        
        return $stmt->execute();
    }
    
    /**
     * Eliminar (soft delete)
     */
    public function delete($id) {
        $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    /**
     * Actualizar stock
     */
    public function actualizarStock($id, $cantidad, $tipo = 'entrada') {
        // Obtener stock actual
        $repuesto = $this->getById($id);
        if (!$repuesto) {
            return false;
        }
        
        $stock_anterior = $repuesto['stock_actual'];
        
        // Calcular nuevo stock
        if ($tipo === 'entrada' || $tipo === 'ajuste') {
            $stock_nuevo = $stock_anterior + $cantidad;
        } else { // salida
            $stock_nuevo = $stock_anterior - $cantidad;
        }
        
        // No permitir stock negativo
        if ($stock_nuevo < 0) {
            return false;
        }
        
        // Actualizar stock
        $query = "UPDATE " . $this->table_name . " 
                 SET stock_actual = :stock_nuevo 
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':stock_nuevo', $stock_nuevo);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return [
                'stock_anterior' => $stock_anterior,
                'stock_nuevo' => $stock_nuevo
            ];
        }
        
        return false;
    }
    
    /**
     * Obtener repuestos con stock bajo
     */
    public function getStockBajo() {
        $query = "SELECT r.*,
                        u.nombre_completo as usuario_registro
                 FROM " . $this->table_name . " r
                 LEFT JOIN usuarios u ON r.id_usuario_registro = u.id
                 WHERE r.activo = 1 
                 AND r.stock_actual <= r.stock_minimo
                 ORDER BY r.stock_actual ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Contar repuestos
     */
    public function count($incluir_inactivos = false) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        if (!$incluir_inactivos) {
            $query .= " WHERE activo = 1";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    /**
     * Buscar repuestos
     */
    public function buscar($termino) {
        $termino = "%{$termino}%";
        
        $query = "SELECT r.*,
                        u.nombre_completo as usuario_registro,
                        CASE 
                            WHEN r.stock_actual <= 0 THEN 'Sin Stock'
                            WHEN r.stock_actual <= r.stock_minimo THEN 'Stock Bajo'
                            ELSE 'Stock Normal'
                        END as estado_stock
                 FROM " . $this->table_name . " r
                 LEFT JOIN usuarios u ON r.id_usuario_registro = u.id
                 WHERE r.activo = 1
                 AND (r.codigo LIKE :termino 
                      OR r.nombre LIKE :termino 
                      OR r.marca LIKE :termino
                      OR r.modelo_compatible LIKE :termino)
                 ORDER BY r.nombre ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':termino', $termino);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
