<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;

class DatabaseRestoreDumpFilter
{
    /**
     * Removes runtime table sections from legacy dumps and returns the business
     * tables represented by the dump. New dumps already omit these sections.
     *
     * @return list<string>
     */
    public function filter(string $source, string $destination): array
    {
        $input = @fopen($source, 'rb');
        $output = @fopen($destination, 'wb');
        if (! is_resource($input) || ! is_resource($output)) {
            is_resource($input) && fclose($input);
            is_resource($output) && fclose($output);
            throw new BackupOperationException('restore_database_filter_failed', 'آماده‌سازی امن dump پایگاه داده ناموفق بود.');
        }

        $protected = array_fill_keys(config('backup.operational_tables', []), true);
        $represented = [];
        $skip = false;

        try {
            while (($line = fgets($input)) !== false) {
                if (preg_match('/^-- Table structure for table `([^`]+)`\R?$/', $line, $matches) === 1) {
                    $table = $matches[1];
                    $skip = isset($protected[$table]);
                    if (! $skip) {
                        $represented[$table] = true;
                    }
                }
                if (! $skip && fwrite($output, $line) === false) {
                    throw new BackupOperationException('restore_database_filter_failed', 'نوشتن dump امن پایگاه داده ناموفق بود.');
                }
            }
            if (! feof($input)) {
                throw new BackupOperationException('restore_database_filter_failed', 'خواندن dump پایگاه داده ناموفق بود.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        if ($represented === [] || ! isset($represented['migrations'])
            || ! is_file($destination) || filesize($destination) < 1) {
            throw new BackupOperationException('restore_database_filter_failed', 'dump پایگاه داده فاقد جدول‌های قابل بازیابی است.');
        }

        return array_keys($represented);
    }
}
