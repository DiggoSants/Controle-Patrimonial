<?php
session_start();
session_destroy();
header('Location: ../Controle-Patrimonial/form/FORMULARIO.html');
exit;
?>
