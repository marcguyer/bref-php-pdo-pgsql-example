#!/bin/bash
set -e

# Check if the environment variables file exists
ENV_FILE=".env"
if [ ! -f "$ENV_FILE" ]; then
    echo "Error: .env file not found!"
    echo "Please create a .env file with the following variables:"
    echo "AWS_REGION"
    echo "VPC_SECURITY_GROUP_ID"
    echo "VPC_SUBNET_ID"
    echo "DB_HOST"
    echo "DB_PORT"
    echo "DB_NAME"
    echo "DB_USERNAME"
    echo "DB_PASSWORD"
    exit 1
fi

# Load environment variables
source "$ENV_FILE"

# Validate required variables
REQUIRED_VARS=("AWS_REGION" "VPC_SECURITY_GROUP_ID" "VPC_SUBNET_ID" "DB_HOST" "DB_USERNAME" "DB_PASSWORD")
for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        echo "Error: $var is not set in the .env file"
        exit 1
    fi
done

echo "Building and deploying..."

# Use the standard composer image to install dependencies
# The platform config in composer.json ensures PHP 8.2 compatibility
echo "Installing dependencies..."
docker run --rm \
    -v "$(pwd):/app" \
    composer:2 \
    install --no-dev --optimize-autoloader --ignore-platform-req=ext-pdo_pgsql

echo "Deploying to AWS Lambda in region ${AWS_REGION}..."
echo "Targeting PostgreSQL database at ${DB_HOST}..."

# Pass environment variables to serverless command line
export AWS_REGION
export VPC_SECURITY_GROUP_ID
export VPC_SUBNET_ID
export DB_HOST
export DB_PORT
export DB_NAME
export DB_USERNAME
export DB_PASSWORD

# Deploy using Serverless Framework
serverless deploy

echo "Deployment complete!"
echo "To invoke the function:"
echo "serverless invoke -f pdo-pgsql-test -d '{}'"
echo ""
echo "To view logs:"
echo "serverless logs -f pdo-pgsql-test"