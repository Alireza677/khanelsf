<?php

namespace App\Services;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\Models\Form;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

final class ProductPriceInquiry
{
    public function __construct(private readonly SettingsService $settings) {}

    public function form(): ?Form
    {
        return Form::query()->published()->where('type', '!=', 'calculator')
            ->find((int) $this->settings->get('shop_price_inquiry_form_id'));
    }

    public function url(Product $product): ?string
    {
        if ($form = $this->form()) {
            $action = app(ActionResolver::class)->resolve(
                new ActionDestination('form', referenceId: $form->id, display: 'page'), new ResolutionContext,
            );
            if ($action->shouldRender()) {
                return URL::temporarySignedRoute('forms.show', now()->addMinutes(30), [
                    'slug' => $form->slug, '_price_product' => $product->id,
                    '_form_instance' => 'price-product-'.$product->id,
                ]);
            }
        }

        return $this->contactUrl();
    }

    public function contactUrl(): ?string
    {
        $action = app(ActionResolver::class)->resolve(ActionDestination::fromArray([
            'type' => 'phone', 'value' => $this->settings->contactPhone() ?: $this->settings->contactMobile(),
        ]), new ResolutionContext);

        return $action->shouldRender() ? $action->url : null;
    }

    public function capture(Request $request, Form $form, FormAttributionSession $attribution): void
    {
        abort_unless($request->hasValidSignature() && app(ModuleService::class)->shopEnabled()
            && $this->form()?->id === $form->id, 403);
        $product = Product::query()->published()->findOrFail($request->integer('_price_product'));
        $instance = 'price-product-'.$product->id;
        abort_unless($request->input('_form_instance') === $instance, 403);
        $request->session()->put($this->key($form, $instance), [
            'product_id' => $product->id, 'sku' => $product->sku, 'title' => $product->title,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);
        $attribution->put($request, $form, ['page_url' => route('shop.show', $product->slug)], $instance);
    }

    public function context(Request $request, Form $form, ?string $instance, bool $required = true): ?array
    {
        $context = $request->session()->get($this->key($form, $instance));
        if (! is_array($context) || ($context['expires_at'] ?? 0) <= now()->timestamp) {
            if ($required && str_starts_with($instance ?? '', 'price-product-')) {
                throw ValidationException::withMessages(['inquiry' => 'مهلت استعلام محصول به پایان رسیده است؛ از صفحه محصول دوباره وارد فرم شوید.']);
            }

            return null;
        }
        unset($context['expires_at']);

        return $context;
    }

    public function forget(Request $request, Form $form, ?string $instance): void
    {
        $request->session()->forget($this->key($form, $instance));
    }

    private function key(Form $form, ?string $instance): string
    {
        return 'shop_price_inquiries.'.$form->id.'.'.($instance ?? 'default');
    }
}
