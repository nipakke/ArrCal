<?php

declare(strict_types=1);

namespace ArrCal\Domain;

enum MediaStatus: string
{
    case Downloaded = 'downloaded';
    case Missing = 'missing';
    case Upcoming = 'upcoming';
    case Unmonitored = 'unmonitored';
    case Error = 'error';
    case Queued = 'queued';

    /**
     * Return a human-readable label for the media status.
     *
     * Used by the frontend to display friendly status names.
     */
    public function label(): string
    {
        return match ($this) {
            self::Downloaded => 'Downloaded',
            self::Missing => 'Missing',
            self::Upcoming => 'Upcoming',
            self::Unmonitored => 'Unmonitored',
            self::Error => 'Error',
            self::Queued => 'Queued',
        };
    }
}
