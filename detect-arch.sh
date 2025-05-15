#!/bin/bash
# Detect architecture and set appropriate Docker configurations
# Use: source ./detect-arch.sh

# Detect architecture
ARCH=$(uname -m)

# Set appropriate Docker configurations based on architecture
if [ "$ARCH" = "arm64" ]; then
    echo "Detected ARM64 architecture"
    
    # ARM64-specific settings (Apple Silicon)
    export LAMBDA_DOCKERFILE=${LAMBDA_DOCKERFILE:-"Dockerfile"}
    export LAMBDA_PLATFORM=${LAMBDA_PLATFORM:-"linux/arm64"}
    
    # Honor force flag if set
    if [ "${LAMBDA_FORCE_X86:-false}" = "true" ]; then
        echo "Forcing x86_64 emulation on ARM64"
        export LAMBDA_DOCKERFILE="Dockerfile.x86"
        export LAMBDA_PLATFORM="linux/amd64"
    fi
else
    echo "Detected x86_64 architecture"
    
    # x86_64-specific settings (Intel)
    export LAMBDA_DOCKERFILE=${LAMBDA_DOCKERFILE:-"Dockerfile.x86"}
    export LAMBDA_PLATFORM=${LAMBDA_PLATFORM:-"linux/amd64"}
fi

echo "Configuration:"
echo "LAMBDA_DOCKERFILE = $LAMBDA_DOCKERFILE"
echo "LAMBDA_PLATFORM = $LAMBDA_PLATFORM"

echo ""
echo "To use these settings with Docker Compose:"
echo "source ./detect-arch.sh && docker compose up -d"