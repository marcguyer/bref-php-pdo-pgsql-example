<?php
// Simple script to test if pdo_pgsql extension is available

echo "PDO drivers available: " . implode(', ', PDO::getAvailableDrivers()) . PHP_EOL;

if (in_array('pgsql', PDO::getAvailableDrivers())) {
    echo "PDO PostgreSQL driver is AVAILABLE" . PHP_EOL;
} else {
    echo "PDO PostgreSQL driver is NOT AVAILABLE" . PHP_EOL;
}

if (defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) {
    echo "PDO::PGSQL_ATTR_DISABLE_PREPARES constant is defined with value: " . PDO::PGSQL_ATTR_DISABLE_PREPARES . PHP_EOL;
} else {
    echo "PDO::PGSQL_ATTR_DISABLE_PREPARES constant is NOT defined" . PHP_EOL;
}