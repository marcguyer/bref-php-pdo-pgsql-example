#!/bin/bash
set -e

# Detect if we're in local mode or serverless mode
if [ "$1" == "local" ]; then
  # Default service to test
  SERVICE=${2:-lambda}
  
  # Determine the invocation type based on service name
  INVOCATION_TYPE="Function URL"
  if [[ "$SERVICE" == *"-api"* ]]; then
    INVOCATION_TYPE="API Gateway"
  fi
  
  # Print service info
  echo "Testing Lambda function in local '$SERVICE' service via HTTP $INVOCATION_TYPE..."
  
  # Use the curlimages/curl container to send a request to the service
  docker run --rm --network bref-php-pdo-pgsql-example_default curlimages/curl -s -X POST "http://$SERVICE:8000/" -d '{}'

  echo ""
  echo "To view logs:"
  echo "./logs.sh local $SERVICE"
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
  
  # Note: You can also access the function via its URL after deployment with:
  # serverless info --verbose | grep -A 1 "pdo-pgsql-test" | grep "URL:" | awk '{print $2}'

  echo ""
  echo "To view logs:"
  echo "./logs.sh"
fi