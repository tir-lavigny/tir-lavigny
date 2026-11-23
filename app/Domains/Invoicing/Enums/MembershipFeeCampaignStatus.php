<?php

namespace App\Domains\Invoicing\Enums;

enum MembershipFeeCampaignStatus: string
{
    case Draft = 'draft';
    case Preview = 'preview';
    case Validated = 'validated';
    case Invoiced = 'invoiced';
    case Issued = 'issued';
    case Cancelled = 'cancelled';
}
