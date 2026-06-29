<?php

declare(strict_types=1);

namespace ArrCal\Domain;

enum MediaType: string
{
    case Movie = 'movie';
    case Episode = 'episode';
}
