<?php

namespace App\Enums;

enum OrderResponseStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
