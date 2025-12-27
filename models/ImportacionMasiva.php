<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportacionMasiva {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Lee archivo Excel y devuelve los datos como array
     */
    public function leerExcel($filePath) {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);
            
            // Remover la fila de encabezados
            $headers = array_shift($data);
            
            return [
                'success' => true,
                'headers' => $headers,
                'data' => $data,
                'total' => count($data)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al leer archivo: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Importa marcas desde array de datos
     */
    public function importarMarcas($datos) {
        $this->db->beginTransaction();
        $insertados = 0;
        $actualizados = 0;
        $errores = [];
        
        try {
            foreach ($datos as $index => $fila) {
                $fila_num = $index + 2; // +2 porque index empieza en 0 y hay header
                
                // Validar datos requeridos
                if (empty($fila['A'])) {
                    $errores[] = "Fila $fila_num: Nombre de marca es requerido";
                    continue;
                }
                
                $nombre = trim($fila['A']);
                $descripcion = isset($fila['B']) ? trim($fila['B']) : null;
                
                // Verificar si ya existe
                $stmt = $this->db->prepare("SELECT id FROM marcas WHERE nombre = ?");
                $stmt->execute([$nombre]);
                $existe = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existe) {
                    // Actualizar
                    $stmt = $this->db->prepare("
                        UPDATE marcas 
                        SET descripcion = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$descripcion, $existe['id']]);
                    $actualizados++;
                } else {
                    // Insertar nueva
                    $stmt = $this->db->prepare("
                        INSERT INTO marcas (nombre, descripcion, activo, created_at, updated_at) 
                        VALUES (?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([$nombre, $descripcion]);
                    $insertados++;
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'insertados' => $insertados,
                'actualizados' => $actualizados,
                'errores' => $errores,
                'total_procesado' => $insertados + $actualizados
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => 'Error al importar marcas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Importa modelos desde array de datos
     */
    public function importarModelos($datos) {
        $this->db->beginTransaction();
        $insertados = 0;
        $actualizados = 0;
        $errores = [];
        
        try {
            foreach ($datos as $index => $fila) {
                $fila_num = $index + 2;
                
                // Validar datos requeridos
                if (empty($fila['A']) || empty($fila['B'])) {
                    $errores[] = "Fila $fila_num: Marca y Modelo son requeridos";
                    continue;
                }
                
                $marca_nombre = trim($fila['A']);
                $modelo_nombre = trim($fila['B']);
                $descripcion = isset($fila['C']) ? trim($fila['C']) : null;
                
                // Buscar ID de la marca
                $stmt = $this->db->prepare("SELECT id FROM marcas WHERE nombre = ? AND activo = 1");
                $stmt->execute([$marca_nombre]);
                $marca = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$marca) {
                    $errores[] = "Fila $fila_num: Marca '$marca_nombre' no existe. Créela primero.";
                    continue;
                }
                
                $id_marca = $marca['id'];
                
                // Verificar si ya existe el modelo para esa marca
                $stmt = $this->db->prepare("
                    SELECT id FROM modelos 
                    WHERE id_marca = ? AND nombre = ?
                ");
                $stmt->execute([$id_marca, $modelo_nombre]);
                $existe = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existe) {
                    // Actualizar
                    $stmt = $this->db->prepare("
                        UPDATE modelos 
                        SET descripcion = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$descripcion, $existe['id']]);
                    $actualizados++;
                } else {
                    // Insertar nuevo
                    $stmt = $this->db->prepare("
                        INSERT INTO modelos (id_marca, nombre, descripcion, activo, created_at, updated_at) 
                        VALUES (?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([$id_marca, $modelo_nombre, $descripcion]);
                    $insertados++;
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'insertados' => $insertados,
                'actualizados' => $actualizados,
                'errores' => $errores,
                'total_procesado' => $insertados + $actualizados
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => 'Error al importar modelos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Importa equipos desde array de datos
     */
    public function importarEquipos($datos) {
        $this->db->beginTransaction();
        $insertados = 0;
        $actualizados = 0;
        $errores = [];
        
        try {
            foreach ($datos as $index => $fila) {
                $fila_num = $index + 2;
                
                // Validar datos requeridos mínimos
                if (empty($fila['A'])) {
                    $errores[] = "Fila $fila_num: Código patrimonial es requerido";
                    continue;
                }
                
                $codigo_patrimonial = trim($fila['A']);
                $numero_serie = isset($fila['B']) ? trim($fila['B']) : null;
                $marca_nombre = isset($fila['C']) ? trim($fila['C']) : null;
                $modelo_nombre = isset($fila['D']) ? trim($fila['D']) : null;
                $clasificacion = isset($fila['E']) ? trim($fila['E']) : 'impresora';
                $ubicacion_fisica = isset($fila['F']) ? trim($fila['F']) : null;
                $observaciones = isset($fila['G']) ? trim($fila['G']) : null;
                $anio_adquisicion = isset($fila['H']) ? trim($fila['H']) : null;
                $estado_nombre = isset($fila['I']) ? trim($fila['I']) : 'Operativo';
                
                // Validar clasificación
                if (!in_array(strtolower($clasificacion), ['impresora', 'multifuncional'])) {
                    $clasificacion = 'impresora';
                }
                $clasificacion = strtolower($clasificacion);
                
                // Buscar ID de marca y modelo
                $id_marca = null;
                $id_modelo = null;
                
                if ($marca_nombre) {
                    $stmt = $this->db->prepare("SELECT id FROM marcas WHERE nombre = ? AND activo = 1");
                    $stmt->execute([$marca_nombre]);
                    $marca = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($marca) {
                        $id_marca = $marca['id'];
                        
                        if ($modelo_nombre) {
                            $stmt = $this->db->prepare("
                                SELECT id FROM modelos 
                                WHERE id_marca = ? AND nombre = ? AND activo = 1
                            ");
                            $stmt->execute([$id_marca, $modelo_nombre]);
                            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($modelo) {
                                $id_modelo = $modelo['id'];
                            }
                        }
                    }
                }
                
                // Buscar ID del estado
                $stmt = $this->db->prepare("SELECT id FROM estados_equipo WHERE nombre = ?");
                $stmt->execute([$estado_nombre]);
                $estado = $stmt->fetch(PDO::FETCH_ASSOC);
                $id_estado = $estado ? $estado['id'] : 1; // Default: Operativo
                
                // Validar y convertir año
                $anio_sql = null;
                if ($anio_adquisicion) {
                    // Si es fecha completa, extraer solo el año
                    if (preg_match('/(\d{4})/', $anio_adquisicion, $matches)) {
                        $anio_sql = $matches[1];
                        // Validar que sea un año razonable
                        if ($anio_sql < 1990 || $anio_sql > date('Y') + 1) {
                            $errores[] = "Fila $fila_num: Año '$anio_sql' no es válido";
                            $anio_sql = null;
                        }
                    }
                }
                
                // Verificar si ya existe el equipo
                $stmt = $this->db->prepare("SELECT id FROM equipos WHERE codigo_patrimonial = ?");
                $stmt->execute([$codigo_patrimonial]);
                $existe = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existe) {
                    // Actualizar
                    $stmt = $this->db->prepare("
                        UPDATE equipos SET 
                            clasificacion = ?,
                            marca = ?,
                            modelo = ?,
                            numero_serie = ?,
                            id_marca = ?,
                            id_modelo = ?,
                            ubicacion_fisica = ?,
                            observaciones = ?,
                            anio_adquisicion = ?,
                            id_estado = ?,
                            fecha_actualizacion = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $clasificacion,
                        $marca_nombre ?? '',
                        $modelo_nombre ?? '',
                        $numero_serie,
                        $id_marca,
                        $id_modelo,
                        $ubicacion_fisica,
                        $observaciones,
                        $anio_sql,
                        $id_estado,
                        $existe['id']
                    ]);
                    $actualizados++;
                } else {
                    // Insertar nuevo
                    $stmt = $this->db->prepare("
                        INSERT INTO equipos (
                            codigo_patrimonial, clasificacion, marca, modelo,
                            numero_serie, id_marca, id_modelo, 
                            ubicacion_fisica, observaciones, anio_adquisicion, 
                            id_estado, activo, fecha_creacion, fecha_actualizacion
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $codigo_patrimonial,
                        $clasificacion,
                        $marca_nombre ?? '',
                        $modelo_nombre ?? '',
                        $numero_serie,
                        $id_marca,
                        $id_modelo,
                        $ubicacion_fisica,
                        $observaciones,
                        $anio_sql,
                        $id_estado
                    ]);
                    $insertados++;
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'insertados' => $insertados,
                'actualizados' => $actualizados,
                'errores' => $errores,
                'total_procesado' => $insertados + $actualizados
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => 'Error al importar equipos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera plantilla Excel para marcas
     */
    public function generarPlantillaMarcas() {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Headers
        $sheet->setCellValue('A1', 'Nombre Marca *');
        $sheet->setCellValue('B1', 'Descripción');
        
        // Ejemplos
        $sheet->setCellValue('A2', 'HP');
        $sheet->setCellValue('B2', 'Hewlett-Packard');
        
        $sheet->setCellValue('A3', 'Epson');
        $sheet->setCellValue('B3', 'Impresoras Epson');
        
        $sheet->setCellValue('A4', 'Canon');
        $sheet->setCellValue('B4', 'Impresoras multifuncionales');
        
        // Estilos
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(50);
        
        return $spreadsheet;
    }
    
    /**
     * Genera plantilla Excel para modelos
     */
    public function generarPlantillaModelos() {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Headers
        $sheet->setCellValue('A1', 'Marca *');
        $sheet->setCellValue('B1', 'Modelo *');
        $sheet->setCellValue('C1', 'Descripción');
        
        // Ejemplos
        $sheet->setCellValue('A2', 'HP');
        $sheet->setCellValue('B2', 'LaserJet Pro M404dn');
        $sheet->setCellValue('C2', 'Impresora láser monocromática');
        
        $sheet->setCellValue('A3', 'Epson');
        $sheet->setCellValue('B3', 'EcoTank L3250');
        $sheet->setCellValue('C3', 'Multifuncional de tanque de tinta');
        
        $sheet->setCellValue('A4', 'Canon');
        $sheet->setCellValue('B4', 'PIXMA G3160');
        $sheet->setCellValue('C4', 'Impresora multifuncional WiFi');
        
        // Estilos
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(50);
        
        return $spreadsheet;
    }
    
    /**
     * Genera plantilla Excel para equipos
     */
    public function generarPlantillaEquipos() {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Headers
        $sheet->setCellValue('A1', 'Código Patrimonial *');
        $sheet->setCellValue('B1', 'Número de Serie');
        $sheet->setCellValue('C1', 'Marca');
        $sheet->setCellValue('D1', 'Modelo');
        $sheet->setCellValue('E1', 'Clasificación (impresora/multifuncional)');
        $sheet->setCellValue('F1', 'Ubicación Física');
        $sheet->setCellValue('G1', 'Observaciones');
        $sheet->setCellValue('H1', 'Año Adquisición (YYYY)');
        $sheet->setCellValue('I1', 'Estado');
        
        // Ejemplos
        $sheet->setCellValue('A2', 'IMP-001');
        $sheet->setCellValue('B2', 'SN123456789');
        $sheet->setCellValue('C2', 'HP');
        $sheet->setCellValue('D2', 'LaserJet Pro M404dn');
        $sheet->setCellValue('E2', 'impresora');
        $sheet->setCellValue('F2', 'Oficina Principal - Piso 3');
        $sheet->setCellValue('G2', 'Impresora en buenas condiciones');
        $sheet->setCellValue('H2', '2024');
        $sheet->setCellValue('I2', 'Operativo');
        
        $sheet->setCellValue('A3', 'IMP-002');
        $sheet->setCellValue('B3', 'SN987654321');
        $sheet->setCellValue('C3', 'Epson');
        $sheet->setCellValue('D3', 'EcoTank L3250');
        $sheet->setCellValue('E3', 'multifuncional');
        $sheet->setCellValue('F3', 'Edificio B - Piso 2');
        $sheet->setCellValue('G3', 'Requiere revisión periódica');
        $sheet->setCellValue('H3', '2023');
        $sheet->setCellValue('I3', 'En Mantenimiento');
        
        // Estilos
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(28);
        $sheet->getColumnDimension('I')->setWidth(18);
        
        return $spreadsheet;
    }
}
