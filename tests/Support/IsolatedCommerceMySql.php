<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

final class IsolatedCommerceMySql
{
    public static function connect(string $database): PDO
    {
        $directory = realpath((string) getenv('KING_P2_INNODB_TEST_DIRECTORY'));
        $port = (string) getenv('KING_P2_INNODB_TEST_PORT');
        $temp = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR;
        if (! $directory || ! str_starts_with(strtolower($directory), strtolower($temp.'king_p2_mysql_testing_'))
            || ! ctype_digit($port) || (int) $port < 1024
            || ! preg_match('/^king_p2_[a-f0-9]+_testing$/D', $database)) {
            throw new RuntimeException('An explicitly isolated temporary InnoDB instance is required.');
        }
        $pdo = new PDO('mysql:host=127.0.0.1;port='.$port, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $actual = realpath($pdo->query('SELECT @@datadir')->fetchColumn());
        if (! $actual || strcasecmp($actual, $directory) !== 0) {
            throw new RuntimeException('Refusing to touch a database outside the explicit temporary instance.');
        }

        return $pdo;
    }
}
