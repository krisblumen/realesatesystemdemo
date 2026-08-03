<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'activo';
    case Suspended = 'suspendido';
    case Pending = 'pendiente';
}
