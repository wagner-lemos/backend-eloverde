<?php
namespace App\Domain\Waste\Enums;

enum SuggestedAction: string
{
    case EXECUTE = 'execute';
    case REVIEW_DOCUMENTS = 'review_documents';
    case FIX_STATE = 'fix_state';
    case REVIEW_OR_MERGE = 'review_or_merge';
    case MANUAL_REVIEW = 'manual_review';
}