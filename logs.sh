#!/bin/bash
set -e

# Detect if we're in local mode or serverless mode
if [ "$1" == "local" ]; then
  # Default service to test
  SERVICE=${2:-lambda}

  # Print service info
  echo "Fetching logs for local '$SERVICE' service..."

  # Use docker compose logs to fetch the Lambda container logs
  docker compose logs "$SERVICE"
else
  # Check if the environment variables file exists
  ENV_FILE=".env"
  if [ ! -f "$ENV_FILE" ]; then
      echo "Error: .env file not found!"
      exit 1
  fi

  # Load environment variables
  # shellcheck source=.env
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

  # Fetch the Lambda function logs
  echo "Fetching Lambda function logs from AWS..."
  serverless logs -f pdo-pgsql-test
fi