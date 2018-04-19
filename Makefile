.DEFAULT_GOAL := help


.PHONY: help
help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'


##
## Commands
##---------------------------------------------------------------------------

.PHONY: clean
clean:			## Clean all created artifacts
clean:
	git clean --exclude=.idea/ -ffdx

.PHONY: cs
cs:			## Fix CS
cs: vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer
	php -d zend.enable_gc=0 vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer fix

.PHONY: compile
compile:		## Compile the application into the PHAR
compile:
	# Cleanup existing artefacts
	rm -f bin/box.phar

	# Build the PHAR
	php bin/box compile $(args)


##
## Tests
##---------------------------------------------------------------------------

.PHONY: test
test:			## Run all the tests
test: tu e2e e2e_check_requirements

.PHONY: tu
tu:			## Run the unit tests
tu: vendor/bin/phpunit fixtures/default_stub.php
	php -d zend.enable_gc=0 bin/phpunit

.PHONY: tc
tc:			## Run the unit tests with code coverage
tc: vendor/bin/phpunit
	phpdbg -qrr -d zend.enable_gc=0 bin/phpunit --coverage-html=dist/coverage --coverage-text

.PHONY: tm
tm:			## Run Infection
tm:	vendor/bin/phpunit fixtures/default_stub.php
	php -d zend.enable_gc=0 bin/infection

.PHONY: e2e
e2e:			## Run the end-to-end tests
e2e: box_dev.json
	$(MAKE) compile args='--config=box_dev.json'

	rm box.phar || true
	mv -v bin/box.phar .

	# TODO: use the build step again otherwise it is going to include the dev files
	php box.phar compile

	rm box.phar bin/box.phar


.PHONY: e2e_check_requirements
e2e_check_requirements:	## Runs the end-to-end tests for the check requirements feature
e2e_check_requirements: bin/box src vendor
	.docker/build

	bin/box compile --working-dir fixtures/check-requirements/pass-no-config/
	
	rm fixtures/check-requirements/pass-no-config/actual-output || true
	docker run -i --rm -v "$$PWD/fixtures/check-requirements/pass-no-config":/opt/box -w /opt/box box_php53 php default.phar -vvv --no-ansi > fixtures/check-requirements/pass-no-config/actual-output
	diff fixtures/check-requirements/pass-no-config/expected-output-53 fixtures/check-requirements/pass-no-config/actual-output
	
	rm fixtures/check-requirements/pass-no-config/actual-output || true
	docker run -i --rm -v "$$PWD/fixtures/check-requirements/pass-no-config":/opt/box -w /opt/box box_php72 php default.phar -vvv --no-ansi > fixtures/check-requirements/pass-no-config/actual-output
	diff fixtures/check-requirements/pass-no-config/expected-output-72 fixtures/check-requirements/pass-no-config/actual-output

	bin/box compile --working-dir fixtures/check-requirements/pass-complete/
	
	rm fixtures/check-requirements/pass-complete/actual-output || true
	docker run -i --rm -v "$$PWD/fixtures/check-requirements/pass-complete":/opt/box -w /opt/box box_php53 php default.phar -vvv --no-ansi > fixtures/check-requirements/pass-complete/actual-output
	diff fixtures/check-requirements/pass-complete/expected-output-53 fixtures/check-requirements/pass-complete/actual-output
	
	rm fixtures/check-requirements/pass-complete/actual-output || true
	docker run -i --rm -v "$$PWD/fixtures/check-requirements/pass-complete":/opt/box -w /opt/box box_php72 php default.phar -vvv --no-ansi > fixtures/check-requirements/pass-complete/actual-output
	diff fixtures/check-requirements/pass-complete/expected-output-72 fixtures/check-requirements/pass-complete/actual-output

	bin/box compile --working-dir fixtures/check-requirements/fail-complete/
	
	rm fixtures/check-requirements/fail-complete/actual-output || true
	docker run -i --rm -v "$$PWD/fixtures/check-requirements/fail-complete":/opt/box -w /opt/box box_php53 php default.phar -vvv --no-ansi > fixtures/check-requirements/fail-complete/actual-output || true
	diff fixtures/check-requirements/fail-complete/expected-output-53 fixtures/check-requirements/fail-complete/actual-output
	
	rm fixtures/check-requirements/fail-complete/actual-output || true
	docker run -i --rm -v "$$PWD/fixtures/check-requirements/fail-complete":/opt/box -w /opt/box box_php72 php default.phar -vvv --no-ansi > fixtures/check-requirements/fail-complete/actual-output || true
	diff fixtures/check-requirements/fail-complete/expected-output-72 fixtures/check-requirements/fail-complete/actual-output


.PHONY: blackfire
blackfire:		## Profiles the compile step
blackfire: bin/box src vendor
	# Cleanup existing artefacts
	rm -f bin/box.phar

	# Re-dump the loader to account for the prefixing
	# and optimize the loader
	composer install
	composer dump-autoload --classmap-authoritative

	# Profile compiling the PHAR from the source code
	blackfire --reference=1 --samples=5 run php -d zend.enable_gc=0 -d bin/box compile --quiet

	# Profile compiling the PHAR from the PHAR
	mv -fv bin/box.phar .
	blackfire --reference=2 --samples=5 run php -d zend.enable_gc=0 -d box.phar compile --quiet

	# Cleanup
	composer install
	rm box.phar


##
## Rules from files
##---------------------------------------------------------------------------

composer.lock: composer.json
	composer install

vendor: composer.lock
	composer install

vendor/bamarni: composer.lock
	composer install

vendor/bin/phpunit: composer.lock
	composer install

vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer: vendor/bamarni
	composer bin php-cs-fixer install

bin/box.phar: bin/box src vendor
	$(MAKE) compile

box_dev.json: box.json.dist
	cat box.json.dist | sed -E 's/\"key\": \".+\",//g' | sed -E 's/\"algorithm\": \".+\",//g' | sed -E 's/\"alias\": \".+\",//g' > box_dev.json

.PHONY: fixtures/default_stub.php
fixtures/default_stub.php:
	bin/generate_default_stub
