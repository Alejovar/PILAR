<?php
// Clase DE PRUEBA, para generar el hash para registrar a un nuevo gerente, por el motivo de que este tipo de usuario
// tendria permisos absolutos sobre el sistema, el primer gerente registrado tiene que ser ingresado por los
// administradores de este
// Vamos a usar una contraseña muy simple para la prueba
echo password_hash('dican123', PASSWORD_DEFAULT);
?>
