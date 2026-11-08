<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImportRow extends Model
{
    public const ACTION_LABELS = [
        'new' => 'ایجاد', 'update' => 'به‌روزرسانی', 'unchanged' => 'بدون تغییر',
        'skip' => 'رد محصول موجود', 'review' => 'نیازمند تصمیم', 'duplicate' => 'تکراری', 'error' => 'خطادار',
    ];

    public const RESULT_LABELS = [
        'created' => 'ایجاد شد', 'updated' => 'به‌روزرسانی شد', 'unchanged' => 'بدون تغییر',
        'skipped' => 'بدون تغییر رد شد', 'rejected' => 'رد شد', 'error' => 'خطا',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'specifications' => 'array', 'source_errors' => 'array',
            'source_warnings' => 'array', 'errors' => 'array', 'warnings' => 'array', 'decision' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }
}
