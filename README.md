# Bref PHP + PDO PostgreSQL Multi-Architecture Example

This repository demonstrates a working multi-architecture (ARM64 and x86_64) implementation of PHP Lambda functions using Bref and PDO PostgreSQL. It addresses common issues encountered in local development environments and showcases best practices for architecture-aware Docker configuration.

## Key Features

- **Multi-Architecture Support**: Works on both ARM64 (Apple Silicon) and x86_64 (Intel) platforms
- **Architecture Detection**: Automatically selects the appropriate configuration for the current platform
- **Local Development Environment**: Uses PHP built-in server for Function URLs and Bref-dev-server for API Gateway
- **PDO PostgreSQL Integration**: Properly handles architecture-specific extension loading
- **Docker Compose Setup**: Provides easy-to-use local development environment
- **Dual Handler Support**: Implements both Function URL and API Gateway handlers

## Architecture-Specific Behavior

This example follows an "ARM64-first" approach while ensuring full compatibility with x86_64. We've identified two critical architecture differences:

1. **PDO PostgreSQL Extension Loading**:
   - **ARM64 (bref/arm-php-82:2)**: PDO PostgreSQL is available by default without explicit loading
   - **x86_64 (bref/php-82:2)**: PDO PostgreSQL must be explicitly loaded with `extension=pdo_pgsql`

2. **Local Testing Approaches**:
   - **Function URL**: Uses PHP built-in server to handle HTTP requests directly
   - **API Gateway**: Uses Bref development server to emulate API Gateway environment
   - Both approaches are implemented with support for ARM64 and x86_64 architectures

### What to Expect on Your Platform

#### On ARM64 (Apple Silicon)
- The example automatically detects ARM64 and uses the ARM64-specific Dockerfiles
- The default Docker configuration runs natively on ARM64 for best performance
- The `lambda-x86` and `lambda-api-x86` services demonstrate using x86_64 emulation if needed for compatibility

#### On x86_64 (Intel)
- The example automatically adjusts to use x86_64-specific configurations
- The architecture detection script sets `LAMBDA_PLATFORM=linux/amd64` by default
- You'll still have access to the ARM64-specific configurations if you need to test ARM64 compatibility

## Quick Start

### Prerequisites

- Docker and Docker Compose
- AWS CLI (for optional AWS deployment)

### Automatic Architecture Detection

This example uses automatic architecture detection to provide the correct configuration:

```bash
# Source the detection script to configure for your architecture
source ./detect-arch.sh

# You'll see which configuration will be used:
# - ARM64: Uses Dockerfile with linux/arm64 platform
# - x86_64: Uses Dockerfile.x86 with linux/amd64 platform
```

### Local Development

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/bref-php-pdo-pgsql-example.git
   cd bref-php-pdo-pgsql-example
   ```

2. Install dependencies (if needed):
   ```bash
   # Using Docker to install dependencies (no local PHP required)
   # Ignore platform requirements since we don't need PDO PostgreSQL locally
   docker run --rm -v "$(pwd):/app" composer:2 install --ignore-platform-req=ext-pdo_pgsql
   ```

3. Start the containers:
   ```bash
   # Start all containers for testing both approaches
   docker compose up -d
   ```

4. Test the Function URL handler (native architecture):
   ```bash
   # Invoke the function using the helper script
   ./invoke.sh local lambda
   ```

5. Test the API Gateway handler (native architecture):
   ```bash
   # Invoke the function using the helper script
   ./invoke.sh local lambda-api
   ```

6. Test the x86_64 handlers (emulated on ARM64):
   ```bash
   # Function URL on x86_64
   ./invoke.sh local lambda-x86
   
   # API Gateway on x86_64
   ./invoke.sh local lambda-api-x86
   ```

7. View logs:
   ```bash
   ./logs.sh local lambda
   ./logs.sh local lambda-api
   ./logs.sh local lambda-x86
   ./logs.sh local lambda-api-x86
   ```

## Multi-Architecture Testing

### Native Architecture

Use the default `lambda` and `lambda-api` services, which will use your host architecture:

```bash
# Run on your native architecture
docker compose up -d lambda lambda-api
./invoke.sh local lambda
./invoke.sh local lambda-api
```

### x86_64 Emulation Testing (For ARM64 Users)

On Apple Silicon Macs, you can test the x86_64 versions:

```bash
# Run with x86_64 emulation
docker compose up -d lambda-x86 lambda-api-x86
./invoke.sh local lambda-x86
./invoke.sh local lambda-api-x86
```

### Advanced: Forcing x86_64 on ARM64

To explicitly force x86_64 emulation:

```bash
# Set force flag before starting
export LAMBDA_FORCE_X86=true
source ./detect-arch.sh
docker compose up -d lambda
```

## Architecture-Specific PHP Configuration

We've implemented a clean solution using architecture-specific PHP .ini files:

**ARM64 Configuration (php/conf.d/php.ini.arm64)**:
```ini
; Standard logging settings
log_errors=1
display_startup_errors=1
error_reporting=E_ALL

; NOTE: DO NOT load pdo_pgsql here as it's already loaded by default on ARM64
; extension=pdo_pgsql

; Additional PHP settings
zend.exception_ignore_args=0
```

**x86_64 Configuration (php/conf.d/php.ini.x86_64)**:
```ini
; Standard logging settings
log_errors=1
display_startup_errors=1
error_reporting=E_ALL

; Load PDO PostgreSQL extension - REQUIRED on x86_64
extension=pdo_pgsql

; Additional PHP settings
zend.exception_ignore_args=0
```

These configurations are selected at Docker build time:

```dockerfile
# In Dockerfile (ARM64)
COPY php/conf.d/php.ini.arm64 /var/task/php/conf.d/php.ini

# In Dockerfile.x86 (x86_64)
COPY php/conf.d/php.ini.x86_64 /var/task/php/conf.d/php.ini
```

## AWS Deployment

To deploy to AWS Lambda:

1. Create a `.env` file with your AWS configuration:
   ```
   AWS_REGION=eu-central-1
   VPC_SECURITY_GROUP_ID=sg-xxx
   VPC_SUBNET_ID=subnet-xxx
   DB_HOST=your-postgres-host
   DB_PORT=5432
   DB_NAME=postgres
   DB_USERNAME=postgres
   DB_PASSWORD=your-password
   ```

2. Deploy and test:
   ```bash
   ./deploy.sh     # Deploy to AWS
   ./invoke.sh     # Test the function
   ./logs.sh       # View logs
   ```

## Project Structure

```
├── Dockerfile              # ARM64 Dockerfile
├── Dockerfile.x86          # x86_64 Dockerfile
├── Dockerfile.api          # ARM64 API Gateway Dockerfile
├── Dockerfile.api.x86      # x86_64 API Gateway Dockerfile
├── docker-compose.yml      # Docker Compose configuration
├── api-entrypoint.sh       # Entrypoint for API Gateway containers
├── entrypoint.sh           # Entrypoint for Function URL containers
├── php/
│   └── conf.d/             # PHP configuration
│       ├── php.ini         # Active PHP configuration
│       ├── php.ini.arm64   # ARM64-specific configuration
│       └── php.ini.x86_64  # x86_64-specific configuration
├── src/
│   └── PdoTester.php       # Shared PDO testing logic
├── function-url-handler.php # Lambda Function URL handler
├── api-handler.php         # Lambda API Gateway handler
├── composer.json           # PHP dependencies
├── detect-arch.sh          # Architecture detection script
├── test-pdo-pgsql.php      # PDO PostgreSQL test script
├── invoke.sh               # Lambda invocation helper
└── logs.sh                 # Log viewing helper
```

## Testing and Deployment Approaches

This project demonstrates two Lambda deployment approaches:

### 1. Lambda Function URLs

- Configured with `url: true` in serverless.yml
- Direct HTTP access to the Lambda function without API Gateway
- More cost-effective for simple Lambda HTTP endpoints
- Uses `function-url-handler.php` which implements Bref's `Handler` interface
- Local testing uses the `lambda` and `lambda-x86` containers with PHP's built-in server

### 2. API Gateway

- Configured with `httpApi: '*'` in serverless.yml
- Uses Amazon API Gateway to route requests to Lambda
- More features for API management, but slightly higher cost
- Uses `api-handler.php` which implements PSR-15's `RequestHandlerInterface`
- Local testing uses the `lambda-api` and `lambda-api-x86` containers with Bref's dev server

Both handlers share common logic through the `PdoTester` class located in `src/PdoTester.php`.

## Local Testing

The project supports comprehensive local testing of both approaches:

### Function URL Testing

- Uses PHP's built-in server for direct HTTP invocation
- Accessible through Docker Compose network
- Command: `./invoke.sh local lambda` (or `lambda-x86`)

### API Gateway Testing

- Uses `bref-dev-server` to simulate API Gateway locally
- Also accessible through Docker Compose network
- Command: `./invoke.sh local lambda-api` (or `lambda-api-x86`)

Both approaches are implemented with multi-architecture support (ARM64 and x86_64).

## License

MIT

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.