#!/bin/bash
# This script will test if you have given a leap year or not.

function bump {
	version=${VERSION}
	search='("version":[[:space:]]*").+(")'
	replace="\1${version}\2"

	sed -i ".tmp" -E "s/${search}/${replace}/g" "$1"
	rm "$1.tmp"
}

function help {
	echo "Usage: $(basename $0) [<newversion>]"
}

if [ -z "$1" ] || [ "$1" = "help" ]; then
	help
	exit
fi

DIR=`pwd -P`
VERSION=$1

cd "$DIR" && git checkout main && git pull

cd "$DIR" && composer cs-fix
cd "$DIR" && composer update --lock --no-install
cd "$DIR" && composer test

bump "$DIR/composer.json"

cd "$DIR" && git add .
cd "$DIR" && git commit -m "Bump to ${VERSION}."
cd "$DIR" && git push

#cd "$DIR" && git checkout release/0.1.x && git pull
#cd "$DIR" && git merge master -m "Bump to ${VERSION}."
#cd "$DIR" && git push
cd "$DIR" && git tag -a "${VERSION}" -m "${VERSION}"
cd "$DIR" && git push --tags
cd "$DIR" && git checkout master && git pull
