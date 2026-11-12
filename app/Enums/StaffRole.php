<?php

namespace App\Enums;

enum StaffRole: string
{
    case Admin = 'admin';
    case BcbaReviewer = 'bcba_reviewer';
    case FairnessReviewer = 'fairness_reviewer';
    case LanguageReviewer = 'language_reviewer';
    case Tester = 'tester';
}
