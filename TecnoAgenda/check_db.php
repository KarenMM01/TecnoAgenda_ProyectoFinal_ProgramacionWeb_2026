<?php
require 'conexion.php';
try {
    $conexion->exec("
        ALTER TABLE usuarios
        ADD COLUMN IF NOT EXISTS descripcion TEXT,
        ADD COLUMN IF NOT EXISTS materias_inscritas TEXT
    ");
    echo "Columns added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
