# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

.DEFAULT_GOAL := help

.PHONY: help
help:
	@printf "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'

INFECTION=vendor/bin/infection
MIN_MSI=68
MIN_COVERED_MSI=97


.PHONY: check
check: 	 ## Runs all the checks
check: test

.PHONY: test
test:	 ## Executes the tests
test: codecept infection

.PHONY: codecept
codecept: vendor/autoload.php
	vendor/bin/codecept run

.PHONY: codecept_coverage
codecept_coverage: 	## Executes the tests with code coverage
codecept_coverage: vendor/autoload.php
	XDEBUG_MODE=coverage vendor/bin/codecept run \
		--coverage-phpunit=coverage-xml \
		--xml=junit.xml

.PHONY: infection
infection: vendor/autoload.php
	$(INFECTION) \
		--min-msi=$(MIN_MSI) \
		--min-covered-msi=$(MIN_COVERED_MSI) \
		--threads=max \
		--show-mutations \
		--test-framework="codeception"
	diff --ignore-all-space expected-output.txt var/infection.log

# Do install if there's no 'vendor'
vendor/autoload.php:
	composer install
	touch -c $@
	test -d vendor/infection/infection/src/StreamWrapper/ && rm -fr vendor/infection/infection/src/StreamWrapper/ && $(COMPOSER) dump-autoload || true

# If composer.lock is older than `composer.json`, do update,
# and touch composer.lock because composer not always does that
composer.lock: composer.json
	composer update
	touch -c $@
