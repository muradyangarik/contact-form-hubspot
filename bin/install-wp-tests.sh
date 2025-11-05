#!/bin/bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION == 'latest' ]]; then
	# Get the latest WordPress version
	WP_VERSION=$(download https://api.wordpress.org/core/version-check/1.7/ /dev/stdout | head -n 4 | tail -n 1)
fi

WP_TESTS_TAG="tags/$WP_VERSION"

# Set up the WordPress testing environment.
if [ ! -d $WP_TESTS_DIR ]; then
	# Download the WordPress testing framework.
	mkdir -p $WP_TESTS_DIR
	svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
	svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
fi

# Download the WordPress core files.
if [ ! -d $WP_CORE_DIR ]; then
	mkdir -p $WP_CORE_DIR
	download https://wordpress.org/wordpress-$WP_VERSION.tar.gz /tmp/wordpress.tar.gz
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR
fi

# Create the wp-config.php file.
download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php $WP_TESTS_DIR/wp-tests-config.php
sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" $WP_TESTS_DIR/wp-tests-config.php
sed -i "s/youremptytestdbnamehere/$DB_NAME/" $WP_TESTS_DIR/wp-tests-config.php
sed -i "s/yourusernamehere/$DB_USER/" $WP_TESTS_DIR/wp-tests-config.php
sed -i "s/yourpasswordhere/$DB_PASS/" $WP_TESTS_DIR/wp-tests-config.php
sed -i "s|localhost|${DB_HOST}|" $WP_TESTS_DIR/wp-tests-config.php

# Create the database.
mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" 2>/dev/null || true

echo "WordPress test environment installed successfully!"



