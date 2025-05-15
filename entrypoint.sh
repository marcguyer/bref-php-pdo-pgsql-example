#!/bin/sh
set -eu

# Simple entrypoint for Lambda container to help reproduce PDO PostgreSQL segfault
echo "Starting Lambda container for PDO PostgreSQL segfault reproduction..."

# Install Composer dependencies if they don't exist - do this first, before any other operations
if [ ! -d "/var/task/vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
else
    echo "Composer dependencies already installed."
fi

# Set up Lambda environment variables
export LAMBDA_TASK_ROOT=/var/task
export HANDLER=${HANDLER}
export _HANDLER=${HANDLER}
export AWS_LAMBDA_FUNCTION_NAME=pgsql-segfault
export AWS_LAMBDA_FUNCTION_VERSION=\$LATEST
export AWS_LAMBDA_FUNCTION_MEMORY_SIZE=512
export RUNTIME_CLASS="Bref\FunctionRuntime\Main"

# Print configuration and debug info
echo "Environment configuration:"
echo "DB_HOST: ${DB_HOST}"
echo "DB_PORT: ${DB_PORT}"
echo "DB_NAME: ${DB_NAME}"
echo "DB_USERNAME: ${DB_USERNAME}"
echo "HANDLER: ${HANDLER}"
echo "Debug Options:"
echo "AWS_LAMBDA_RUNTIME_API_DEBUG: ${AWS_LAMBDA_RUNTIME_API_DEBUG:-0}"
echo "RAPID_DEBUG: ${RAPID_DEBUG:-0}"
echo "AWS_LAMBDA_DEBUG_LOG: ${AWS_LAMBDA_DEBUG_LOG:-none}"

# Verify the handler file exists
if [ -f "${LAMBDA_TASK_ROOT}/${HANDLER}" ]; then
    echo "Handler file exists at ${LAMBDA_TASK_ROOT}/${HANDLER}"
else
    echo "ERROR: Handler file does not exist at ${LAMBDA_TASK_ROOT}/${HANDLER}"
    echo "Listing directory content:"
    ls -la "${LAMBDA_TASK_ROOT}/"
fi

# Check PDO support
echo "Checking PDO drivers..."
php -r "echo 'PDO drivers: ' . implode(', ', PDO::getAvailableDrivers()) . PHP_EOL;"

# Check PDO::PGSQL_ATTR_DISABLE_PREPARES
echo "Checking PDO::PGSQL_ATTR_DISABLE_PREPARES constant..."
php -r "echo 'PDO::PGSQL_ATTR_DISABLE_PREPARES defined: ' . (defined('PDO::PGSQL_ATTR_DISABLE_PREPARES') ? 'Yes' : 'No') . PHP_EOL; if(defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) { echo 'Value: ' . PDO::PGSQL_ATTR_DISABLE_PREPARES . PHP_EOL; }"

# Run PDO test script to confirm PDO PostgreSQL extension is available
echo "Running PDO PostgreSQL extension test..."
php "${LAMBDA_TASK_ROOT}/test-pdo-pgsql.php" || echo "PDO PostgreSQL test failed"

# Note about the warning
echo ""
echo "NOTE: You may see 'Module pdo_pgsql already loaded' warnings. This is normal."
echo "The segmentation fault appears to occur randomly in certain Lambda environments"
echo "when using PDO with PostgreSQL. This reproduction attempts to create conditions"
echo "where the segfault has been observed."
echo ""

# Check PHP modules and extensions for completeness
echo "Listing all loaded PHP modules:"
php -m

# Print information about PHP and system
echo "PHP Version and Configuration:"
php -i | grep -E 'PHP Version|Architecture|System|PDO drivers|PDO Support|pdo_pgsql'

# Print RIE version - not using --version flag as it's not supported
echo "Runtime Interface Emulator (RIE) is installed at:"
ls -la /usr/local/bin/aws-lambda-rie || echo "RIE not found"

# Start the Lambda Runtime Interface Emulator with additional debug flags
echo "Starting AWS Lambda Runtime Interface Emulator with debug options..."
exec /usr/local/bin/aws-lambda-rie php -d display_errors=1 -d display_startup_errors=1 -d error_reporting=E_ALL "/opt/bref/bootstrap.php"