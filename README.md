# Bref PHP + PDO PostgreSQL Multi-Architecture Example

This repository demonstrates a working multi-architecture (ARM64 and x86_64) implementation of PHP Lambda functions using Bref and PDO PostgreSQL. It addresses common issues encountered in local development environments and showcases best practices for architecture-aware Docker configuration.

## Key Features

- **Multi-Architecture Support**: Works on both ARM64 (Apple Silicon) and x86_64 (Intel) platforms
- **Architecture Detection**: Automatically selects the appropriate configuration for the current platform
- **Local Lambda Runtime**: Uses AWS Lambda Runtime Interface Emulator (RIE) for local testing
- **PDO PostgreSQL Integration**: Properly handles architecture-specific extension loading
- **Docker Compose Setup**: Provides easy-to-use local development environment
- **Sample Lambda Function**: Simple PDO PostgreSQL connection example

## Architecture-Specific Behavior

This example follows an "ARM64-first" approach while ensuring full compatibility with x86_64. We've identified two critical architecture differences:

1. **PDO PostgreSQL Extension Loading**:
   - **ARM64 (bref/arm-php-82:2)**: PDO PostgreSQL is available by default without explicit loading
   - **x86_64 (bref/php-82:2)**: PDO PostgreSQL must be explicitly loaded with `extension=pdo_pgsql`

2. **Runtime Interface Emulator Behavior**:
   - Both architectures may show "Runtime exited without providing a reason" in local Docker
   - This exit happens *after* the function completes and returns a response
   - The same code works flawlessly in actual AWS Lambda

### What to Expect on Your Platform

#### On ARM64 (Apple Silicon)
- The example automatically detects ARM64 and uses the ARM64-specific Dockerfile
- The default Docker configuration runs natively on ARM64 for best performance
- The `lambda-x86` service demonstrates using x86_64 emulation if needed for compatibility

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

2. Start the containers:
   ```bash
   # Start both containers for testing
   docker compose up -d
   ```

3. Test the function (native architecture):
   ```bash
   # Invoke the function in the lambda container
   docker compose exec lambda curl -s -X POST "http://localhost:8080/2015-03-31/functions/function/invocations" -d '{}'
   ```

4. Test the x86_64 function (emulated on ARM64):
   ```bash
   # Invoke the function in the x86_64 container
   docker compose exec lambda-x86 curl -s -X POST "http://localhost:8080/2015-03-31/functions/function/invocations" -d '{}'
   ```

5. View logs:
   ```bash
   docker compose logs lambda
   docker compose logs lambda-x86
   ```

## Multi-Architecture Testing

### Native Architecture

Use the default `lambda` service, which will use your host architecture:

```bash
# Run on your native architecture
docker compose up -d lambda
./invoke.sh local lambda
./logs.sh local lambda
```

### x86_64 Emulation Testing (For ARM64 Users)

On Apple Silicon Macs, you can test the x86_64 version:

```bash
# Run with x86_64 emulation
docker compose up -d lambda-x86
./invoke.sh local lambda-x86
./logs.sh local lambda-x86
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
├── docker-compose.yml      # Docker Compose configuration
├── php/
│   └── conf.d/             # PHP configuration
│       ├── php.ini         # Active PHP configuration
│       ├── php.ini.arm64   # ARM64-specific configuration
│       └── php.ini.x86_64  # x86_64-specific configuration
├── composer.json           # PHP dependencies
├── detect-arch.sh          # Architecture detection script
├── entrypoint.sh           # Container entrypoint script
├── index.php               # Lambda handler
├── test-pdo-pgsql.php      # PDO PostgreSQL test script
├── invoke.sh               # Lambda invocation helper
└── logs.sh                 # Log viewing helper
```

## Known Limitations

- The AWS Lambda Runtime Interface Emulator (RIE) in Docker may exit unexpectedly after successful execution
- This exit is harmless as it happens after the function completes and returns a response
- Docker Compose can be configured with restart policies if needed
- The issue doesn't occur in actual AWS Lambda deployments

## License

MIT

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.