<?php

namespace App\Domains\Invoicing\Enums;

enum EnvelopeWindow: string
{
    case Left = 'left';
    case Right = 'right';

    public function label(): string
    {
        return match ($this) {
            self::Left => 'Fenêtre à gauche',
            self::Right => 'Fenêtre à droite',
        };
    }
}
