<?php
/**
 * Controlador de Usuarios
 * Maneja operaciones CRUD de usuarios
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/LogAuditoria.php';
require_once __DIR__ . '/AuthController.php';

class UsuarioController {
    private $model;
    private $logModel;
    private $auth;
    
    public function __construct() {
        $this->model = new Usuario();
        $this->logModel = new LogAuditoria();
        $this->auth = new AuthController();
    }
    
    /**
     * Lista usuarios con paginación
     */
    public function listar($filtros = [], $page = 1) {
        $this->auth->requiereRol(['super_admin', 'admin']);
        
        $usuarios = $this->model->obtenerTodos($page, RECORDS_PER_PAGE, $filtros['rol'] ?? null, $filtros['buscar'] ?? null);
        $total = $this->model->contarTotal($filtros['rol'] ?? null, $filtros['buscar'] ?? null);
        $totalPaginas = ceil($total / RECORDS_PER_PAGE);
        
        return [
            'usuarios' => $usuarios,
            'total' => $total,
            'pagina_actual' => $page,
            'total_paginas' => $totalPaginas
        ];
    }
    
    /**
     * Obtiene un usuario por ID
     */
    public function obtener($id) {
        $this->auth->requiereRol(['super_admin', 'admin']);
        return $this->model->obtenerPorId($id);
    }
    
    /**
     * Crea un nuevo usuario
     */
    public function crear($datos) {
        $this->auth->requiereRol(['super_admin', 'admin']);
        
        // Validaciones
        $errores = $this->validar($datos);
        if (!empty($errores)) {
            return ['error' => 'Errores de validación', 'errores' => $errores];
        }
        
        $result = $this->model->crear($datos);
        
        if (isset($result['success'])) {
            // Registrar en log
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(),
                'crear_usuario',
                'usuarios',
                $result['id'],
                null,
                $datos
            );
        }
        
        return $result;
    }
    
    /**
     * Actualiza un usuario
     */
    public function actualizar($id, $datos) {
        $this->auth->requiereRol(['super_admin', 'admin']);
        
        // Obtener datos anteriores para log
        $datosAnteriores = $this->model->obtenerPorId($id);
        
        if (!$datosAnteriores) {
            return ['error' => 'Usuario no encontrado'];
        }
        
        // Validaciones
        $errores = $this->validar($datos, $id);
        if (!empty($errores)) {
            return ['error' => 'Errores de validación', 'errores' => $errores];
        }
        
        $result = $this->model->actualizar($id, $datos);
        
        if ($result) {
            // Registrar en log
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(),
                'actualizar_usuario',
                'usuarios',
                $id,
                $datosAnteriores,
                $datos
            );
            
            return ['success' => true];
        }
        
        return ['error' => 'Error al actualizar usuario'];
    }
    
    /**
     * Cambia contraseña de un usuario
     */
    public function cambiarPassword($id, $newPassword) {
        $this->auth->requiereAutenticacion();
        
        // Solo super_admin/admin pueden cambiar password de otros
        if ($id != $this->auth->obtenerUsuarioId()) {
            $this->auth->requiereRol(['super_admin', 'admin']);
        }
        
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['error' => 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres'];
        }
        
        $result = $this->model->cambiarPassword($id, $newPassword);
        
        if ($result) {
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(),
                'cambiar_password',
                'usuarios',
                $id
            );
            
            return ['success' => true];
        }
        
        return ['error' => 'Error al cambiar contraseña'];
    }
    
    /**
     * Elimina (desactiva) un usuario
     */
    public function eliminar($id) {
        $this->auth->requiereRol(['super_admin']);
        
        $datosAnteriores = $this->model->obtenerPorId($id);
        
        if (!$datosAnteriores) {
            return ['error' => 'Usuario no encontrado'];
        }
        
        $result = $this->model->eliminar($id);
        
        if ($result) {
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(),
                'eliminar_usuario',
                'usuarios',
                $id,
                $datosAnteriores,
                null
            );
            
            return ['success' => true];
        }
        
        return ['error' => 'Error al eliminar usuario'];
    }
    
    /**
     * Cambia el estado (activo/suspendido) de un usuario
     */
    public function cambiarEstado($id, $nuevoEstado) {
        $this->auth->requiereRol(['super_admin', 'admin']);
        
        $datosAnteriores = $this->model->obtenerPorId($id);
        
        if (!$datosAnteriores) {
            return ['error' => 'Usuario no encontrado'];
        }
        
        $result = $this->model->cambiarEstado($id, $nuevoEstado);
        
        if ($result) {
            $accion = $nuevoEstado ? 'activar_usuario' : 'suspender_usuario';
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(),
                $accion,
                'usuarios',
                $id,
                ['activo' => $datosAnteriores['activo']],
                ['activo' => $nuevoEstado]
            );
            
            return ['success' => true];
        }
        
        return ['error' => 'Error al cambiar estado del usuario'];
    }
    
    /**
     * Valida los datos de un usuario
     */
    private function validar($datos, $excludeId = null) {
        $errores = [];
        
        // Campos obligatorios
        if (empty($datos['username'])) {
            $errores['username'] = 'El nombre de usuario es obligatorio';
        } elseif ($this->model->existeUsername($datos['username'], $excludeId)) {
            $errores['username'] = 'El nombre de usuario ya existe';
        }
        
        if (empty($datos['email'])) {
            $errores['email'] = 'El email es obligatorio';
        } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'Formato de email inválido';
        } elseif ($this->model->existeEmail($datos['email'], $excludeId)) {
            $errores['email'] = 'El email ya está registrado';
        }
        
        if (empty($datos['nombre_completo'])) {
            $errores['nombre_completo'] = 'El nombre completo es obligatorio';
        }
        
        if (empty($datos['rol'])) {
            $errores['rol'] = 'El rol es obligatorio';
        }
        
        // Validar password solo al crear
        if (!$excludeId && empty($datos['password'])) {
            $errores['password'] = 'La contraseña es obligatoria';
        } elseif (!$excludeId && strlen($datos['password']) < PASSWORD_MIN_LENGTH) {
            $errores['password'] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
        }
        
        return $errores;
    }
}
