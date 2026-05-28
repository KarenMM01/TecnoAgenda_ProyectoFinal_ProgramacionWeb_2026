<?php
require 'conexion.php';

// Create notificaciones table
try {
    $conexion->exec("
        CREATE TABLE IF NOT EXISTS notificaciones (
            id SERIAL PRIMARY KEY,
            para_email VARCHAR(255) NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            mensaje TEXT NOT NULL,
            de_nombre VARCHAR(255),
            de_email VARCHAR(255),
            leida BOOLEAN DEFAULT FALSE,
            fecha TIMESTAMP DEFAULT NOW()
        )
    ");
    echo "Tabla notificaciones creada correctamente.<br>";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
echo "Listo.";
