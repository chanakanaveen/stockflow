<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ApprovedPosted = 'approved_posted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
