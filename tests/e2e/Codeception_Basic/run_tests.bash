#!/usr/bin/env bash

set -e

tputx () {
	test -x $(which tput) && tput "$@"
}

run () {
    local INFECTION=${1}
    local PHPARGS=${2}

    if [ "$DRIVER" = "phpdbg" ]
    then
        phpdbg $PHPARGS -qrr $INFECTION
    else
        php $PHPARGS $INFECTION
    fi
}


cd "$(dirname "$0")"

rm -rf codeception-package
mkdir codeception-package

git_branch="$(git rev-parse --abbrev-ref HEAD)"

if [ "$git_branch" == "master" ]; then
  exit 0;
fi;

sed -i "s/\"infection\/codeception-adapter\": \"dev-master\"/\"infection\/codeception-adapter\": \"dev-master#${git_branch}\"/" composer.json

cp -r ../../../src codeception-package/src
cp ../../../composer.json codeception-package

set -e pipefail

rm -f composer.lock
composer install

run "vendor/bin/infection"

git checkout composer.json

diff -w expected-output.txt infection.log

