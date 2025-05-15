<?php
declare(strict_types=1);

// Simple handler to test PDO PostgreSQL segfault issue
error_log('LAMBDA DEBUG: Starting handler execution');

// First, load the autoloader
try {
    require __DIR__ . '/vendor/autoload.php';
    error_log('LAMBDA DEBUG: Autoloader loaded successfully');
} catch (\Throwable $e) {
    error_log('LAMBDA DEBUG: Failed to load autoloader: ' . $e->getMessage());
    throw $e;
}

use Bref\Context\Context;
use Bref\Event\Handler;

/**
 * Handler to reproduce PDO PostgreSQL segfault observed in Bref/Lambda environments
 * This implementation is very aggressive in attempting to trigger the segfault
 */
class PdoSegfaultHandler implements Handler
{
    public function handle($event, Context $context): array
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
            $pgSqlAttrValue = $isPgSqlAttrDefined ? PDO::PGSQL_ATTR_DISABLE_PREPARES : 1000;
            
            error_log("LAMBDA DEBUG: PDO::PGSQL_ATTR_DISABLE_PREPARES defined: " . ($isPgSqlAttrDefined ? 'Yes' : 'No'));
            error_log("LAMBDA DEBUG: PDO::PGSQL_ATTR_DISABLE_PREPARES value: $pgSqlAttrValue");
            
            // ======== SEGFAULT TRIGGER ATTEMPT 1 ========
            // Create multiple PDO connections and set attributes directly
            error_log('LAMBDA DEBUG: Creating multiple PDO connections...');
            $connections = [];
            $results = [];
            
            // Create 5 connections in parallel
            for ($i = 0; $i < 5; $i++) {
                $connections[$i] = new PDO($dsn, $username, $password);
                $connections[$i]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
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
                            $connections[$i]->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, $value);
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
                $results[$i] = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // ======== SEGFAULT TRIGGER ATTEMPT 2 ========
            // Rapidly toggle the attribute and run prepared statements, which is a common scenario
            error_log('LAMBDA DEBUG: Creating connection for prepared statement tests...');
            $prep_pdo = new PDO($dsn, $username, $password);
            $prep_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Toggle the attribute a few times while using prepared statements
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
                    $toggle_results[$i] = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Immediately close the statement
                    $stmt = null;
                } catch (\Throwable $e) {
                    error_log("LAMBDA DEBUG: Toggle test $i failed: " . $e->getMessage());
                }
            }
            
            // ======== SEGFAULT TRIGGER ATTEMPT 3 ========
            // Keep references to a connection while creating new ones
            // This can sometimes cause issues with memory management
            error_log('LAMBDA DEBUG: Testing multiple connection creation with lingering references...');
            $lingering_connections = [];
            
            // Create and immediately discard connections
            for ($i = 0; $i < 10; $i++) {
                try {
                    // Create a new connection
                    $new_pdo = new PDO($dsn, $username, $password);
                    
                    // Every 3rd connection we'll keep a reference to
                    if ($i % 3 === 0) {
                        error_log("LAMBDA DEBUG: Keeping reference to connection $i");
                        $lingering_connections[] = $new_pdo;
                        
                        // Set the attribute
                        $new_pdo->setAttribute($pgSqlAttrValue, ($i % 2 === 0));
                        
                        // Run a query
                        $stmt = $new_pdo->query("SELECT $i AS lingering_test");
                        $lingering_results[$i] = $stmt->fetch(PDO::FETCH_ASSOC);
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
                    $cleanup_result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
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
            $final_pdo = new PDO($dsn, $username, $password);
            $final_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $final_stmt = $final_pdo->query('SELECT 999 as final');
            $final_result = $final_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create response
            return [
                'statusCode' => 200,
                'body' => json_encode([
                    'message' => 'Extensive PDO PostgreSQL tests completed without segfault',
                    'connection_count' => count($connections) + count($lingering_connections) + 1,
                    'final_result' => $final_result,
                    'notes' => [
                        'If you see this message, the handler completed without a segfault',
                        'Note: "Module pdo_pgsql already loaded" warnings may appear in logs',
                        'The unusual runtime exit may still occur after response is sent'
                    ]
                ], JSON_PRETTY_PRINT),
                'headers' => ['Content-Type' => 'application/json']
            ];
            
        } catch (\Throwable $e) {
            error_log('LAMBDA ERROR: ' . $e->getMessage());
            
            return [
                'statusCode' => 500,
                'body' => json_encode([
                    'error' => 'Error during PDO PostgreSQL segfault attempts',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], JSON_PRETTY_PRINT),
                'headers' => ['Content-Type' => 'application/json']
            ];
        }
    }
}

// Return the handler to Lambda
return new PdoSegfaultHandler();