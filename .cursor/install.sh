#!/usr/bin/env bash
# Idempotent Cloud Agent bootstrap for the uopz PHP extension.
# Installs the PHP build toolchain (if missing), then compiles the extension
# from source with phpize/configure/make and verifies that it loads.
set -euo pipefail

PHP_VERSION="${UOPZ_PHP_VERSION:-8.3}"

echo "==> Ensuring PHP ${PHP_VERSION} build toolchain is installed"
if ! command -v php >/dev/null 2>&1 || ! command -v phpize >/dev/null 2>&1; then
	export DEBIAN_FRONTEND=noninteractive
	sudo apt-get update -y
	sudo apt-get install -y --no-install-recommends \
		"php${PHP_VERSION}-cli" \
		"php${PHP_VERSION}-dev" \
		"php${PHP_VERSION}-opcache" \
		autoconf \
		gcc \
		make \
		pkg-config
fi

php --version
phpize --version | head -n 1

echo "==> Building the uopz extension"
# Start from a clean tree so re-runs are deterministic (idempotent).
phpize --clean >/dev/null 2>&1 || true
phpize
./configure --enable-uopz
make -j"$(nproc)"

echo "==> Verifying the freshly built extension loads"
php -d extension="$(pwd)/modules/uopz.so" -r \
	'if (!extension_loaded("uopz")) { fwrite(STDERR, "uopz failed to load\n"); exit(1); } echo "uopz ", phpversion("uopz"), " loaded OK\n";'

echo "==> uopz build complete. Run the test suite with:"
echo "    NO_INTERACTION=1 TEST_PHP_ARGS=\"-d zend_extension=\$(php-config --extension-dir)/opcache.so -d opcache.enable_cli=1\" make test"
