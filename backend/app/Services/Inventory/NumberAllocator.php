<?php

namespace App\Services\Inventory;

use App\Enums\DocumentType;
use App\Exceptions\InventoryException;
use Illuminate\Support\Facades\DB;

class NumberAllocator
{
    public function next(DocumentType $type): string
    {
        return $this->allocate($type->value, $type->prefix());
    }

    public function nextTransfer(): string
    {
        return $this->allocate('transfer', 'TRF');
    }

    private function allocate(string $kind, string $prefix): string
    {
        $year = (int) now('UTC')->format('Y');
        DB::statement('INSERT INTO document_sequences (`kind`, `year`, `last_value`) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE id = id', [$kind, $year]);
        $row = DB::table('document_sequences')->where('kind', $kind)->where('year', $year)->lockForUpdate()->first();
        if (! $row || bccomp((string) $row->last_value, (string) PHP_INT_MAX, 0) >= 0) {
            throw new InventoryException('NUMBER_EXHAUSTED', 'Document numbering capacity has been reached.', 503);
        }
        $next = bcadd((string) $row->last_value, '1', 0);
        DB::table('document_sequences')->where('id', $row->id)->update(['last_value' => $next]);

        return $prefix.'-'.$year.'-'.str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
