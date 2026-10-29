<?php

namespace Paw\Models;

use RuntimeException;

// El servicio de correo rechazó el envío o no respondió (lo lanza ReservaMailer).
class CorreoException extends RuntimeException
{
}
