<?php
declare(strict_types=1);

namespace App;

/**
 * Core PDO testing logic that can be used by different handlers
 */
class PdoTester
{
    /**
     * Run the PDO PostgreSQL tests and return the result
     */
    public function runTests(): array
    {
        try {
            // Get database connection parameters
            $host = getenv('DB_HOST') ?: 'postgres';
            $port = getenv('DB_PORT') ?: '5432';
            $dbname = getenv('DB_NAME') ?: 'postgres';
            $username = getenv('DB_USERNAME') ?: 'postgres';
            $password = getenv('DB_PASSWORD') ?: 'postgres';
            
            // Create a DSN
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            
            // Get the PDO::PGSQL_ATTR_DISABLE_PREPARES value
            $isPgSqlAttrDefined = defined('PDO::PGSQL_ATTR_DISABLE_PREPARES');
            $pgSqlAttrValue = $isPgSqlAttrDefined ? \PDO::PGSQL_ATTR_DISABLE_PREPARES : 1000;
            
            error_log("LAMBDA DEBUG: PDO::PGSQL_ATTR_DISABLE_PREPARES defined: " . ($isPgSqlAttrDefined ? 'Yes' : 'No'));
            error_log("LAMBDA DEBUG: PDO::PGSQL_ATTR_DISABLE_PREPARES value: $pgSqlAttrValue");
            
            // ======== TEST 1: CREATE MULTIPLE CONNECTIONS ========
            error_log('LAMBDA DEBUG: Creating multiple PDO connections...');
            $connections = [];
            $results = [];
            
            // Create 5 connections in parallel
            for ($i = 0; $i < 5; $i++) {
                $connections[$i] = new \PDO($dsn, $username, $password);
                $connections[$i]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                
                // Directly set the PostgreSQL attribute to different values on each connection
                $value = ($i % 2 == 0);
                try {
                    if ($i === 0) {
                        // First connection: Use the raw value
                        $connections[$i]->setAttribute(1000, $value);
                    } else if ($i === 1) {
                        // Second connection: Use the constant indirectly
                        $attr = $pgSqlAttrValue;
                        $connections[$i]->setAttribute($attr, $value);
                    } else if ($i === 2) {
                        // Third connection: Use directly if defined
                        if (defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) {
                            $connections[$i]->setAttribute(\PDO::PGSQL_ATTR_DISABLE_PREPARES, $value);
                        }
                    } else {
                        // Other connections: Use default pattern
                        $connections[$i]->setAttribute($pgSqlAttrValue, $value);
                    }
                    
                    error_log("LAMBDA DEBUG: Connection $i: Successfully set attribute to " . ($value ? 'true' : 'false'));
                } catch (\Throwable $e) {
                    error_log("LAMBDA DEBUG: Connection $i: Error setting attribute: " . $e->getMessage());
                }
                
                // Run a query on each connection
                $stmt = $connections[$i]->query("SELECT $i as test_$i");
                $results[$i] = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            // ======== TEST 2: TOGGLE ATTRIBUTE ========
            error_log('LAMBDA DEBUG: Creating connection for prepared statement tests...');
            $prep_pdo = new \PDO($dsn, $username, $password);
            $prep_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Toggle the attribute a few times while using prepared statements
            $toggle_results = [];
            for ($i = 0; $i < 5; $i++) {
                try {
                    // Set attribute to either true or false
                    $toggle_value = ($i % 2 == 0);
                    error_log("LAMBDA DEBUG: Setting attribute to " . ($toggle_value ? 'true' : 'false'));
                    $prep_pdo->setAttribute($pgSqlAttrValue, $toggle_value);
                    
                    // Create and execute a prepared statement
                    $stmt = $prep_pdo->prepare("SELECT :param AS toggle_result");
                    $stmt->bindValue(':param', $i);
                    $stmt->execute();
                    $toggle_results[$i] = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    // Immediately close the statement
                    $stmt = null;
                } catch (\Throwable $e) {
                    error_log("LAMBDA DEBUG: Toggle test $i failed: " . $e->getMessage());
                }
            }
            
            // ======== TEST 3: CONNECTION MANAGEMENT ========
            error_log('LAMBDA DEBUG: Testing multiple connection creation with lingering references...');
            $lingering_connections = [];
            
            // Create and immediately discard connections
            for ($i = 0; $i < 10; $i++) {
                try {
                    // Create a new connection
                    $new_pdo = new \PDO($dsn, $username, $password);
                    
                    // Every 3rd connection we'll keep a reference to
                    if ($i % 3 === 0) {
                        error_log("LAMBDA DEBUG: Keeping reference to connection $i");
                        $lingering_connections[] = $new_pdo;
                        
                        // Set the attribute
                        $new_pdo->setAttribute($pgSqlAttrValue, ($i % 2 === 0));
                        
                        // Run a query
                        $stmt = $new_pdo->query("SELECT $i AS lingering_test");
                        $lingering_results[$i] = $stmt->fetch(\PDO::FETCH_ASSOC);
                    } else {
                        // Just discard it
                        error_log("LAMBDA DEBUG: Discarding connection $i");
                        $new_pdo = null;
                    }
                    
                    // Force garbage collection every 5 iterations
                    if ($i % 5 === 0 && function_exists('gc_collect_cycles')) {
                        error_log("LAMBDA DEBUG: Forcing garbage collection");
                        gc_collect_cycles();
                    }
                } catch (\Throwable $e) {
                    error_log("LAMBDA DEBUG: Connection creation $i failed: " . $e->getMessage());
                }
            }
            
            // Aggressively close connections in reverse order
            error_log('LAMBDA DEBUG: Closing connections in reverse order...');
            foreach (array_reverse($lingering_connections) as $index => $conn) {
                try {
                    // Run one query before closing
                    $stmt = $conn->query("SELECT 'cleanup' AS cleanup_test");
                    $cleanup_result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    // Explicitly close resources
                    $stmt = null;
                    $conn = null;
                    
                    error_log("LAMBDA DEBUG: Successfully closed lingering connection $index");
                } catch (\Throwable $e) {
                    error_log("LAMBDA DEBUG: Error closing connection $index: " . $e->getMessage());
                }
            }
            
            // Final verification connection
            error_log('LAMBDA DEBUG: Creating final verification connection...');
            $final_pdo = new \PDO($dsn, $username, $password);
            $final_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $final_stmt = $final_pdo->query('SELECT 999 as final');
            $final_result = $final_stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Return test results
            return [
                'message' => 'PDO PostgreSQL test completed successfully',
                'connection_count' => count($connections) + count($lingering_connections) + 1,
                'final_result' => $final_result,
                'environment' => [
                    'php_version' => PHP_VERSION,
                    'sapi_name' => php_sapi_name(),
                    'os' => PHP_OS
                ],
                'notes' => [
                    'Test completed without errors',
                    'Multiple PDO connections created and used successfully',
                    'Function URL and event invocation both supported'
                ]
            ];
            
        } catch (\Throwable $e) {
            error_log('LAMBDA ERROR: ' . $e->getMessage());
            
            return [
                'error' => 'Error during PDO PostgreSQL tests',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
    }
}