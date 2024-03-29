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

git_branch=$(echo "${GITHUB_HEAD_REF:-$(git rev-parse --abbrev-ref HEAD)}" | sed 's/\//\\\//g')

echo "git_branch: ${git_branch}"

if [ "$git_branch" == "master" ]; then
  exit 0;
fi;

sed -i "s/\"infection\/codeception-adapter\": \"dev-master\"/\"infection\/codeception-adapter\": \"dev-${git_branch}\"/" composer.json

set -e pipefail

rm -f composer.lock
composer install

run "vendor/bin/infection"

git checkout composer.json

diff -w expected-output.txt infection.log

