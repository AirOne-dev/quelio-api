#!/bin/bash

# Run PHPUnit tests via Docker
# Usage: ./run-tests.sh [options]
# Options:
#   --filter <pattern>  Run specific test(s)
#   --coverage          Generate coverage report
#   --unit              Run only unit tests
#   --integration       Run only integration tests

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}   PHPUnit Test Suite Runner${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}Error: Docker is not running${NC}"
    echo "Please start Docker and try again"
    exit 1
fi

# Parse arguments
PHPUNIT_ARGS=""
SUITE=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --filter)
            PHPUNIT_ARGS="--filter=$2"
            shift 2
            ;;
        --coverage)
            PHPUNIT_ARGS="$PHPUNIT_ARGS --coverage-html coverage"
            shift
            ;;
        --unit)
            SUITE="Unit"
            shift
            ;;
        --integration)
            SUITE="Integration"
            shift
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

# Build test suite argument
if [ -n "$SUITE" ]; then
    PHPUNIT_ARGS="$PHPUNIT_ARGS --testsuite=$SUITE"
fi

echo -e "${YELLOW}Installing dependencies...${NC}"

# Install Composer dependencies
docker run --rm \
    -v "$(pwd):/app" \
    -w /app \
    composer:latest \
    install --no-interaction --quiet

echo -e "${GREEN}✓ Dependencies installed${NC}"
echo ""
echo -e "${YELLOW}Running tests...${NC}"
echo ""

# Run PHPUnit
docker run --rm \
    -v "$(pwd):/app" \
    -w /app \
    php:8.2-cli \
    ./vendor/bin/phpunit --testdox $PHPUNIT_ARGS

TEST_EXIT_CODE=$?

echo ""
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Tests failed${NC}"
fi

echo ""

exit $TEST_EXIT_CODE
