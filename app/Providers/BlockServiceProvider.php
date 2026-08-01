<?php

namespace App\Providers;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\CTA\CTABlock;
use App\CMS\Blocks\FeatureGrid\FeatureGridBlock;
use App\CMS\Blocks\FeatureGrid\FeatureGridRuntime;
use App\CMS\Blocks\Form\FormBlock;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Hero\HeroMediaResolver;
use App\CMS\Blocks\Product\ProductDocumentsBlock;
use App\CMS\Blocks\Product\ProductGalleryBlock;
use App\CMS\Blocks\Product\ProductHeaderBlock;
use App\CMS\Blocks\Product\ProductOverviewBlock;
use App\CMS\Blocks\Product\ProductRelatedBlock;
use App\CMS\Blocks\Product\ProductSpecificationsBlock;
use App\CMS\Blocks\Project\ProjectGalleryBlock;
use App\CMS\Blocks\Project\ProjectHeaderBlock;
use App\CMS\Blocks\Project\ProjectMetricsBlock;
use App\CMS\Blocks\Project\ProjectOverviewBlock;
use App\CMS\Blocks\Project\ProjectServicesBlock;
use App\CMS\Blocks\Project\ProjectStoryBlock;
use App\CMS\Blocks\Project\RelatedProjectsBlock;
use App\CMS\Blocks\Service\RelatedServicesBlock;
use App\CMS\Blocks\Service\ServiceBenefitsBlock;
use App\CMS\Blocks\Service\ServiceDeliverablesBlock;
use App\CMS\Blocks\Service\ServiceGalleryBlock;
use App\CMS\Blocks\Service\ServiceHeaderBlock;
use App\CMS\Blocks\Service\ServiceOverviewBlock;
use App\CMS\Blocks\Service\ServiceProcessBlock;
use App\CMS\Blocks\Service\ServiceProjectsBlock;
use App\CMS\Blocks\SiteHeader\SiteHeaderBlock;
use App\CMS\Blocks\SiteHeader\SiteHeaderRuntime;
use App\CMS\Templates\SiteHeaderTemplateResolver;
use App\Models\Template;
use App\Services\TemplateService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as IlluminateView;

class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(HeroMediaResolver::class);

        $this->app->singleton(BlockRegistry::class, fn ($app): BlockRegistry => new BlockRegistry(
            $app,
            [
                'hero' => HeroBlock::class,
                'cta' => CTABlock::class,
                'form' => FormBlock::class,
                'feature_grid' => FeatureGridBlock::class,
                'site_header' => SiteHeaderBlock::class,
                'project_header' => ProjectHeaderBlock::class,
                'project_overview' => ProjectOverviewBlock::class,
                'project_metrics' => ProjectMetricsBlock::class,
                'project_services' => ProjectServicesBlock::class,
                'project_gallery' => ProjectGalleryBlock::class,
                'project_story' => ProjectStoryBlock::class,
                'related_projects' => RelatedProjectsBlock::class,
                'product_header' => ProductHeaderBlock::class,
                'product_overview' => ProductOverviewBlock::class,
                'product_specifications' => ProductSpecificationsBlock::class,
                'product_gallery' => ProductGalleryBlock::class,
                'product_documents' => ProductDocumentsBlock::class,
                'product_related' => ProductRelatedBlock::class,
                'service_header' => ServiceHeaderBlock::class,
                'service_overview' => ServiceOverviewBlock::class,
                'service_benefits' => ServiceBenefitsBlock::class,
                'service_process' => ServiceProcessBlock::class,
                'service_deliverables' => ServiceDeliverablesBlock::class,
                'service_projects' => ServiceProjectsBlock::class,
                'service_gallery' => ServiceGalleryBlock::class,
                'related_services' => RelatedServicesBlock::class,
            ],
        ));
    }

    public function boot(
        FeatureGridRuntime $featureGrids,
        SiteHeaderRuntime $siteHeaders,
        SiteHeaderTemplateResolver $headerTemplates,
        TemplateService $templates,
    ): void {
        View::composer(
            'partials.blocks.feature_grid',
            function (IlluminateView $view) use ($featureGrids): void {
                $viewData = $view->getData();
                $data = is_array($viewData['data'] ?? null)
                    ? $viewData['data']
                    : [];
                $context = is_array($viewData['context'] ?? null)
                    ? $viewData['context']
                    : [];
                $context['page_url'] ??= request()->getRequestUri();

                $view->with('grid', $featureGrids->prepare(
                    $data,
                    $context,
                    ! empty($viewData['isPreview']) || ! empty($context['preview']),
                ));
            },
        );

        View::composer(
            'partials.blocks.site-header-industrial',
            function (IlluminateView $view) use ($siteHeaders): void {
                $viewData = $view->getData();
                $data = is_array($viewData['data'] ?? null)
                    ? $viewData['data']
                    : [];
                $context = is_array($viewData['context'] ?? null)
                    ? $viewData['context']
                    : [];
                $context['page_url'] ??= request()->getRequestUri();

                $view->with('header', $siteHeaders->prepare(
                    $data,
                    $context,
                    ! empty($viewData['isPreview']) || ! empty($context['preview']),
                ));
            },
        );

        View::composer(
            'layouts.app',
            function (IlluminateView $view) use ($headerTemplates, $templates): void {
                $viewData = $view->getData();
                $previewTemplate = $viewData['template'] ?? null;
                $previewingHeader = ! empty($viewData['isPreview'])
                    && $previewTemplate instanceof Template
                    && $previewTemplate->type === 'site_header';

                $view->with([
                    'siteHeaderTemplate' => $previewingHeader
                        ? $previewTemplate
                        : $headerTemplates->selected(),
                    'siteFooterTemplate' => $templates->findTemplateFor('site_footer'),
                ]);
            },
        );
    }
}
