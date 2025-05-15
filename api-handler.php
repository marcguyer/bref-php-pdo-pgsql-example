<?php
declare(strict_types=1);

// Bootstrap core app
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/PdoTester.php';

use App\PdoTester;
use Bref\Context\Context;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lambda API Gateway handler
 * This class implements PSR-15 RequestHandlerInterface for API Gateway
 */
class ApiGatewayHandler implements RequestHandlerInterface
{
    /**
     * Handle method required by PSR-15 RequestHandlerInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        error_log('LAMBDA DEBUG: Starting API Gateway handler execution via PSR-15');
        
        try {
            // Run the PDO tests
            $pdoTester = new PdoTester();
            $results = $pdoTester->runTests();
            
            // Create an HTTP response with the results
            $status = isset($results['error']) ? 500 : 200;
            $body = json_encode($results, JSON_PRETTY_PRINT);
            
            return new Response(
                $status,
                ['Content-Type' => 'application/json'],
                $body
            );
        } catch (\Throwable $e) {
            // Log the error
            error_log('PDO Test Error: ' . $e->getMessage());
            
            // Return error response
            return new Response(
                500,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => $e->getMessage()])
            );
        }
    }
}

// Return the handler
return new ApiGatewayHandler();