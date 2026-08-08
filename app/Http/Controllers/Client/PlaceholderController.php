<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PlaceholderController extends Controller
{
    public function reports(): View
    {
        return $this->render('گزارش‌ها', 'گزارشی ثبت نشده است.', 'reports');
    }

    public function invoices(): View
    {
        return $this->render('فاکتورها', 'فاکتوری موجود نیست.', 'invoices');
    }

    public function files(): View
    {
        return $this->render('فایل‌ها', 'فایلی برای شما بارگذاری نشده است.', 'files');
    }

    private function render(string $title, string $message, string $icon): View
    {
        return view('client.placeholder', compact('title', 'message', 'icon'));
    }
}
