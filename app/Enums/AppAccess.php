<?php

namespace App\Enums;

enum AppAccess: string
{
    case BackOffice = 'access backoffice';
    case Register = 'access register';
}
