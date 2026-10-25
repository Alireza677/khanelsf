<?php

namespace Tests\Browser;

use Filament\Support\Colors\Color;
use HeadlessChromium\BrowserFactory;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Isolated browser harness: real Blade/Alpine/Filament assets, mocked browse HTTP, no app data. */
class DestinationPickerBrowserTest extends TestCase
{
    public function test_rich_text_uses_the_same_browser_and_applies_the_current_url(): void
    {
        $binary = getenv('CHROME_PATH') ?: 'C:\Program Files\Google\Chrome\Application\chrome.exe';
        if (! is_file($binary)) {
            $this->markTestSkipped('Set CHROME_PATH to run the browser harness.');
        }
        $browser = (new BrowserFactory($binary))->createBrowser(['headless' => true]);
        try {
            $page = $browser->createPage();
            $source = file_get_contents(resource_path('views/filament/theme.blade.php'));
            $tail = substr($source, strpos($source, '<template id="king-rich-text-destination-browser">'));
            $tail = Blade::render($tail);
            $module = 'data:text/javascript;base64,'.base64_encode(file_get_contents(resource_path('js/filament/destination-picker.js')));
            $tail = preg_replace('/x-load-src="[^"]*"/', 'x-load-src="'.$module.'"', $tail);
            $richModule = 'data:text/javascript;base64,'.base64_encode(file_get_contents(base_path('vendor/filament/forms/dist/components/rich-editor.js')));
            $setup = <<<'JS'
window.livewireScriptConfig = {};
window.browserErrors = [];
window.addEventListener('error', e => window.browserErrors.push(e.message));
window.addEventListener('unhandledrejection', e => window.browserErrors.push(String(e.reason)));
const nativeFetch = window.fetch.bind(window);
window.fetch = async (url, options) => {
    if (!String(url).includes('/internal-links/search')) return nativeFetch(url, options);
    return { ok: true, json: async () => ({ items: [{ reference_id: 42, title: 'Current page', url: 'https://cms.test/current-slug', subtitle: '/current-slug', selectable: true, status: 'published' }], page: 1, last_page: 1, total: 1 }) };
};
JS;
            $page->setHtml('<!doctype html><html><head><meta charset="utf-8"><style>.trix-dialog:not([data-trix-active]){display:none}</style></head><body>'
                .'<input type="hidden" id="trix-value" value="&lt;p&gt;Text to link&lt;/p&gt;"><trix-editor input="trix-value"></trix-editor>'
                .$tail.'<script>'.$setup.'</script><script>'.file_get_contents(public_path('js/filament/support/support.js')).'</script>'
                .'<script>'.file_get_contents(base_path('vendor/livewire/livewire/dist/livewire.js')).'</script>'
                .'<script>Livewire.start(); import('.json_encode($richModule).')</script></body></html>');
            $page->evaluate('new Promise(resolve => setTimeout(resolve, 600))')->getReturnValue();
            $page->evaluate('document.querySelector("trix-editor").focus(); document.querySelector("trix-editor").editor.setSelectedRange([0, 4]); document.querySelector("[data-trix-attribute=href]").click()')->getReturnValue();
            $this->assertSame(1, $page->evaluate('document.querySelectorAll("trix-toolbar [x-ref=trigger]").length')->getReturnValue());
            $page->evaluate('document.querySelector("trix-toolbar [x-ref=trigger]").click()')->getReturnValue();
            $page->evaluate('new Promise(resolve => setTimeout(resolve, 100))')->getReturnValue();
            $this->assertTrue($page->evaluate('document.querySelector("trix-toolbar dialog").open')->getReturnValue());
            $page->evaluate('document.querySelector("trix-toolbar dialog button[aria-pressed]").click()')->getReturnValue();
            $this->assertSame('https://cms.test/current-slug', $page->evaluate('document.querySelector("trix-editor a").getAttribute("href")')->getReturnValue());
            $this->assertSame('Text', $page->evaluate('document.querySelector("trix-editor a").textContent')->getReturnValue());
            $this->assertFalse($page->evaluate('document.querySelector("trix-toolbar dialog").open')->getReturnValue());
            $this->assertTrue($page->evaluate('document.activeElement === document.querySelector("trix-editor")')->getReturnValue());
            $this->assertSame([], $page->evaluate('window.browserErrors')->getReturnValue());
        } finally {
            $browser->close();
        }
    }

    public static function viewports(): array
    {
        return ['mobile' => [[390, 844]], 'desktop' => [[1440, 900]]];
    }

    #[DataProvider('viewports')]
    public function test_native_dialog_inside_an_inspector_trap_supports_mobile_focus_escape_and_selection(array $viewport): void
    {
        $binary = getenv('CHROME_PATH') ?: 'C:\Program Files\Google\Chrome\Application\chrome.exe';
        if (! is_file($binary)) {
            $this->markTestSkipped('Set CHROME_PATH to run the browser harness.');
        }
        $browser = (new BrowserFactory($binary))->createBrowser(['headless' => true, 'windowSize' => $viewport]);
        try {
            $page = $browser->createPage();
            $module = 'data:text/javascript;base64,'.base64_encode(file_get_contents(resource_path('js/filament/destination-picker.js')));
            $field = view('filament.forms.components.destination-browser', [
                'configuration' => '{ state: 12, label: "Current", endpoint: "https://cms.test/browse" }',
            ])->render();
            $field = preg_replace('/x-load-src="[^"]*"/', 'x-load-src="'.$module.'"', $field);
            $livewire = file_get_contents(base_path('vendor/livewire/livewire/dist/livewire.js'));
            $support = file_get_contents(public_path('js/filament/support/support.js'));
            preg_match('~<style>\s+dialog\[data-destination-browser\].*?</style>~s', file_get_contents(resource_path('views/filament/theme.blade.php')), $dialogStyles);
            $setup = <<<'JS'
window.livewireScriptConfig = {};
window.browserErrors = [];
window.addEventListener('error', e => window.browserErrors.push(e.message));
window.addEventListener('unhandledrejection', e => window.browserErrors.push(String(e.reason)));
window.requests = [];
const nativeFetch = window.fetch.bind(window);
window.fetch = async (url, options) => {
    if (!String(url).startsWith('https://cms.test/browse')) return nativeFetch(url, options);
    window.requests.push(String(url));
    return { ok: true, json: async () => ({ label: 'Selected', items: Array.from({ length: 20 }, (_, index) => ({
        reference_id: index === 1 ? 12 : 42 + index,
        title: index === 0 ? '<b>Safe text</b>' : `عنوان مقصد نمونه برای بررسی چیدمان شماره ${index}`,
        subtitle: '/hidden-destination-slug', details: 'Hidden metadata',
        selectable: index !== 19, status: index !== 19 ? 'منتشرشده' : 'منتشرنشده — غیرقابل انتخاب',
    })), page: 1, last_page: 1, total: 20 }) };
};
JS;
            $palette = '';
            foreach (['gray' => Color::Gray, 'primary' => Color::Blue, 'success' => Color::Green] as $name => $shades) {
                foreach ($shades as $shade => $rgb) {
                    $palette .= "--{$name}-{$shade}:{$rgb};";
                }
            }
            $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<style>'.file_get_contents(public_path('css/filament/filament/app.css')).'</style>'
                .($dialogStyles[0] ?? '')
                .'<style>:root{'.$palette.'}</style>'
                .'<style>body{margin:0}dialog{box-sizing:border-box}dialog::backdrop{background:#0008}</style></head><body>'
                .'<div x-data="{ active: true }" x-trap="active" style="overflow:hidden;height:600px;width:100%;transform:translateZ(0)">'
                .$field.'</div><script>'.$setup.'</script><script>'.$support.'</script><script>'.$livewire.'</script><script>Livewire.start()</script></body></html>';
            $page->setHtml($html);
            $page->evaluate('new Promise(resolve => setTimeout(resolve, 500))')->getReturnValue();
            $page->evaluate('document.querySelector("[x-ref=trigger]").click()')->getReturnValue();
            $page->evaluate('new Promise(resolve => setTimeout(resolve, 100))')->getReturnValue();
            $this->assertTrue($page->evaluate('document.querySelector("dialog").open')->getReturnValue());
            $this->assertTrue($page->evaluate('document.querySelector("dialog").matches(":modal")')->getReturnValue());
            $this->assertSame('hidden', $page->evaluate('getComputedStyle(document.documentElement).overflow')->getReturnValue());
            $this->assertSame('search', $page->evaluate('document.activeElement.getAttribute("x-ref")')->getReturnValue());
            $this->assertTrue($page->evaluate('document.querySelector("dialog").getBoundingClientRect().width <= innerWidth')->getReturnValue());
            $this->assertSame('', $page->evaluate('new URL(window.requests.find(url => new URL(url).searchParams.has("q"))).searchParams.get("q")')->getReturnValue());
            $this->assertSame(0, $page->evaluate('document.querySelectorAll("dialog b").length')->getReturnValue());
            $this->assertFalse($page->evaluate('document.querySelector("dialog").innerText.includes("/hidden-destination-slug") || document.querySelector("dialog").innerText.includes("Hidden metadata") || document.querySelector("dialog").innerText.includes("شناسه")')->getReturnValue());
            $this->assertTrue($page->evaluate('(() => { const row = document.querySelector(".destination-picker__row"); return row.getBoundingClientRect().height <= 46 && row.querySelector(".destination-picker__title").getBoundingClientRect().right > row.querySelector(".destination-picker__badge").getBoundingClientRect().right; })()')->getReturnValue());
            $this->assertTrue($page->evaluate('(() => { const list = document.querySelector(".destination-picker__list"); return list.scrollHeight > list.clientHeight && getComputedStyle(list).overflowY === "auto"; })()')->getReturnValue());
            $this->assertTrue($page->evaluate('getComputedStyle(document.querySelector(".destination-picker__row[aria-pressed=true]")).backgroundColor !== getComputedStyle(document.querySelector(".destination-picker__row[aria-pressed=false]")).backgroundColor')->getReturnValue());
            $this->assertTrue($page->evaluate('document.querySelector("dialog").scrollWidth <= document.querySelector("dialog").clientWidth')->getReturnValue());
            $page->screenshot()->saveToFile(storage_path('framework/testing/destination-ui-'.$viewport[0].'.png'));
            for ($tab = 0; $tab < 6; $tab++) {
                $page->keyboard()->typeRawKey('Tab');
                $this->assertTrue($page->evaluate('document.querySelector("dialog").contains(document.activeElement)')->getReturnValue());
            }
            $page->keyboard()->typeRawKey('Escape');
            $this->assertFalse($page->evaluate('document.querySelector("dialog").open')->getReturnValue());
            $this->assertSame('trigger', $page->evaluate('document.activeElement.getAttribute("x-ref")')->getReturnValue());
            $page->evaluate('document.querySelector("[x-ref=trigger]").click()')->getReturnValue();
            $page->evaluate('new Promise(resolve => setTimeout(resolve, 100))')->getReturnValue();
            $page->evaluate('document.querySelector("dialog button[aria-pressed]").click()')->getReturnValue();
            $this->assertFalse($page->evaluate('document.querySelector("dialog").open')->getReturnValue());
            $this->assertSame(42, $page->evaluate('Alpine.$data(document.querySelector("[x-load]")).state')->getReturnValue());
            $this->assertSame([], $page->evaluate('window.browserErrors')->getReturnValue());
        } finally {
            $browser->close();
        }
    }
}
