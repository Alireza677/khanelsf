<?php

namespace App\Services\ProductImport;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProductImportTemplate
{
    public function workbook(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheets = [
            'Import_Products' => ProductImportMapper::PRODUCT_HEADERS,
            'Categories' => ProductImportMapper::CATEGORY_HEADERS,
            'Specifications_Proposed' => ProductImportMapper::SPEC_HEADERS,
        ];
        foreach ($sheets as $index => $headers) {
            $sheet = $book->getSheetCount() === 1 && $book->getActiveSheet()->getCell('A1')->getValue() === null
                ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($index)->setRightToLeft(true);
            foreach ($headers as $column => $header) {
                $sheet->setCellValueExplicit([$column + 1, 1], $header, DataType::TYPE_STRING);
                $letter = Coordinate::stringFromColumnIndex($column + 1);
                $sheet->getColumnDimension($letter)->setWidth(in_array($header, ['title', 'content', 'excerpt'], true) ? 42 : 24);
                if (in_array($header, ['sku', 'slug', 'category_slug', 'key', 'unit'], true)) {
                    $sheet->getStyle($letter.':'.$letter)->getNumberFormat()->setFormatCode('@');
                }
            }
            $last = $sheet->getHighestDataColumn();
            $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D4ED8');
            $sheet->getRowDimension(1)->setRowHeight(28);
            $sheet->freezePane('A2')->setAutoFilter("A1:{$last}1");
        }
        $readme = $book->createSheet()->setTitle('Readme')->setRightToLeft(true);
        $notes = [
            'قالب استاندارد درون‌ریزی محصولات King CMS',
            'فقط Import_Products محصول می‌سازد. دسته‌ها و مشخصات از شیت‌های هم‌نام خوانده می‌شوند.',
            'محصولات: sku، title، slug و price الزامی‌اند. ترتیب ستون‌ها آزاد است؛ Headerها را تغییر ندهید.',
            'SKU و نامک را به‌صورت متن وارد کنید. نامک فارسی حفظ می‌شود. فرمول‌ها پذیرفته نیستند.',
            'price و sale_price به تومان، غیرمنفی و با حداکثر دو رقم اعشار هستند. تخفیف باید کمتر از قیمت باشد.',
            'status: draft یا published. stock_status: out_of_stock یا in_stock. پرچم‌ها: 0 یا 1.',
            'پیش‌فرض امن: draft، out_of_stock، has_stock=0، robots_index=0 و price باید صریحاً وارد شود.',
            'Categories: slug و name. دسته جدید فقط پس از تصمیم و تأیید کاربر ایجاد می‌شود.',
            'Specifications_Proposed: sku، key و value ستون‌های الزامی‌اند. مقدار می‌تواند خالی باشد.',
            'مشخصات فقط برای SKUهای موجود در Import_Products بررسی و وارد می‌شوند.',
            'حالت پیش‌فرض فقط ایجاد است. به‌روزرسانی نیازمند انتخاب حالت و تأیید ردیف‌هاست.',
            'مقدار خالی اختیاری اطلاعات قبلی را پاک نمی‌کند. تصاویر، اسناد و ارتباط‌های قبلی حفظ می‌شوند.',
        ];
        foreach ($notes as $row => $note) {
            $readme->setCellValueExplicit('A'.($row + 1), $note, DataType::TYPE_STRING);
        }
        $readme->getColumnDimension('A')->setWidth(110);
        $readme->getStyle('A1:A12')->getAlignment()->setWrapText(true);
        $readme->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        for ($row = 1; $row <= 12; $row++) {
            $readme->getRowDimension($row)->setRowHeight($row === 1 ? 36 : 42);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $book = $this->workbook();
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'king-products-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }
}
