#!/bin/sh
set -eu

# Entrypoint for API Gateway Lambda container using Bref dev server
echo "Starting Lambda container with Bref API Gateway emulation..."

# Install Composer dependencies if they don't exist - do this first, before any other operations
if [ ! -d "/var/task/vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
else
    echo "Composer dependencies already installed."
fi

# Set up environment variables
export LAMBDA_TASK_ROOT=/var/task
export HANDLER=${HANDLER}

# Print configuration info
echo "Environment configuration:"
echo "DB_HOST: ${DB_HOST}"
echo "DB_PORT: ${DB_PORT}"
echo "DB_NAME: ${DB_NAME}"
echo "DB_USERNAME: ${DB_USERNAME}"
echo "HANDLER: ${HANDLER}"

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

# Start Bref API Gateway emulation with the specified handler
echo "Starting Bref API Gateway emulation for ${HANDLER}..."
cd ${LAMBDA_TASK_ROOT}

# Run the bref-dev-server with the specified function name (derived from handler filename)
FUNCTION_NAME=$(echo "${HANDLER}" | sed 's/\.php$//' | sed 's/-/_/g')
echo "Using function name: ${FUNCTION_NAME}"

# Execute bref-dev-server
exec vendor/bin/bref-dev-server run