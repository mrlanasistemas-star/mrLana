<?php

namespace App\Exports\Dashboard\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ResumenSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(
        private string $role,
        private string $generatedAt,
        private array $data
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    public function array(): array
    {
        return [
            ['Dashboard', $this->data['headline'] ?? 'Dashboard'],
            ['Perfil', $this->role],
            ['Generado', $this->generatedAt],
            ['Descripción', $this->data['subheadline'] ?? ''],
        ];
    }
}
