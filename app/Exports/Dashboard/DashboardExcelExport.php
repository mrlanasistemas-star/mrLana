<?php

namespace App\Exports\Dashboard;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DashboardExcelExport implements WithMultipleSheets
{
    public function __construct(
        private string $profileLabel,
        private string $generatedAt,
        private array $data,
        private bool $includeMixes = true,
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new Sheets\ResumenSheet($this->profileLabel, $this->generatedAt, $this->data);
        $sheets[] = new Sheets\KpisSheet($this->data);

        $sheets[] = new Sheets\ActivityDailySheet($this->data);
        $sheets[] = new Sheets\AmountsDailySheet($this->data);

        if ($this->includeMixes) {
            $sheets[] = new Sheets\StatusMixSheet($this->data);
            $sheets[] = new Sheets\ComprobantesMixSheet($this->data);
        }

        return $sheets;
    }
}
