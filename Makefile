.PHONY: reinstall build test

WP_CLI = tools/wp-cli.phar
PHPUNIT = tools/phpunit.phar

reinstall: $(WP_CLI) build
	$(WP_CLI) plugin uninstall --deactivate wp-web-push --path=$(WORDPRESS_PATH)
	$(WP_CLI) plugin install --activate wp-web-push.zip --path=$(WORDPRESS_PATH)

build:
	rm -rf build wp-web-push.zip
	cp -r wp-web-push/ build/
	cp node_modules/localforage/dist/localforage.min.js build/lib/js/localforage.min.js
	cp node_modules/chart.js/Chart.min.js build/lib/js/Chart.min.js
	cp vendor/marco-c/wp-web-app-manifest-generator/WebAppManifestGenerator.php build/WebAppManifestGenerator.php
	cd build/ && zip wp-web-push.zip -r *
	mv build/wp-web-push.zip wp-web-push.zip

test: $(PHPUNIT) build
	$(PHPUNIT)

generate-pot:
	php $(WORDPRESS_REPO_PATH)/tools/i18n/makepot.php wp-plugin wp-web-push
	mv wp-web-push.pot wp-web-push/lang/web-push.pot

tools/wp-cli.phar:
	mkdir -p tools
	wget -P tools -N https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +x $(WP_CLI)

tools/phpunit.phar:
	mkdir -p tools
	wget -P tools -N https://phar.phpunit.de/phpunit-old.phar
	mv tools/phpunit-old.phar tools/phpunit.phar
	chmod +x $(PHPUNIT)

