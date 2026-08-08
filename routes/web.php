<?php

use App\Http\Controllers\Admin\ContactMessageExportController;
use App\Http\Controllers\Admin\InternalLinkSearchController;
use App\Http\Controllers\Admin\OrderExportController;
use App\Http\Controllers\Admin\OrderPrintController;
use App\Http\Controllers\Admin\PreviewController;
use App\Http\Controllers\Admin\RedirectExportController;
use App\Http\Controllers\CalculatorSubmissionReportController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Client\AuthenticatedSessionController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\PlaceholderController as ClientPlaceholderController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\ProjectController as ClientProjectController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ZarinpalCallbackController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/health', HealthController::class)->name('health');

Route::middleware('guest:client')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('client.login.store');
});

Route::middleware(['auth:client', 'client', 'client.context'])->group(function (): void {
    Route::get('/dashboard', ClientDashboardController::class)->name('client.dashboard');
    Route::get('/dashboard/projects', [ClientProjectController::class, 'index'])->name('client.projects.index');
    Route::get('/dashboard/projects/{project}', [ClientProjectController::class, 'show'])
        ->whereNumber('project')
        ->name('client.projects.show');
    Route::get('/dashboard/reports', [ClientPlaceholderController::class, 'reports'])->name('client.placeholder.reports');
    Route::get('/dashboard/invoices', [ClientPlaceholderController::class, 'invoices'])->name('client.placeholder.invoices');
    Route::get('/dashboard/files', [ClientPlaceholderController::class, 'files'])->name('client.placeholder.files');
    Route::get('/profile', [ClientProfileController::class, 'edit'])->name('client.profile.edit');
    Route::patch('/profile', [ClientProfileController::class, 'update'])->name('client.profile.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('client.logout');
});

Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
Route::get('/blog/search', [PostController::class, 'search'])->name('blog.search');
Route::get('/blog/category/{slug}', [PostController::class, 'category'])->name('blog.category');
Route::get('/blog/{slug}', [PostController::class, 'show'])->name('blog.show');

Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::post('/forms/{slug}/context', [FormController::class, 'capture'])->name('forms.context');
Route::post('/forms/{slug}/modal', [FormController::class, 'modal'])->name('forms.modal');
Route::get('/forms/{slug}', [FormController::class, 'show'])->name('forms.show');
Route::post('/forms/{slug}/submit', [FormController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('forms.submit');
Route::get('/forms/submissions/{submission}/calculator-report', CalculatorSubmissionReportController::class)
    ->middleware('signed')
    ->name('forms.submissions.calculator-report');

// Reserved public project URLs; keep these before the catch-all page route.
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/category/{slug}', [ProjectController::class, 'category'])->name('projects.category');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');

// Reserved public service URLs; keep these before the catch-all page route.
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{slug}', [ServiceController::class, 'show'])->name('services.show');

// Reserved public gallery URLs; keep these before the catch-all page route.
Route::get('/galleries', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/galleries/category/{slug}', [GalleryController::class, 'category'])->name('galleries.category');
Route::get('/galleries/{slug}', [GalleryController::class, 'show'])->name('galleries.show');

// Reserved shop and checkout URLs; keep these before the catch-all page route.
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/category/{slug}', [ShopController::class, 'category'])->name('shop.category');
Route::post('/shop/favorites/{product}', [ShopController::class, 'toggleFavorite'])->name('shop.favorites.toggle');
Route::get('/shop/{slug}', [ShopController::class, 'show'])->name('shop.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
Route::match(['patch', 'post'], '/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::match(['delete', 'post'], '/cart/remove', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/thank-you/{order}', [CheckoutController::class, 'thankYou'])->name('checkout.thank-you');
Route::match(['get', 'post'], '/payments/zarinpal/callback', ZarinpalCallbackController::class)
    ->withoutMiddleware(VerifyCsrfToken::class)
    ->name('payments.zarinpal.callback');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/internal-links/search', InternalLinkSearchController::class)->name('internal-links.search');
    });

Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.orders.')
    ->group(function (): void {
        Route::get('/orders-export.csv', OrderExportController::class)->name('export');
        Route::get('/orders/{order}/print', OrderPrintController::class)->name('print');
    });

Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.exports.')
    ->group(function (): void {
        Route::get('/contact-messages-export.csv', ContactMessageExportController::class)->name('contact-messages');
        Route::get('/redirects-export.csv', RedirectExportController::class)->name('redirects');
    });

Route::middleware('auth')
    ->prefix('admin/preview')
    ->name('admin.preview.')
    ->group(function (): void {
        Route::get('/pages/{page}', [PreviewController::class, 'page'])->name('pages.show');
        Route::get('/posts/{post}', [PreviewController::class, 'post'])->name('posts.show');
        Route::get('/projects/{project}', [PreviewController::class, 'project'])->name('projects.show');
        Route::get('/galleries/{gallery}', [PreviewController::class, 'gallery'])->name('galleries.show');
        Route::get('/products/{product}', [PreviewController::class, 'product'])->name('products.show');
        Route::get('/templates/{template}', [PreviewController::class, 'template'])->name('templates.show');
    });

// TEMP DEBUG - remove after production save issue is fixed.
Route::middleware('auth')->get('/admin/debug/log-test', function (Request $request) {
    abort_unless(Auth::user()?->is_admin, 403);

    Log::debug('TEMP DEBUG - Server log test', [
        'user_id' => Auth::id(),
        'route_name' => $request->route()?->getName(),
        'url' => $request->fullUrl(),
        'method' => $request->method(),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    return response()->json([
        'ok' => true,
        'message' => 'Server log test written.',
    ]);
})->name('admin.debug.log-test');

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
