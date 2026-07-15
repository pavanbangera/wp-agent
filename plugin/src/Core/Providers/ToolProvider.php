<?php

declare(strict_types=1);

namespace WpAgent\Core\Providers;

use WpAgent\Core\ServiceProvider;
use WpAgent\MCP\Registry\ToolRegistry;
use WpAgent\Repositories\PostRepository;
use WpAgent\Repositories\MediaRepository;
use WpAgent\Services\PageService;
use WpAgent\Services\PostService;
use WpAgent\Services\MediaService;
use WpAgent\Services\MenuService;
use WpAgent\Services\PluginService;
use WpAgent\Services\ThemeService;
use WpAgent\Services\ElementorService;
use WpAgent\Services\GutenbergService;
use WpAgent\Services\WooCommerceService;
use WpAgent\Services\SeoService;
use WpAgent\Services\PerformanceService;
use WpAgent\Services\SecurityService;
use WpAgent\Services\AiPlannerService;
use WpAgent\Services\CodeExecutionService;

// Site tools.
use WpAgent\Tools\Site\GetOptionTool;
use WpAgent\Tools\Site\GetSiteInfoTool;
use WpAgent\Tools\Site\SetOptionTool;

// Page tools.
use WpAgent\Tools\Pages\CreatePageTool;
use WpAgent\Tools\Pages\DeletePageTool;
use WpAgent\Tools\Pages\DuplicatePageTool;
use WpAgent\Tools\Pages\GetPageTool;
use WpAgent\Tools\Pages\GetRevisionsTool;
use WpAgent\Tools\Pages\ListPagesTool;
use WpAgent\Tools\Pages\PublishPageTool;
use WpAgent\Tools\Pages\RestoreRevisionTool;
use WpAgent\Tools\Pages\SchedulePageTool;
use WpAgent\Tools\Pages\UpdatePageTool;

// Post tools.
use WpAgent\Tools\Posts\CreatePostTool;
use WpAgent\Tools\Posts\DeletePostTool;
use WpAgent\Tools\Posts\GetPostTool;
use WpAgent\Tools\Posts\ListPostsTool;
use WpAgent\Tools\Posts\ManageCategoriesTool;
use WpAgent\Tools\Posts\ManageTagsTool;
use WpAgent\Tools\Posts\QueryPostTool;
use WpAgent\Tools\Posts\SetFeaturedImageTool;
use WpAgent\Tools\Posts\UpdatePostTool;

// Media tools.
use WpAgent\Tools\Media\UploadMediaTool;
use WpAgent\Tools\Media\ReplaceMediaTool;
use WpAgent\Tools\Media\CompressMediaTool;
use WpAgent\Tools\Media\GenerateAltTextTool;
use WpAgent\Tools\Media\ConvertWebpTool;
use WpAgent\Tools\Media\RenameMediaTool;
use WpAgent\Tools\Media\DetectDuplicatesTool;

// Menu tools.
use WpAgent\Tools\Menus\CreateMenuTool;
use WpAgent\Tools\Menus\UpdateMenuTool;
use WpAgent\Tools\Menus\DeleteMenuTool;
use WpAgent\Tools\Menus\ListMenusTool;

// Plugin tools.
use WpAgent\Tools\Plugins\SearchPluginsTool;
use WpAgent\Tools\Plugins\InstallPluginTool;
use WpAgent\Tools\Plugins\ActivatePluginTool;
use WpAgent\Tools\Plugins\DeactivatePluginTool;
use WpAgent\Tools\Plugins\DeletePluginTool;
use WpAgent\Tools\Plugins\UpdatePluginTool;
use WpAgent\Tools\Plugins\RollbackPluginTool;
use WpAgent\Tools\Plugins\ListPluginsTool;
use WpAgent\Tools\Plugins\BulkInstallPluginsTool;
use WpAgent\Tools\Plugins\GetPluginStatusTool;

// Theme tools.
use WpAgent\Tools\Themes\ActivateThemeTool;
use WpAgent\Tools\Themes\CreateChildThemeTool;
use WpAgent\Tools\Themes\ExportDemoTool;
use WpAgent\Tools\Themes\GetActiveThemeTool;
use WpAgent\Tools\Themes\ImportDemoTool;
use WpAgent\Tools\Themes\InstallThemeTool;
use WpAgent\Tools\Themes\SearchThemesTool;

// Elementor tools.
use WpAgent\Tools\Elementor\BuildFooterTool;
use WpAgent\Tools\Elementor\BuildHeaderTool;
use WpAgent\Tools\Elementor\BuildPopupTool;
use WpAgent\Tools\Elementor\CreateContainerTool;
use WpAgent\Tools\Elementor\CreateElementorPageTool;
use WpAgent\Tools\Elementor\DuplicateTemplateTool;
use WpAgent\Tools\Elementor\ExportTemplateTool;
use WpAgent\Tools\Elementor\GetTemplateTool;
use WpAgent\Tools\Elementor\ImportTemplateTool;
use WpAgent\Tools\Elementor\InsertWidgetTool;
use WpAgent\Tools\Elementor\SetGlobalColorsTool;
use WpAgent\Tools\Elementor\SetGlobalFontsTool;
use WpAgent\Tools\Elementor\SetPageLayoutTool;
use WpAgent\Tools\Elementor\UpdateTemplateTool;

// Gutenberg tools.
use WpAgent\Tools\Gutenberg\CreateBlockPageTool;
use WpAgent\Tools\Gutenberg\InsertBlockTool;
use WpAgent\Tools\Gutenberg\CreatePatternTool;
use WpAgent\Tools\Gutenberg\CreateTemplateTool;
use WpAgent\Tools\Gutenberg\GenerateBlockMarkupTool;

// WooCommerce tools.
use WpAgent\Tools\WooCommerce\CreateProductTool;
use WpAgent\Tools\WooCommerce\UpdateProductTool;
use WpAgent\Tools\WooCommerce\ListProductsTool;
use WpAgent\Tools\WooCommerce\ListOrdersTool;
use WpAgent\Tools\WooCommerce\CreateCouponTool;
use WpAgent\Tools\WooCommerce\ManageInventoryTool;
use WpAgent\Tools\WooCommerce\ConfigureShippingTool;
use WpAgent\Tools\WooCommerce\ConfigureTaxTool;
use WpAgent\Tools\WooCommerce\GetAnalyticsTool;

// SEO tools.
use WpAgent\Tools\SEO\SetSeoMetaTool;
use WpAgent\Tools\SEO\SetOpenGraphTool;
use WpAgent\Tools\SEO\SetSchemaMarkupTool;
use WpAgent\Tools\SEO\GenerateSitemapTool;
use WpAgent\Tools\SEO\RunSeoAuditTool;

// Performance tools.
use WpAgent\Tools\Performance\ClearCacheTool;
use WpAgent\Tools\Performance\FlushRewritesTool;
use WpAgent\Tools\Performance\GetCwvTool;
use WpAgent\Tools\Performance\OptimizeImagesTool;
use WpAgent\Tools\Performance\RunLighthouseTool;

// Security tools.
use WpAgent\Tools\Security\CreateBackupTool;
use WpAgent\Tools\Security\RestoreBackupTool;
use WpAgent\Tools\Security\RunMalwareScanTool;
use WpAgent\Tools\Security\AuditPermissionsTool;
use WpAgent\Tools\Security\ScanPluginsTool;
use WpAgent\Tools\Security\CheckHeadersTool;

// AI Planner tools.
use WpAgent\Tools\AI\ExecuteCodeTool;
use WpAgent\Tools\AI\FileReadTool;
use WpAgent\Tools\AI\FileWriteTool;
use WpAgent\Tools\AI\PlanGoalTool;
use WpAgent\Tools\AI\ScaffoldPluginTool;
use WpAgent\Tools\AI\ScaffoldThemeTool;

/**
 * Registers all built-in WP Agent tools into the DI container and ToolRegistry.
 *
 * Organization: tools are registered in namespace groups. Add new namespaces
 * here as modules are built.
 *
 * @package WpAgent\Core\Providers
 * @since   0.1.0
 */
final class ToolProvider extends ServiceProvider
{
    public function register(): void
    {
        // -----------------------------------------------------------------------
        // Shared repositories (singletons — one instance per request).
        // -----------------------------------------------------------------------

        $this->container->singleton(
            PostRepository::class,
            fn (): PostRepository => new PostRepository()
        );

        $this->container->singleton(
            MediaRepository::class,
            fn (): MediaRepository => new MediaRepository()
        );

        // -----------------------------------------------------------------------
        // Services (singletons — inject repository).
        // -----------------------------------------------------------------------

        $this->container->singleton(
            PageService::class,
            fn ($c): PageService => new PageService($c->get(PostRepository::class))
        );

        $this->container->singleton(
            PostService::class,
            fn ($c): PostService => new PostService($c->get(PostRepository::class))
        );

        $this->container->singleton(
            MediaService::class,
            fn ($c): MediaService => new MediaService($c->get(MediaRepository::class))
        );

        $this->container->singleton(
            MenuService::class,
            fn (): MenuService => new MenuService()
        );

        $this->container->singleton(
            PluginService::class,
            fn (): PluginService => new PluginService()
        );

        $this->container->singleton(
            ThemeService::class,
            fn (): ThemeService => new ThemeService()
        );

        $this->container->singleton(
            ElementorService::class,
            fn (): ElementorService => new ElementorService()
        );

        $this->container->singleton(
            GutenbergService::class,
            fn (): GutenbergService => new GutenbergService()
        );

        $this->container->singleton(
            WooCommerceService::class,
            fn (): WooCommerceService => new WooCommerceService()
        );

        $this->container->singleton(
            SeoService::class,
            fn (): SeoService => new SeoService()
        );

        $this->container->singleton(
            PerformanceService::class,
            fn (): PerformanceService => new PerformanceService()
        );

        $this->container->singleton(
            SecurityService::class,
            fn (): SecurityService => new SecurityService()
        );

        $this->container->singleton(
            AiPlannerService::class,
            fn (): AiPlannerService => new AiPlannerService()
        );

        $this->container->singleton(
            CodeExecutionService::class,
            fn (): CodeExecutionService => new CodeExecutionService()
        );

        // -----------------------------------------------------------------------
        // Site tools.
        // -----------------------------------------------------------------------

        $this->container->bind(GetSiteInfoTool::class, fn (): GetSiteInfoTool => new GetSiteInfoTool());
        $this->container->bind(GetOptionTool::class,   fn (): GetOptionTool   => new GetOptionTool());
        $this->container->bind(SetOptionTool::class,   fn (): SetOptionTool   => new SetOptionTool());

        // -----------------------------------------------------------------------
        // Page tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            CreatePageTool::class,
            fn ($c): CreatePageTool => new CreatePageTool($c->get(PageService::class))
        );
        $this->container->bind(
            UpdatePageTool::class,
            fn ($c): UpdatePageTool => new UpdatePageTool($c->get(PageService::class))
        );
        $this->container->bind(
            DeletePageTool::class,
            fn ($c): DeletePageTool => new DeletePageTool($c->get(PageService::class))
        );
        $this->container->bind(
            GetPageTool::class,
            fn ($c): GetPageTool => new GetPageTool($c->get(PageService::class))
        );
        $this->container->bind(
            ListPagesTool::class,
            fn ($c): ListPagesTool => new ListPagesTool($c->get(PageService::class))
        );
        $this->container->bind(
            DuplicatePageTool::class,
            fn ($c): DuplicatePageTool => new DuplicatePageTool($c->get(PageService::class))
        );
        $this->container->bind(
            PublishPageTool::class,
            fn ($c): PublishPageTool => new PublishPageTool($c->get(PageService::class))
        );
        $this->container->bind(
            SchedulePageTool::class,
            fn ($c): SchedulePageTool => new SchedulePageTool($c->get(PageService::class))
        );
        $this->container->bind(
            GetRevisionsTool::class,
            fn ($c): GetRevisionsTool => new GetRevisionsTool($c->get(PageService::class))
        );
        $this->container->bind(
            RestoreRevisionTool::class,
            fn ($c): RestoreRevisionTool => new RestoreRevisionTool($c->get(PageService::class))
        );

        // -----------------------------------------------------------------------
        // Post tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            CreatePostTool::class,
            fn ($c): CreatePostTool => new CreatePostTool($c->get(PostService::class))
        );
        $this->container->bind(
            UpdatePostTool::class,
            fn ($c): UpdatePostTool => new UpdatePostTool($c->get(PostService::class))
        );
        $this->container->bind(
            DeletePostTool::class,
            fn ($c): DeletePostTool => new DeletePostTool($c->get(PostService::class))
        );
        $this->container->bind(
            GetPostTool::class,
            fn ($c): GetPostTool => new GetPostTool($c->get(PostService::class))
        );
        $this->container->bind(
            ListPostsTool::class,
            fn ($c): ListPostsTool => new ListPostsTool($c->get(PostService::class))
        );
        $this->container->bind(
            QueryPostTool::class,
            fn (): QueryPostTool => new QueryPostTool()
        );
        $this->container->bind(
            SetFeaturedImageTool::class,
            fn ($c): SetFeaturedImageTool => new SetFeaturedImageTool($c->get(PostService::class))
        );
        $this->container->bind(
            ManageCategoriesTool::class,
            fn ($c): ManageCategoriesTool => new ManageCategoriesTool($c->get(PostService::class))
        );
        $this->container->bind(
            ManageTagsTool::class,
            fn ($c): ManageTagsTool => new ManageTagsTool($c->get(PostService::class))
        );

        // -----------------------------------------------------------------------
        // Media tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            UploadMediaTool::class,
            fn ($c): UploadMediaTool => new UploadMediaTool($c->get(MediaService::class))
        );
        $this->container->bind(
            ReplaceMediaTool::class,
            fn ($c): ReplaceMediaTool => new ReplaceMediaTool($c->get(MediaService::class))
        );
        $this->container->bind(
            CompressMediaTool::class,
            fn ($c): CompressMediaTool => new CompressMediaTool($c->get(MediaService::class))
        );
        $this->container->bind(
            GenerateAltTextTool::class,
            fn ($c): GenerateAltTextTool => new GenerateAltTextTool($c->get(MediaRepository::class))
        );
        $this->container->bind(
            ConvertWebpTool::class,
            fn ($c): ConvertWebpTool => new ConvertWebpTool($c->get(MediaService::class))
        );
        $this->container->bind(
            RenameMediaTool::class,
            fn ($c): RenameMediaTool => new RenameMediaTool($c->get(MediaService::class))
        );
        $this->container->bind(
            DetectDuplicatesTool::class,
            fn ($c): DetectDuplicatesTool => new DetectDuplicatesTool($c->get(MediaService::class))
        );

        // -----------------------------------------------------------------------
        // Menu tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            CreateMenuTool::class,
            fn ($c): CreateMenuTool => new CreateMenuTool($c->get(MenuService::class))
        );
        $this->container->bind(
            UpdateMenuTool::class,
            fn ($c): UpdateMenuTool => new UpdateMenuTool($c->get(MenuService::class))
        );
        $this->container->bind(
            DeleteMenuTool::class,
            fn ($c): DeleteMenuTool => new DeleteMenuTool($c->get(MenuService::class))
        );
        $this->container->bind(
            ListMenusTool::class,
            fn ($c): ListMenusTool => new ListMenusTool($c->get(MenuService::class))
        );

        // -----------------------------------------------------------------------
        // Plugin tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            SearchPluginsTool::class,
            fn ($c): SearchPluginsTool => new SearchPluginsTool($c->get(PluginService::class))
        );
        $this->container->bind(
            InstallPluginTool::class,
            fn ($c): InstallPluginTool => new InstallPluginTool($c->get(PluginService::class))
        );
        $this->container->bind(
            GetPluginStatusTool::class,
            fn (): GetPluginStatusTool => new GetPluginStatusTool()
        );
        $this->container->bind(
            ActivatePluginTool::class,
            fn ($c): ActivatePluginTool => new ActivatePluginTool($c->get(PluginService::class))
        );
        $this->container->bind(
            DeactivatePluginTool::class,
            fn ($c): DeactivatePluginTool => new DeactivatePluginTool($c->get(PluginService::class))
        );
        $this->container->bind(
            DeletePluginTool::class,
            fn ($c): DeletePluginTool => new DeletePluginTool($c->get(PluginService::class))
        );
        $this->container->bind(
            UpdatePluginTool::class,
            fn ($c): UpdatePluginTool => new UpdatePluginTool($c->get(PluginService::class))
        );
        $this->container->bind(
            RollbackPluginTool::class,
            fn ($c): RollbackPluginTool => new RollbackPluginTool($c->get(PluginService::class))
        );
        $this->container->bind(
            ListPluginsTool::class,
            fn ($c): ListPluginsTool => new ListPluginsTool($c->get(PluginService::class))
        );
        $this->container->bind(
            BulkInstallPluginsTool::class,
            fn ($c): BulkInstallPluginsTool => new BulkInstallPluginsTool($c->get(PluginService::class))
        );

        // -----------------------------------------------------------------------
        // Theme tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            SearchThemesTool::class,
            fn ($c): SearchThemesTool => new SearchThemesTool($c->get(ThemeService::class))
        );
        $this->container->bind(
            InstallThemeTool::class,
            fn ($c): InstallThemeTool => new InstallThemeTool($c->get(ThemeService::class))
        );
        $this->container->bind(
            ActivateThemeTool::class,
            fn ($c): ActivateThemeTool => new ActivateThemeTool($c->get(ThemeService::class))
        );
        $this->container->bind(
            CreateChildThemeTool::class,
            fn ($c): CreateChildThemeTool => new CreateChildThemeTool($c->get(ThemeService::class))
        );
        $this->container->bind(
            GetActiveThemeTool::class,
            fn (): GetActiveThemeTool => new GetActiveThemeTool()
        );
        $this->container->bind(
            ImportDemoTool::class,
            fn (): ImportDemoTool => new ImportDemoTool()
        );
        $this->container->bind(
            ExportDemoTool::class,
            fn (): ExportDemoTool => new ExportDemoTool()
        );

        // -----------------------------------------------------------------------
        // Elementor tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            CreateElementorPageTool::class,
            fn ($c): CreateElementorPageTool => new CreateElementorPageTool($c->get(PageService::class), $c->get(ElementorService::class))
        );
        $this->container->bind(
            ImportTemplateTool::class,
            fn ($c): ImportTemplateTool => new ImportTemplateTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            ExportTemplateTool::class,
            fn ($c): ExportTemplateTool => new ExportTemplateTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            DuplicateTemplateTool::class,
            fn ($c): DuplicateTemplateTool => new DuplicateTemplateTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            CreateContainerTool::class,
            fn ($c): CreateContainerTool => new CreateContainerTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            InsertWidgetTool::class,
            fn ($c): InsertWidgetTool => new InsertWidgetTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            GetTemplateTool::class,
            fn ($c): GetTemplateTool => new GetTemplateTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            UpdateTemplateTool::class,
            fn ($c): UpdateTemplateTool => new UpdateTemplateTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            SetPageLayoutTool::class,
            fn ($c): SetPageLayoutTool => new SetPageLayoutTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            SetGlobalColorsTool::class,
            fn ($c): SetGlobalColorsTool => new SetGlobalColorsTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            SetGlobalFontsTool::class,
            fn ($c): SetGlobalFontsTool => new SetGlobalFontsTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            BuildHeaderTool::class,
            fn ($c): BuildHeaderTool => new BuildHeaderTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            BuildFooterTool::class,
            fn ($c): BuildFooterTool => new BuildFooterTool($c->get(ElementorService::class))
        );
        $this->container->bind(
            BuildPopupTool::class,
            fn ($c): BuildPopupTool => new BuildPopupTool($c->get(ElementorService::class))
        );

        // -----------------------------------------------------------------------
        // Gutenberg tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            CreateBlockPageTool::class,
            fn ($c): CreateBlockPageTool => new CreateBlockPageTool($c->get(PageService::class))
        );
        $this->container->bind(
            InsertBlockTool::class,
            fn ($c): InsertBlockTool => new InsertBlockTool($c->get(GutenbergService::class))
        );
        $this->container->bind(
            CreatePatternTool::class,
            fn ($c): CreatePatternTool => new CreatePatternTool($c->get(GutenbergService::class))
        );
        $this->container->bind(
            CreateTemplateTool::class,
            fn ($c): CreateTemplateTool => new CreateTemplateTool($c->get(GutenbergService::class))
        );
        $this->container->bind(
            GenerateBlockMarkupTool::class,
            fn ($c): GenerateBlockMarkupTool => new GenerateBlockMarkupTool($c->get(GutenbergService::class))
        );

        // -----------------------------------------------------------------------
        // WooCommerce tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            CreateProductTool::class,
            fn ($c): CreateProductTool => new CreateProductTool($c->get(WooCommerceService::class))
        );
        $this->container->bind(
            UpdateProductTool::class,
            fn ($c): UpdateProductTool => new UpdateProductTool($c->get(WooCommerceService::class))
        );
        $this->container->bind(
            ListProductsTool::class,
            fn ($c): ListProductsTool => new ListProductsTool($c->get(WooCommerceService::class))
        );
        $this->container->bind(
            ListOrdersTool::class,
            fn ($c): ListOrdersTool => new ListOrdersTool($c->get(WooCommerceService::class))
        );
        $this->container->bind(
            CreateCouponTool::class,
            fn ($c): CreateCouponTool => new CreateCouponTool($c->get(WooCommerceService::class))
        );
        $this->container->bind(
            ManageInventoryTool::class,
            fn ($c): ManageInventoryTool => new ManageInventoryTool($c->get(WooCommerceService::class))
        );
        $this->container->bind(
            ConfigureShippingTool::class,
            fn ($c): ConfigureShippingTool => new ConfigureShippingTool($c->get(WooCommerceService::class))
        );
        $this->container->bind(
            ConfigureTaxTool::class,
            fn ($c): ConfigureTaxTool => new ConfigureTaxTool($c->get(WooCommerceService::class))
        );
        $this->container->bind(
            GetAnalyticsTool::class,
            fn ($c): GetAnalyticsTool => new GetAnalyticsTool($c->get(WooCommerceService::class))
        );

        // -----------------------------------------------------------------------
        // SEO tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            SetSeoMetaTool::class,
            fn (): SetSeoMetaTool => new SetSeoMetaTool()
        );
        $this->container->bind(
            SetOpenGraphTool::class,
            fn ($c): SetOpenGraphTool => new SetOpenGraphTool($c->get(SeoService::class))
        );
        $this->container->bind(
            SetSchemaMarkupTool::class,
            fn ($c): SetSchemaMarkupTool => new SetSchemaMarkupTool($c->get(SeoService::class))
        );
        $this->container->bind(
            GenerateSitemapTool::class,
            fn ($c): GenerateSitemapTool => new GenerateSitemapTool($c->get(SeoService::class))
        );
        $this->container->bind(
            RunSeoAuditTool::class,
            fn ($c): RunSeoAuditTool => new RunSeoAuditTool($c->get(SeoService::class))
        );

        // -----------------------------------------------------------------------
        // Performance tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            RunLighthouseTool::class,
            fn ($c): RunLighthouseTool => new RunLighthouseTool($c->get(PerformanceService::class))
        );
        $this->container->bind(
            ClearCacheTool::class,
            fn ($c): ClearCacheTool => new ClearCacheTool($c->get(PerformanceService::class))
        );
        $this->container->bind(
            FlushRewritesTool::class,
            fn (): FlushRewritesTool => new FlushRewritesTool()
        );
        $this->container->bind(
            OptimizeImagesTool::class,
            fn (): OptimizeImagesTool => new OptimizeImagesTool()
        );
        $this->container->bind(
            GetCwvTool::class,
            fn (): GetCwvTool => new GetCwvTool()
        );

        // -----------------------------------------------------------------------
        // Security tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            CreateBackupTool::class,
            fn ($c): CreateBackupTool => new CreateBackupTool($c->get(SecurityService::class))
        );
        $this->container->bind(
            RestoreBackupTool::class,
            fn (): RestoreBackupTool => new RestoreBackupTool()
        );
        $this->container->bind(
            RunMalwareScanTool::class,
            fn (): RunMalwareScanTool => new RunMalwareScanTool()
        );
        $this->container->bind(
            AuditPermissionsTool::class,
            fn ($c): AuditPermissionsTool => new AuditPermissionsTool($c->get(SecurityService::class))
        );
        $this->container->bind(
            ScanPluginsTool::class,
            fn (): ScanPluginsTool => new ScanPluginsTool()
        );
        $this->container->bind(
            CheckHeadersTool::class,
            fn ($c): CheckHeadersTool => new CheckHeadersTool($c->get(SecurityService::class))
        );

        // -----------------------------------------------------------------------
        // AI Planner tools.
        // -----------------------------------------------------------------------

        $this->container->bind(
            PlanGoalTool::class,
            fn ($c): PlanGoalTool => new PlanGoalTool($c->get(AiPlannerService::class))
        );
        $this->container->bind(
            ExecuteCodeTool::class,
            fn ($c): ExecuteCodeTool => new ExecuteCodeTool($c->get(CodeExecutionService::class))
        );
        $this->container->bind(
            FileWriteTool::class,
            fn (): FileWriteTool => new FileWriteTool()
        );
        $this->container->bind(
            FileReadTool::class,
            fn (): FileReadTool => new FileReadTool()
        );
        $this->container->bind(
            ScaffoldPluginTool::class,
            fn ($c): ScaffoldPluginTool => new ScaffoldPluginTool($c->get(AiPlannerService::class))
        );
        $this->container->bind(
            ScaffoldThemeTool::class,
            fn ($c): ScaffoldThemeTool => new ScaffoldThemeTool($c->get(AiPlannerService::class))
        );
    }

    public function boot(): void
    {
        $registry = $this->container->get(ToolRegistry::class);

        // Register all tools in namespace order.
        $registry->registerMany([
            // Site.
            $this->container->get(GetSiteInfoTool::class),
            $this->container->get(GetOptionTool::class),
            $this->container->get(SetOptionTool::class),

            // Pages.
            $this->container->get(CreatePageTool::class),
            $this->container->get(UpdatePageTool::class),
            $this->container->get(DeletePageTool::class),
            $this->container->get(GetPageTool::class),
            $this->container->get(ListPagesTool::class),
            $this->container->get(DuplicatePageTool::class),
            $this->container->get(PublishPageTool::class),
            $this->container->get(SchedulePageTool::class),
            $this->container->get(GetRevisionsTool::class),
            $this->container->get(RestoreRevisionTool::class),

            // Posts.
            $this->container->get(CreatePostTool::class),
            $this->container->get(UpdatePostTool::class),
            $this->container->get(DeletePostTool::class),
            $this->container->get(GetPostTool::class),
            $this->container->get(ListPostsTool::class),
            $this->container->get(QueryPostTool::class),
            $this->container->get(SetFeaturedImageTool::class),
            $this->container->get(ManageCategoriesTool::class),
            $this->container->get(ManageTagsTool::class),

            // Media.
            $this->container->get(UploadMediaTool::class),
            $this->container->get(ReplaceMediaTool::class),
            $this->container->get(CompressMediaTool::class),
            $this->container->get(GenerateAltTextTool::class),
            $this->container->get(ConvertWebpTool::class),
            $this->container->get(RenameMediaTool::class),
            $this->container->get(DetectDuplicatesTool::class),

            // Menus.
            $this->container->get(CreateMenuTool::class),
            $this->container->get(UpdateMenuTool::class),
            $this->container->get(DeleteMenuTool::class),
            $this->container->get(ListMenusTool::class),

            // Plugins.
            $this->container->get(SearchPluginsTool::class),
            $this->container->get(InstallPluginTool::class),
            $this->container->get(GetPluginStatusTool::class),
            $this->container->get(ActivatePluginTool::class),
            $this->container->get(DeactivatePluginTool::class),
            $this->container->get(DeletePluginTool::class),
            $this->container->get(UpdatePluginTool::class),
            $this->container->get(RollbackPluginTool::class),
            $this->container->get(ListPluginsTool::class),
            $this->container->get(BulkInstallPluginsTool::class),

            // Themes.
            $this->container->get(SearchThemesTool::class),
            $this->container->get(InstallThemeTool::class),
            $this->container->get(ActivateThemeTool::class),
            $this->container->get(GetActiveThemeTool::class),
            $this->container->get(CreateChildThemeTool::class),
            $this->container->get(ImportDemoTool::class),
            $this->container->get(ExportDemoTool::class),

            // Elementor.
            $this->container->get(CreateElementorPageTool::class),
            $this->container->get(GetTemplateTool::class),
            $this->container->get(UpdateTemplateTool::class),
            $this->container->get(SetPageLayoutTool::class),
            $this->container->get(ImportTemplateTool::class),
            $this->container->get(ExportTemplateTool::class),
            $this->container->get(DuplicateTemplateTool::class),
            $this->container->get(CreateContainerTool::class),
            $this->container->get(InsertWidgetTool::class),
            $this->container->get(SetGlobalColorsTool::class),
            $this->container->get(SetGlobalFontsTool::class),
            $this->container->get(BuildHeaderTool::class),
            $this->container->get(BuildFooterTool::class),
            $this->container->get(BuildPopupTool::class),

            // Gutenberg.
            $this->container->get(CreateBlockPageTool::class),
            $this->container->get(InsertBlockTool::class),
            $this->container->get(CreatePatternTool::class),
            $this->container->get(CreateTemplateTool::class),
            $this->container->get(GenerateBlockMarkupTool::class),

            // WooCommerce.
            $this->container->get(CreateProductTool::class),
            $this->container->get(UpdateProductTool::class),
            $this->container->get(ListProductsTool::class),
            $this->container->get(ListOrdersTool::class),
            $this->container->get(CreateCouponTool::class),
            $this->container->get(ManageInventoryTool::class),
            $this->container->get(ConfigureShippingTool::class),
            $this->container->get(ConfigureTaxTool::class),
            $this->container->get(GetAnalyticsTool::class),

            // SEO.
            $this->container->get(SetSeoMetaTool::class),
            $this->container->get(SetOpenGraphTool::class),
            $this->container->get(SetSchemaMarkupTool::class),
            $this->container->get(GenerateSitemapTool::class),
            $this->container->get(RunSeoAuditTool::class),

            // Performance.
            $this->container->get(RunLighthouseTool::class),
            $this->container->get(ClearCacheTool::class),
            $this->container->get(FlushRewritesTool::class),
            $this->container->get(OptimizeImagesTool::class),
            $this->container->get(GetCwvTool::class),

            // Security.
            $this->container->get(CreateBackupTool::class),
            $this->container->get(RestoreBackupTool::class),
            $this->container->get(RunMalwareScanTool::class),
            $this->container->get(AuditPermissionsTool::class),
            $this->container->get(ScanPluginsTool::class),
            $this->container->get(CheckHeadersTool::class),

            // AI Planner.
            $this->container->get(PlanGoalTool::class),
            $this->container->get(ExecuteCodeTool::class),
            $this->container->get(FileWriteTool::class),
            $this->container->get(FileReadTool::class),
            $this->container->get(ScaffoldPluginTool::class),
            $this->container->get(ScaffoldThemeTool::class),
        ]);
    }
}
