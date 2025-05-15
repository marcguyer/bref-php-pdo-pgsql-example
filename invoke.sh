#!/bin/bash
set -e

# Detect if we're in local mode or serverless mode
if [ "$1" == "local" ]; then
  # Default service to test
  SERVICE=${2:-lambda}

  # Print service info
  echo "Testing Lambda function in local '$SERVICE' service..."

  # Use docker compose exec to invoke the Lambda function within the container
  docker compose exec "$SERVICE" \
    curl -s -X POST "http://localhost:8080/2015-03-31/functions/function/invocations" \
    -d '{}' || echo "Failed to invoke Lambda function"

  echo ""
  echo "To view logs:"
  echo "./logs.sh"
else
  # Check if the environment variables file exists
  ENV_FILE=".env"
  if [ ! -f "$ENV_FILE" ]; then
      echo "Error: .env file not found!"
      exit 1
  fi

  # Load environment variables
  source "$ENV_FILE"

  # Export the variables for serverless
  export AWS_REGION
  export VPC_SECURITY_GROUP_ID
  export VPC_SUBNET_ID
  export DB_HOST
  export DB_PORT
  export DB_NAME
  export DB_USERNAME
  export DB_PASSWORD

  # Invoke the Lambda function in AWS
  echo "Invoking Lambda function in AWS..."
  serverless invoke -f pdo-pgsql-test -d '{}'

  echo ""
  echo "To view logs:"
  echo "./logs.sh"
fi