# See https://tech.davis-hansson.com/p/make/
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

.DEFAULT_GOAL := help

.PHONY: help
help:
	@printf "\033[33mUsage:\033[0m\n  make TARGET\n\n\033[32m#\n# Commands\n#---------------------------------------------------------------------------\033[0m\n\n"
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | awk 'BEGIN {FS = ":"}; {printf "\033[33m%s:\033[0m%s\n", $$1, $$2}'


TOOL_DOWNLOAD_DIR := var/download
TOOL_DIR := var/tools

# PHP CS Fixer
PHP_CS_FIXER := $(TOOL_DIR)/php-cs-fixer.phar
PHP_CS_FIXER_VERSION := .tools/php-cs-fixer-version
PHP_CS_FIXER_GPG_KEY := E82B2FB314E9906E
TMP_PHP_CS_FIXER := $(TOOL_DOWNLOAD_DIR)/$(shell basename $(PHP_CS_FIXER))
TMP_PHP_CS_FIXER_SIGNATURE := $(TMP_PHP_CS_FIXER).asc
PHP_CS_FIXER_URL := "https://github.com/FriendsOfPHP/PHP-CS-Fixer/releases/download/$(shell cat $(PHP_CS_FIXER_VERSION))/php-cs-fixer.phar"
PHP_CS_FIXER_URL_SIGNATURE := "https://github.com/FriendsOfPHP/PHP-CS-Fixer/releases/download/$(shell cat $(PHP_CS_FIXER_VERSION))/php-cs-fixer.phar.asc"

# PHPUnit
PHPUNIT=vendor/bin/phpunit
PHPUNIT_COVERAGE_CLOVER=--coverage-clover=build/logs/clover.xml
PHPUNIT_ARGS=--coverage-xml=build/logs/coverage-xml --log-junit=build/logs/junit.xml $(PHPUNIT_COVERAGE_CLOVER)

# PHPStan
PHPSTAN=vendor/bin/phpstan
PHPSTAN_ARGS=analyse src tests/phpunit -c .phpstan.neon

# Infection
INFECTION=./.tools/infection.phar
INFECTION_URL="https://github.com/infection/infection/releases/download/0.27.4/infection.phar"
MIN_MSI=78
MIN_COVERED_MSI=82
INFECTION_ARGS=--min-msi=$(MIN_MSI) --min-covered-msi=$(MIN_COVERED_MSI) --threads=max --log-verbosity=none --no-interaction --no-progress --show-mutations

.PHONY: all
all:	 ## Executes all checks
all: cs-lint test

.PHONY: cs
cs:	 ## Apply CS fixes
cs: gitignore composer-validate php-cs-fixer

.PHONY: cs-lint
cs-lint: ## Run CS checks
cs-lint: composer-validate php-cs-fixer-lint

.PHONY: gitignore
gitignore:
	LC_ALL=C sort -u .gitignore -o .gitignore

.PHONY: composer-validate
composer-validate: vendor/autoload.php
	composer validate --strict

.PHONY: php-cs-fixer
php-cs-fixer: $(PHP_CS_FIXER) vendor/autoload.php
	$(PHP_CS_FIXER) fix --verbose --diff

.PHONY: php-cs-fixer-lint
php-cs-fixer-lint: $(PHP_CS_FIXER) vendor/autoload.php
	$(PHP_CS_FIXER) fix --verbose --diff --dry-run
	composer validate --strict

.PHONY: test
test:	 ## Executes the tests
test: phpstan test-unit infection test-e2e

.PHONY: phpstan
phpstan:
	$(PHPSTAN) $(PHPSTAN_ARGS) --no-progress

.PHONY: test-unit
test-unit: vendor/autoload.php
	$(PHPUNIT) $(PHPUNIT_ARGS)

.PHONY: test-e2e
test-e2e: vendor/autoload.php
	tests/e2e_tests

.PHONY: infection
infection: $(INFECTION)
	$(INFECTION) $(INFECTION_ARGS)

# Do install if there's no 'vendor'
vendor/autoload.php:
	composer install --prefer-dist

# If composer.lock is older than `composer.json`, do update,
# and touch composer.lock because composer not always does that
composer.lock: composer.json
	composer update
	touch -c $@


$(INFECTION): Makefile
	wget -q $(INFECTION_URL) --output-document=$(INFECTION)
	chmod a+x $(INFECTION)
	touch $@

$(PHP_CS_FIXER): $(PHP_CS_FIXER_VERSION)
	$(MAKE) $(TMP_PHP_CS_FIXER)

	mkdir -p $(TOOL_DOWNLOAD_DIR)
	mv $(TMP_PHP_CS_FIXER) $@
	touch -c $@

$(TMP_PHP_CS_FIXER): $(PHP_CS_FIXER_VERSION)
	mkdir -p $(TOOL_DOWNLOAD_DIR)
	wget --quiet $(PHP_CS_FIXER_URL) --output-document=$@
	wget --quiet $(PHP_CS_FIXER_URL_SIGNATURE) --output-document=$(TMP_PHP_CS_FIXER_SIGNATURE)

	@echo "Importing GPG key..."
	@gpg --keyserver hkps://keys.openpgp.org --recv-keys $(PHP_CS_FIXER_GPG_KEY) >/dev/null 2>&1 || \
		(echo "ERROR: Failed to import GPG key" && rm -f $@ $(TMP_PHP_CS_FIXER_SIGNATURE) && exit 1)
	@echo "Verifying signature..."
	@gpg --verify $(TMP_PHP_CS_FIXER_SIGNATURE) $@ >/dev/null 2>&1 || \
		(echo "ERROR: Signature verification FAILED!" && rm -f $@ $(TMP_PHP_CS_FIXER_SIGNATURE) && exit 1)
	@echo "✓ Signature verification passed"
	rm -f $(TMP_PHP_CS_FIXER_SIGNATURE)

	chmod a+x $@
	PHP_CS_FIXER_IGNORE_ENV=1 $@ --version
