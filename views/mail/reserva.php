<?php /* Cuerpo en texto plano del correo al personal (lo usa ReservaMailer, sin layout ni escape HTML). */ ?>
Se recibió una nueva reserva desde el sitio.

Nombre: <?= $reserva->nombre ?>

Correo: <?= $reserva->email ?>

Teléfono: <?= $reserva->telefono !== '' ? $reserva->telefono : '(no informado)' ?>


Libros:
<?php foreach ($reserva->items as $item): ?>
- <?= $item['cantidad'] ?> x <?= $item['libro']->titulo ?> (<?= $item['libro']->autor ?>) - <?= $item['libro']->precioFormateado() ?> c/u
<?php endforeach; ?>

Total estimado: <?= $reserva->totalFormateado() ?>

