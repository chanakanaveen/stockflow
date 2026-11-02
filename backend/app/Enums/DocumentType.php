<?php

namespace App\Enums;

enum DocumentType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';

    public function permission(): string
    {
        return $this === self::Receipt ? 'inventory.receive' : 'inventory.issue';
    }

    public function prefix(): string
    {
        return $this === self::Receipt ? 'RCV' : 'ISS';
    }
}
