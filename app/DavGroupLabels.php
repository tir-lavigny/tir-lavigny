<?php

namespace App;

enum DavGroupLabels: string
{
    case ACTIVE_MEMBERS = 'Actifs';
    case PASSIVE_MEMBERS = 'Passifs';
    case SOFT_DELETED_MEMBERS = 'Archives';
}
