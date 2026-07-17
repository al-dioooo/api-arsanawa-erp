<?php

namespace App\Modules\Finance\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Builds a single-sheet workbook from a collection of rows.
 *
 * This owns what the maatwebsite/excel concerns used to provide (headings, row
 * mapping, a bold header and auto-sized columns) so the exports depend directly
 * on phpoffice/phpspreadsheet. That wrapper pinned phpspreadsheet to ^1.30,
 * whose last PHP 8.5-compatible release carries unpatched advisories.
 */
abstract class SpreadsheetExport
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    abstract public function collection(): Collection;

    /**
     * @return array<int, string>
     */
    abstract public function headings(): array;

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    abstract public function map(array $row): array;

    abstract public function title(): string;

    public function sheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->title());

        $headings = $this->headings();
        $sheet->fromArray($headings, null, 'A1');

        $rows = $this->collection()
            ->map(fn (array $row): array => $this->map($row))
            ->all();

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headings));
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);

        // Replaces the ShouldAutoSize concern; without an explicit per-column
        // call the widths silently regress and no assertion catches it.
        foreach (range(1, count($headings)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
