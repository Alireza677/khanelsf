<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TEMP DEBUG - remove after production save issue is fixed if you do not want to keep this schema hardening.
     */
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $columns = [
            'pages' => ['seo_description'],
            'posts' => ['excerpt', 'description', 'seo_description'],
            'categories' => ['description'],
            'settings' => ['value'],
            'project_categories' => ['description', 'seo_description'],
            'projects' => ['excerpt', 'description', 'seo_description'],
            'product_categories' => ['description', 'seo_description'],
            'products' => ['excerpt', 'description', 'seo_description'],
            'gallery_categories' => ['description', 'seo_description'],
            'galleries' => ['excerpt', 'description', 'seo_description'],
            'templates' => ['description'],
        ];

        foreach ($columns as $table => $tableColumns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($tableColumns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::statement(sprintf('ALTER TABLE `%s` MODIFY `%s` LONGTEXT NULL', $table, $column));
            }
        }
    }

    public function down(): void
    {
        // TEMP DEBUG - no down conversion to avoid truncating production content.
    }
};
