<?php

namespace App;

enum DavGroupLabels: string
{
    case ACTIVE_MEMBERS = 'Actifs';
    case SOFT_DELETED_MEMBERS = 'Archives';
}
