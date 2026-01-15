#!/bin/bash

# Test runner script for TimeCalculator tests
# Uses Docker to run PHP tests in isolated environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo ""
echo "=========================================="
echo "Running TimeCalculator Tests via Docker"
echo "=========================================="
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}Error: Docker is not running${NC}"
    echo "Please start Docker and try again"
    exit 1
fi

# Run tests using PHP Docker image
echo -e "${YELLOW}Starting PHP container...${NC}"
docker run --rm \
    -v "$(pwd)/api:/app" \
    -w /app \
    php:8.2-cli \
    php tests/TimeCalculatorTest.php

TEST_EXIT_CODE=$?

echo ""
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Tests failed${NC}"
fi

exit $TEST_EXIT_CODE
