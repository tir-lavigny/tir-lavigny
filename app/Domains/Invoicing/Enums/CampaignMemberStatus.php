<?php

namespace App\Domains\Invoicing\Enums;

enum CampaignMemberStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Excluded = 'excluded';
}
