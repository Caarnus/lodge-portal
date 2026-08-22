<?php

namespace App\Enums;

enum DistributionRequestStatus: string
{
    case PendingVerification = 'pending_verification';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
