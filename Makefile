.PHONY: ci test prerequisites

# Use any most recent PHP version
PHP=$(shell which php)

# Default parallelism
JOBS=$(shell nproc)

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

# Composer
COMPOSER=$(PHP) $(shell which composer)

# Infection
INFECTION=./.tools/infection.phar
INFECTION_URL="https://github.com/infection/infection/releases/download/0.27.4/infection.phar"
MIN_MSI=78
MIN_COVERED_MSI=82
INFECTION_ARGS=--min-msi=$(MIN_MSI) --min-covered-msi=$(MIN_COVERED_MSI) --threads=$(JOBS) --log-verbosity=none --no-interaction --no-progress --show-mutations

all: test

cs: $(PHP_CS_FIXER)
	$(PHP_CS_FIXER) fix -v --diff --dry-run
	LC_ALL=C sort -u .gitignore -o .gitignore

phpstan:
	$(PHPSTAN) $(PHPSTAN_ARGS) --no-progress

test-unit:
	$(PHPUNIT) $(PHPUNIT_ARGS)

test-e2e: $(INFECTION)
	tests/e2e_tests

infection: $(INFECTION)
	$(INFECTION) $(INFECTION_ARGS)

##############################################################
# Development Workflow                                       #
##############################################################

test: phpunit analyze composer-validate

.PHONY: composer-validate
composer-validate: test-prerequisites
	$(COMPOSER) validate --strict

test-prerequisites: prerequisites composer.lock

phpunit: cs-fix
	$(PHPUNIT) $(PHPUNIT_ARGS) --verbose
	cp build/logs/junit.xml build/logs/phpunit.junit.xml
	$(PHP) $(INFECTION) $(INFECTION_ARGS)

analyze: cs-fix
	$(PHPSTAN) $(PHPSTAN_ARGS)
	$(PSALM) $(PSALM_ARGS)

cs-fix: test-prerequisites
	$(PHP_CS_FIXER) fix -v --diff
	LC_ALL=C sort -u .gitignore -o .gitignore

##############################################################
# Prerequisites Setup                                        #
##############################################################

# We need both vendor/autoload.php and composer.lock being up to date
.PHONY: prerequisites
prerequisites: build/cache vendor/autoload.php composer.lock infection.json.dist .phpstan.neon

# Do install if there's no 'vendor'
vendor/autoload.php:
	$(COMPOSER) install --prefer-dist

# If composer.lock is older than `composer.json`, do update,
# and touch composer.lock because composer not always does that
composer.lock: composer.json
	$(COMPOSER) update && touch composer.lock

build/cache:
	mkdir -p build/cache

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
