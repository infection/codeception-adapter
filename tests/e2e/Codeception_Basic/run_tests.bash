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

set -e pipefail

rm -f composer.lock
composer install

run "vendor/bin/infection"

diff -w expected-output.txt var/infection.log

