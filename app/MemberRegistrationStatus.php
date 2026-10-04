<?php

namespace App;

enum MemberRegistrationStatus: string
{
    case PENDING = 'pending';
    case FORM_FILLED = 'form_filled';
    case DAV_CREATED = 'dav_created';
    case MEMBER_CREATED = 'member_created';
    case COMPLETED = 'completed';
    case ERROR = 'error';
}
