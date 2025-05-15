<?php
declare(strict_types=1);

// Bootstrap core app
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/PdoTester.php';

use App\PdoTester;
use Bref\Context\Context;
use Bref\Event\Handler;

/**
 * Lambda Function URL handler
 */
class FunctionUrlHandler implements Handler
{
    public function handle($event, Context $context = null): array
    {
        error_log('LAMBDA DEBUG: Starting Function URL handler execution');
        
        // Run the PDO tests
        $pdoTester = new PdoTester();
        $results = $pdoTester->runTests();
        
        // Return in Function URL format
        return [
            'statusCode' => isset($results['error']) ? 500 : 200,
            'body' => json_encode($results, JSON_PRETTY_PRINT),
            'headers' => ['Content-Type' => 'application/json']
        ];
    }
}

// Return the handler
return new FunctionUrlHandler();