.PHONY: help test test-unit test-integration test-filter test-coverage install

# Colors
BLUE := \033[0;34m
GREEN := \033[0;32m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)Quel.io API - Test Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ""

install: ## Install dependencies via Composer
	@docker run --rm -v "$$(pwd):/app" -w /app composer:latest install

test: ## Run all tests
	@./run-tests.sh

test-unit: ## Run only unit tests
	@./run-tests.sh --unit

test-integration: ## Run only integration tests
	@./run-tests.sh --integration

test-filter: ## Run specific test (usage: make test-filter FILTER=TestName)
	@./run-tests.sh --filter=$(FILTER)

test-coverage: ## Generate test coverage report
	@./run-tests.sh --coverage
	@echo ""
	@echo "$(GREEN)Coverage report generated in coverage/index.html$(NC)"

clean: ## Clean test artifacts
	@rm -rf vendor/ .phpunit.cache/ coverage/
	@echo "$(GREEN)✓ Cleaned test artifacts$(NC)"
