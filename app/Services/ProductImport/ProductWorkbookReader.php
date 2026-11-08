<?php

namespace App\Services\ProductImport;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Throwable;
use ZipArchive;

final class ProductWorkbookReader
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function read(string $path): array
    {
        $this->inspectArchive($path);
        $reader = new Xlsx;
        $book = null;
        try {
            $info = $reader->listWorksheetInfo($path);
            $names = array_column($info, 'worksheetName');
            if (! in_array('Import_Products', $names, true)) {
                $this->fail('شیت Import_Products پیدا نشد.');
            }
            $limits = ['Import_Products' => 5000, 'Categories' => 2000, 'Specifications_Proposed' => 20000];
            foreach ($info as $sheet) {
                if (isset($limits[$sheet['worksheetName']]) && ($sheet['totalRows'] > $limits[$sheet['worksheetName']] + 1 || $sheet['totalColumns'] > 64)) {
                    $this->fail('تعداد ردیف‌ها یا ستون‌های شیت از ظرفیت مجاز بیشتر است.');
                }
            }
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $reader->setLoadSheetsOnly(array_keys($limits));
            $book = $reader->load($path);
            $result = ['sheet_names' => $names];
            foreach ($limits as $name => $limit) {
                $sheet = $book->getSheetByName($name);
                $result[$name] = [];
                if (! $sheet) {
                    continue;
                }
                $headers = [];
                $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
                for ($column = 1; $column <= $maxColumn; $column++) {
                    $cell = $sheet->getCell([$column, 1]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $this->fail('Header نباید فرمول باشد.');
                    }
                    $header = strtolower(trim(ltrim((string) $cell->getValue(), "\xEF\xBB\xBF")));
                    if ($name === 'Categories') {
                        $header = ['category_slug' => 'slug', 'category_name' => 'name'][$header] ?? $header;
                    }
                    if ($header === '') {
                        continue;
                    }
                    if (in_array($header, $headers, true)) {
                        $this->fail("ستون تکراری {$header} در شیت {$name}.");
                    }
                    $headers[$column] = $header;
                }
                $required = match ($name) {
                    'Import_Products' => ['sku', 'title', 'slug', 'price'],
                    'Categories' => ['slug', 'name'],
                    default => ['sku', 'key', 'value'],
                };
                $missing = array_diff($required, $headers);
                if ($missing !== []) {
                    $this->fail("ستون‌های الزامی شیت {$name}: ".implode('، ', $missing));
                }
                for ($number = 2; $number <= $sheet->getHighestDataRow(); $number++) {
                    $row = ['__row' => $number, '__errors' => [], '__warnings' => []];
                    $hasValue = false;
                    foreach ($headers as $column => $header) {
                        $cell = $sheet->getCell([$column, $number]);
                        $value = $cell->getValue();
                        if ($cell->getDataType() === DataType::TYPE_FORMULA || $cell->getDataType() === DataType::TYPE_ERROR) {
                            $row['__errors'][] = "{$name}!{$cell->getCoordinate()}: فرمول و خطای اکسل پذیرفته نمی‌شود.";
                            $value = null;
                            $hasValue = true;
                        }
                        if ($value instanceof RichText) {
                            $value = $value->getPlainText();
                        }
                        if (in_array($header, ['sku', 'slug', 'category_slug'], true) && is_numeric($value) && ! is_string($value)) {
                            $row['__warnings'][] = "{$header}: شناسه در اکسل عددی است؛ صفرهای ابتدایی قابل بازیابی نیستند.";
                        }
                        $row[$header] = is_scalar($value) ? (string) $value : null;
                        $hasValue = $hasValue || ($row[$header] !== null && trim($row[$header]) !== '');
                    }
                    if ($hasValue) {
                        $result[$name][] = $row;
                    }
                }
            }
            if ($result['Import_Products'] === []) {
                $this->fail('شیت محصولات خالی است.');
            }

            return $result;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->fail('فایل XLSX قابل خواندن نیست یا ساختار معتبری ندارد.');
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    private function inspectArchive(string $path): void
    {
        if (! is_file($path) || filesize($path) > self::MAX_BYTES || filesize($path) === 0) {
            $this->fail('فایل باید یک XLSX معتبر با حجم حداکثر ۱۰ مگابایت باشد.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('ساختار فایل XLSX معتبر نیست.');
        }
        try {
            if ($zip->numFiles > 3000 || $zip->locateName('xl/workbook.xml') === false || $zip->locateName('[Content_Types].xml') === false) {
                $this->fail('ساختار فایل یا تعداد اجزای آن مجاز نیست.');
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $total += $entry['size'];
                if ($total > 100 * 1024 * 1024 || $entry['size'] > 40 * 1024 * 1024
                    || preg_match('~(^/|\\\\|(^|/)\.\.(/|$)|^[a-z]+:)~i', $name)
                    || str_contains(strtolower($name), 'vbaproject') || str_starts_with($name, 'xl/externalLinks/')
                    || str_starts_with($name, 'xl/embeddings/')) {
                    $this->fail('فایل شامل محتوای ناامن یا بیش از ظرفیت مجاز است.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
