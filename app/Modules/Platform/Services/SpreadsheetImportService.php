<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Organization\Actions\GetCompanyProfile;
use App\Modules\Platform\Jobs\CommitSpreadsheetImport;
use App\Modules\Platform\Models\ImportBatch;
use App\Modules\Platform\Models\ImportRow;
use App\Support\SupabaseStorage;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpreadsheetImportService
{
    public const POS_KIND = 'pos_catering_orders';

    public const INVENTORY_KIND = 'inventory_products';

    public const MAX_ROWS = 1000;

    private const CONFIGURED_POS_SHEET = 'google-sheet.csv';

    public function __construct(
        private readonly SettingsManager $settings,
        private readonly SupabaseStorage $supabaseStorage,
        private readonly GetCompanyProfile $companyProfile,
    ) {}

    /**
     * @return list<string>
     */
    public function headers(string $kind): array
    {
        return match ($kind) {
            self::POS_KIND => [
                'order_reference',
                'branch_code',
                'customer_name',
                'customer_email',
                'customer_phone',
                'order_date',
                'fulfilment_date',
                'delivery_address',
                'sku',
                'quantity',
                'unit_price',
                'discount',
                'notes',
            ],
            self::INVENTORY_KIND => [
                'product_name',
                'product_description',
                'category_path',
                'brand_name',
                'base_uom_code',
                'sku',
                'product_unit_name',
                'barcode',
                'variant_values',
                'price_list_name',
                'currency_code',
                'price',
                'effective_from',
                'branch_code',
                'opening_quantity',
                'unit_cost',
                'batch_number',
                'received_at',
                'expiry_date',
                'production_date',
                'status',
                'track_stock',
            ],
        };
    }

    public function templateResponse(string $kind, string $format)
    {
        $headers = $this->headers($kind);
        $basename = $kind === self::POS_KIND ? 'pos-catering-orders-template' : 'inventory-products-template';

        if ($format === 'csv') {
            return response(implode(',', $headers)."\n", 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$basename.'.csv"',
            ]);
        }

        if ($format !== 'xlsx') {
            abort(404);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');
        $sheet->fromArray($headers, null, 'A1');

        $tmp = tempnam(sys_get_temp_dir(), 'arsanawa-template-');
        (new Xlsx($spreadsheet))->save($tmp);
        $contents = file_get_contents($tmp);
        @unlink($tmp);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$basename.'.xlsx"',
        ]);
    }

    public function inspect(string $kind, int $companyId, User $user, ?UploadedFile $file, ?string $sourceUrl): ImportBatch
    {
        $source = $sourceUrl !== null ? 'url' : 'file';
        $stored = $sourceUrl !== null
            ? $this->storeRemoteSource($sourceUrl, $companyId)
            : $this->storeUploadedSource($file, $companyId);

        $sheets = $this->inspectSheets($kind, $companyId, $stored['path'], $stored['original_name']);

        return ImportBatch::query()->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'kind' => $kind,
            'source' => $source,
            'source_path' => $stored['path'],
            'source_url' => $sourceUrl,
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'sheets' => $sheets,
            'status' => 'inspected',
        ]);
    }

    public function previewConfiguredPosCateringImport(int $companyId, User $user): ImportBatch
    {
        $company = $this->companyProfile->execute($companyId, mustExist: true);

        if ($company->slug !== 'sekalori') {
            abort(403, __('Configured catering form imports are only available for SEKALORI.'));
        }

        $config = $this->configuredPosCateringImportConfig($companyId);
        $sourceUrl = (string) ($config['source_url'] ?? '');

        if ($sourceUrl === '') {
            throw ValidationException::withMessages([
                'source_url' => [__('A configured Google Sheets source URL is required.')],
            ]);
        }

        $stored = $this->storeRemoteSource($sourceUrl, $companyId);
        $records = $this->readCsv($stored['path']);

        if ($records->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'source_url' => [__('A sheet may contain at most :count rows.', ['count' => self::MAX_ROWS])],
            ]);
        }

        $import = ImportBatch::query()->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'kind' => self::POS_KIND,
            'source' => 'url',
            'source_path' => $stored['path'],
            'source_url' => $sourceUrl,
            'original_name' => self::CONFIGURED_POS_SHEET,
            'mime_type' => $stored['mime_type'],
            'sheets' => [[
                'name' => self::CONFIGURED_POS_SHEET,
                'supported' => true,
                'row_count' => $records->count(),
                'reason' => null,
            ]],
            'selected_sheet' => self::CONFIGURED_POS_SHEET,
            'status' => 'inspected',
        ]);

        $errorCount = 0;

        foreach ($records as $index => $record) {
            $rowNumber = $index + 2;
            [$normalized, $mappingErrors] = $this->mapConfiguredPosCateringRow($record, $config);
            $errors = $this->validateRow(self::POS_KIND, $companyId, $normalized);

            if (isset($mappingErrors['menu_type'])) {
                unset($errors['sku']);
            }

            $errors = array_merge($errors, $mappingErrors);

            if ($errors !== []) {
                $errorCount += count($errors);
            }

            ImportRow::query()->create([
                'import_batch_id' => $import->id,
                'row_number' => $rowNumber,
                'raw' => $record,
                'normalized' => $normalized,
                'errors' => $errors,
            ]);
        }

        $import->forceFill([
            'status' => $errorCount === 0 ? 'previewed' : 'invalid',
            'row_count' => $records->count(),
            'error_count' => $errorCount,
            'created_count' => 0,
            'updated_count' => 0,
            'failure_message' => null,
        ])->save();

        return $import->fresh(['rows']);
    }

    public function preview(ImportBatch $import, string $sheetName): ImportBatch
    {
        $sheet = collect($import->sheets ?? [])->firstWhere('name', $sheetName);

        if (! $sheet || ($sheet['supported'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'sheet_name' => [__('The selected sheet is not supported by this import template.')],
            ]);
        }

        $records = $this->readSheet($import->source_path, $sheetName);

        if ($records->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'sheet_name' => [__('A sheet may contain at most :count rows.', ['count' => self::MAX_ROWS])],
            ]);
        }

        $import->rows()->delete();
        $errorCount = 0;

        foreach ($records as $index => $record) {
            $rowNumber = $index + 2;
            [$normalized, $mappingErrors] = $this->normalizePreviewRow($import, $record);
            $errors = $this->validateRow($import->kind, $import->company_id, $normalized);

            if (isset($mappingErrors['menu_type'])) {
                unset($errors['sku']);
            }

            $errors = array_merge($errors, $mappingErrors);

            if ($errors !== []) {
                $errorCount += count($errors);
            }

            ImportRow::query()->create([
                'import_batch_id' => $import->id,
                'row_number' => $rowNumber,
                'raw' => $record,
                'normalized' => $normalized,
                'errors' => $errors,
            ]);
        }

        $import->forceFill([
            'selected_sheet' => $sheetName,
            'status' => $errorCount === 0 ? 'previewed' : 'invalid',
            'row_count' => $records->count(),
            'error_count' => $errorCount,
            'created_count' => 0,
            'updated_count' => 0,
            'failure_message' => null,
        ])->save();

        return $import->fresh(['rows']);
    }

    public function commit(ImportBatch $import): ImportBatch
    {
        if ($import->status !== 'previewed' || $import->error_count > 0) {
            throw ValidationException::withMessages([
                'import' => [__('Only a valid previewed import can be committed.')],
            ]);
        }

        $import->forceFill(['status' => 'queued'])->save();
        CommitSpreadsheetImport::dispatch($import->id);

        return $import->fresh(['rows']);
    }

    /**
     * @return array{path: string, original_name: string, mime_type: string|null}
     */
    private function storeUploadedSource(?UploadedFile $file, int $companyId): array
    {
        if (! $file) {
            throw ValidationException::withMessages(['file' => [__('A spreadsheet file is required.')]]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $path = "imports/{$companyId}/".Str::uuid().'.'.$extension;
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages(['file' => [__('Unable to read uploaded spreadsheet file.')]]);
        }

        $this->storeImportSource($path, $contents, $file->getMimeType());

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * @return array{path: string, original_name: string, mime_type: string|null}
     */
    private function storeRemoteSource(string $sourceUrl, int $companyId): array
    {
        $host = parse_url($sourceUrl, PHP_URL_HOST);

        if ($host !== 'docs.google.com') {
            throw ValidationException::withMessages(['source_url' => [__('Only public Google Sheets links are supported.')]]);
        }

        $response = $this->downloadRemoteSource($sourceUrl);

        if (! $response->successful()) {
            foreach ($this->fallbackGoogleSheetsExportUrls($sourceUrl) as $fallbackUrl) {
                $response = $this->downloadRemoteSource($fallbackUrl);

                if ($response->successful()) {
                    break;
                }
            }
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages(['source_url' => [__('Unable to download the Google Sheets export.')]]);
        }

        $path = "imports/{$companyId}/".Str::uuid().'.csv';
        $this->storeImportSource($path, $response->body(), $response->header('Content-Type'));

        return [
            'path' => $path,
            'original_name' => 'google-sheet.csv',
            'mime_type' => $response->header('Content-Type'),
        ];
    }

    private function downloadRemoteSource(string $sourceUrl): HttpResponse
    {
        return Http::timeout(15)->get($sourceUrl);
    }

    /**
     * @return list<string>
     */
    private function fallbackGoogleSheetsExportUrls(string $sourceUrl): array
    {
        $parts = parse_url($sourceUrl);
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (($query['gid'] ?? null) !== '0') {
            return [];
        }

        unset($query['gid']);

        $fallbackUrl = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').($parts['path'] ?? '');
        $fallbackQuery = http_build_query($query);

        if ($fallbackQuery !== '') {
            $fallbackUrl .= '?'.$fallbackQuery;
        }

        return $fallbackUrl === $sourceUrl ? [] : [$fallbackUrl];
    }

    /**
     * @return list<array{name: string, supported: bool, row_count: int, reason: string|null}>
     */
    private function inspectSheets(string $kind, int $companyId, string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $records = $this->readCsv($path);
            $supported = $this->headersSupported($kind, array_keys($records->first() ?? []), $companyId);

            return [[
                'name' => $originalName,
                'supported' => $supported,
                'row_count' => $records->count(),
                'reason' => $supported ? null : __('Missing required template headers.'),
            ]];
        }

        $spreadsheet = IOFactory::load($this->localSpreadsheetPath($path));
        $sheets = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $headers = $this->sheetHeaders($sheet);
            $highestRow = max(0, $sheet->getHighestDataRow() - 1);
            $supported = $this->headersSupported($kind, $headers, $companyId);
            $sheets[] = [
                'name' => $sheet->getTitle(),
                'supported' => $supported,
                'row_count' => $highestRow,
                'reason' => $supported ? null : __('Missing required template headers.'),
            ];
        }

        return $sheets;
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function readSheet(string $path, string $sheetName): Collection
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->readCsv($path);
        }

        $spreadsheet = IOFactory::load($this->localSpreadsheetPath($path));
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if ($sheet === null) {
            return collect();
        }

        $headers = $this->sheetHeaders($sheet);
        $rows = [];

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $record = [];

            foreach ($headers as $index => $header) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $record[$header] = trim((string) $sheet->getCell("{$column}{$row}")->getFormattedValue());
            }

            if (collect($record)->filter(fn (string $value): bool => $value !== '')->isNotEmpty()) {
                $rows[] = $record;
            }
        }

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function readCsv(string $path): Collection
    {
        $contents = $this->readImportSource($path);
        $tmp = fopen('php://temp', 'r+');
        fwrite($tmp, $contents);
        rewind($tmp);

        $headers = null;
        $rows = [];

        while (($line = fgetcsv($tmp)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn (string $value): string => Str::snake(trim($value)), $line);

                continue;
            }

            $record = [];

            foreach ($headers as $index => $header) {
                $record[$header] = trim((string) ($line[$index] ?? ''));
            }

            if (collect($record)->filter(fn (string $value): bool => $value !== '')->isNotEmpty()) {
                $rows[] = $record;
            }
        }

        fclose($tmp);

        return collect($rows);
    }

    private function storeImportSource(string $path, string $contents, ?string $mimeType): void
    {
        if ($this->usesSupabaseStorage()) {
            $this->supabaseStorage->put($this->importsBucket(), $path, $contents, $mimeType);

            return;
        }

        Storage::put($path, $contents);
    }

    private function readImportSource(string $path): string
    {
        if ($this->usesSupabaseStorage()) {
            return $this->supabaseStorage->get($this->importsBucket(), $path);
        }

        return Storage::get($path);
    }

    private function localSpreadsheetPath(string $path): string
    {
        if (! $this->usesSupabaseStorage()) {
            return Storage::path($path);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $tmp = tempnam(sys_get_temp_dir(), 'arsanawa-import-');
        $localPath = $extension !== '' ? "{$tmp}.{$extension}" : $tmp;

        file_put_contents($localPath, $this->readImportSource($path));

        return $localPath;
    }

    private function importsBucket(): string
    {
        return (string) config('services.supabase.storage.imports_bucket');
    }

    private function usesSupabaseStorage(): bool
    {
        return (string) config('filesystems.default') === 'supabase';
    }

    /**
     * @return list<string>
     */
    private function sheetHeaders($sheet): array
    {
        $headers = [];

        for ($column = 1; $column <= Coordinate::columnIndexFromString($sheet->getHighestDataColumn()); $column++) {
            $value = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).'1')->getValue());

            if ($value !== '') {
                $headers[] = Str::snake($value);
            }
        }

        return $headers;
    }

    /**
     * @param  list<string>  $headers
     */
    private function headersSupported(string $kind, array $headers, ?int $companyId = null): bool
    {
        $required = $kind === self::POS_KIND
            ? ['order_reference', 'branch_code']
            : ['product_name', 'sku'];

        if (collect($required)->every(fn (string $header): bool => in_array($header, $headers, true))) {
            return true;
        }

        return $kind === self::POS_KIND
            && $companyId !== null
            && $this->configuredPosCateringHeadersSupported($companyId, $headers);
    }

    /**
     * @param  array<string, string>  $record
     * @return array<string, mixed>
     */
    private function normalize(string $kind, array $record): array
    {
        $normalized = [];

        foreach ($this->headers($kind) as $header) {
            $normalized[$header] = isset($record[$header]) ? trim((string) $record[$header]) : '';
        }

        foreach (['quantity', 'unit_price', 'discount', 'price', 'opening_quantity', 'unit_cost'] as $numeric) {
            if (array_key_exists($numeric, $normalized) && $normalized[$numeric] !== '') {
                $normalized[$numeric] = (float) $normalized[$numeric];
            }
        }

        if (array_key_exists('track_stock', $normalized)) {
            $normalized['track_stock'] = ! in_array(strtolower((string) $normalized['track_stock']), ['false', '0', 'no'], true);
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $record
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function normalizePreviewRow(ImportBatch $import, array $record): array
    {
        if ($this->shouldUseConfiguredPosCateringMapping($import->kind, $import->company_id, $record)) {
            return $this->mapConfiguredPosCateringRow(
                $record,
                $this->configuredPosCateringImportConfig($import->company_id),
            );
        }

        return [$this->normalize($import->kind, $record), []];
    }

    /**
     * @param  array<string, string>  $record
     */
    private function shouldUseConfiguredPosCateringMapping(string $kind, int $companyId, array $record): bool
    {
        if ($kind !== self::POS_KIND || ! $this->companyIsSekalori($companyId)) {
            return false;
        }

        try {
            return $this->configuredRecordHasRequiredHeaders(
                $record,
                $this->configuredPosCateringImportConfig($companyId),
            );
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @param  list<string>  $headers
     */
    private function configuredPosCateringHeadersSupported(int $companyId, array $headers): bool
    {
        if (! $this->companyIsSekalori($companyId)) {
            return false;
        }

        try {
            return $this->configuredRecordHasRequiredHeaders(
                array_fill_keys($headers, ''),
                $this->configuredPosCateringImportConfig($companyId),
            );
        } catch (ValidationException) {
            return false;
        }
    }

    private function companyIsSekalori(int $companyId): bool
    {
        return $this->companyProfile->execute($companyId)?->slug === 'sekalori';
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredPosCateringImportConfig(int $companyId): array
    {
        $config = $this->settings->get($companyId, 'pos', 'catering_form_import', []);

        if (! is_array($config)) {
            throw ValidationException::withMessages([
                'configuration' => [__('The configured catering form import settings are invalid.')],
            ]);
        }

        return $config;
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<string, mixed>  $config
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function mapConfiguredPosCateringRow(array $record, array $config): array
    {
        $errors = [];
        $fieldMap = is_array($config['field_map'] ?? null) ? $config['field_map'] : [];
        $value = fn (string $field): string => $this->configuredFieldValue($record, $fieldMap, $field);
        $timestamp = $value('timestamp');
        $responseDate = $this->configuredResponseDate($timestamp);
        $customerName = $value('customer_name');
        $customerPhone = $value('customer_phone');
        $menuType = $value('menu_type');
        $menuMap = is_array($config['menu_type_bundle_skus'] ?? null) ? $config['menu_type_bundle_skus'] : [];
        $sku = '';

        if ($menuType === '' || ! array_key_exists($menuType, $menuMap)) {
            $errors['menu_type'][] = __('Menu type is not configured.');
        } else {
            $sku = (string) $menuMap[$menuType];
        }

        $paymentLabel = $value('payment_method');
        $paymentMap = is_array($config['payment_method_map'] ?? null) ? $config['payment_method_map'] : [];
        $paymentMethod = null;

        if ($paymentLabel !== '') {
            if (! array_key_exists($paymentLabel, $paymentMap)) {
                $errors['payment_method'][] = __('Payment method is not configured.');
            } elseif ($paymentMap[$paymentLabel] !== null && $paymentMap[$paymentLabel] !== '') {
                $paymentMethod = (string) $paymentMap[$paymentLabel];
            }
        }

        return [[
            'order_reference' => $this->configuredOrderReference($timestamp, $customerPhone, $customerName, $menuType),
            'branch_code' => (string) ($config['default_branch_code'] ?? 'MAIN'),
            'customer_name' => $customerName,
            'customer_email' => '',
            'customer_phone' => $customerPhone,
            'order_date' => $responseDate->toDateString(),
            'fulfilment_date' => $responseDate->copy()->addDay()->toDateString(),
            'fulfilment_time_window' => $value('fulfilment_time_window'),
            'delivery_address' => $value('delivery_address'),
            'sku' => $sku,
            'quantity' => (float) ($config['default_quantity'] ?? 1),
            'unit_price' => '',
            'discount' => 0.0,
            'notes' => $value('notes'),
            'source_channel' => 'google_form',
            'payment_method' => $paymentMethod,
            'payment_reference' => $value('payment_reference'),
            'import_register_code' => $paymentMethod !== null ? (string) ($config['default_import_register_code'] ?? '') : '',
        ], $errors];
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<string, mixed>  $fieldMap
     */
    private function configuredFieldValue(array $record, array $fieldMap, string $field): string
    {
        $header = $this->configuredFieldHeader($fieldMap, $field);

        return trim((string) ($record[$header] ?? ''));
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<string, mixed>  $config
     */
    private function configuredRecordHasRequiredHeaders(array $record, array $config): bool
    {
        $fieldMap = is_array($config['field_map'] ?? null) ? $config['field_map'] : [];
        $requiredFields = [
            'timestamp',
            'customer_name',
            'customer_phone',
            'delivery_address',
            'menu_type',
            'fulfilment_time_window',
            'payment_method',
        ];

        return collect($requiredFields)->every(
            fn (string $field): bool => array_key_exists($this->configuredFieldHeader($fieldMap, $field), $record),
        );
    }

    /**
     * @param  array<string, mixed>  $fieldMap
     */
    private function configuredFieldHeader(array $fieldMap, string $field): string
    {
        return Str::snake((string) ($fieldMap[$field] ?? $field));
    }

    private function configuredResponseDate(string $timestamp): Carbon
    {
        if ($timestamp === '') {
            return now();
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return now();
        }
    }

    private function configuredOrderReference(string $timestamp, string $customerPhone, string $customerName, string $menuType): string
    {
        $hash = hash('sha256', implode('|', [$timestamp, $customerPhone, $customerName, $menuType]));

        return 'GFORM-'.strtoupper(substr($hash, 0, 12));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, list<string>>
     */
    private function validateRow(string $kind, int $companyId, array $row): array
    {
        return $kind === self::POS_KIND
            ? app(PosCateringImportProcessor::class)->validateRow($companyId, $row)
            : app(InventoryProductImportProcessor::class)->validateRow($companyId, $row);
    }
}
