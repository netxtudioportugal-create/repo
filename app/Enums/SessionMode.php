<?php

namespace App\Enums;

enum SessionMode: string
{
    /** One domain, answer checked and explained item by item. */
    case Domain = 'domain';

    /** All items, server-enforced time limit, answers shown at the end. */
    case Timed = 'timed';
}
