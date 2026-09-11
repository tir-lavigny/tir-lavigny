<?php

namespace App\Enums;

enum SwissCanton: string
{
    case AG = 'AG';
    case BL = 'BL';
    case BS = 'BS';
    case BE = 'BE';
    case FR = 'FR';
    case GE = 'GE';
    case GL = 'GL';
    case GR = 'GR';
    case JU = 'JU';
    case LU = 'LU';
    case NE = 'NE';
    case NW = 'NW';
    case OW = 'OW';
    case SG = 'SG';
    case SH = 'SH';
    case SO = 'SO';
    case TI = 'TI';
    case TG = 'TG';
    case UR = 'UR';
    case VD = 'VD';
    case VS = 'VS';
    case ZG = 'ZG';
    case ZH = 'ZH';

    /**
     * Get all canton codes as a string for validation rules.
     */
    public static function validationRule(): string
    {
        return implode(',', array_column(self::cases(), 'value'));
    }
}
