<?php

namespace App\Domains\Invoicing\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Printed = 'printed';
}
