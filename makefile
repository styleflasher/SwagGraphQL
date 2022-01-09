#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

test: ## Starts all Tests
	php vendor/bin/phpunit --configuration=phpunit.xml

stan: ## Starts the PHPStan Analyser //@TODO: --coverage-html coverage
	@php vendor/bin/phpstan --memory-limit=150M analyze --level 5 --configuration phpstan.neon --autoload-file=../../../vendor/autoload.php .

dev: ## Setup plugin dependencies
	composer install
	npm install ./src/Resources

build: ## zip plugin
	@cd ./../../../ && ./psh.phar administration:build
	@cd ../ && zip -r SUTProductListingSorter.zip ./SUTProductListingSorter -x "*/node_modules/*" "*/vendor/*" "*/.*/*"

