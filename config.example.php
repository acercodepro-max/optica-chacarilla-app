<?php
// Cambia este PIN antes de publicar. Es la clave del panel de la empresa.
// Debe tener al menos 8 caracteres (mejor con letras y números). Con '1234' el panel no deja entrar.
// Tras 5 intentos fallidos desde una misma conexión, el panel se bloquea 15 minutos.
return [
  'pin'  => '1234',
  'zona' => 'America/Lima',
];
