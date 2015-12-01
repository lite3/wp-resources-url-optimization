#!/bin/bash

set -e

echo "this is deplywp"

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
GIT_DIR="$DIR/.."
TRUNK_DIR="$GIT_DIR/../../svntmp/trunk"
SVN_REPOSITORY_URL="http://plugins.svn.wordpress.org/wp-resources-url-optimization"
SVN_AUTH="--username $SVN_USERNAME --password $SVN_PASSWORD --no-auth-cache"

SVN="/usr/bin/svn"

echo "cur branch is $TRAVIS_BRANCH"
git branch


# checkout svn repository to svntmp
$SVN checkout $SVN_REPOSITORY_URL "$TRUNK_DIR/.."

# TRAVIS_BRANCH

# print branch type: tag or branch
branchtype() {
    for t in `git tag`; do
        if [ "$1"x = "$t"x ]; then
            echo "tag"
            return 0
        fi
    done
    echo "branch"
}

# move current git branch to svn dir
# @param dst
move2svn() {
    cd "$1"
    $SVN delete ./*
    cp -rf $GIT_DIR .
    rm -rf .git
    rm -rf .travis.yml
    rm -rf travis-scripts
    $SVN add --force .
    cd -
}

# deply to tag
deploywptag() {
    echo "is tag $TRAVIS_BRANCH"
    # copy tag to trunk
    move2svn "$TRUNK_DIR"
    cd "$TRUNK_DIR"
    $SVN commit --username $SVN_USERNAME --password $SVN_PASSWORD --no-auth-cache -m "auto deploy from deplywp" .
    cd -
    $SVN copy --username $SVN_USERNAME --password $SVN_PASSWORD --no-auth-cache $SVN_REPOSITORY_URL/trunk $SVN_REPOSITORY_URL/tags/$TRAVIS_BRANCH -m 'auto deploy by deplywp'
}

# deploy to assets
deploywpassets() {
    echo "this is deploywpassets"
    cd "$TRUNK_DIR/../assets"
    $SVN commit --username $SVN_USERNAME --password $SVN_PASSWORD --no-auth-cache -m "auto deploy from git" .
}

if [[ "$TRAVIS_BRANCH"x == 'assets' ]]; then
    deploywpassets
elif [[ "$(branchtype $TRAVIS_BRANCH)"x == 'tag'x ]]; then
    deploywptag
fi

# check current branch is tag
# typename=$(branchtype $TRAVIS_BRANCH)
# echo "$TRAVIS_BRANCH is $typename"
# if [[ "$typename"x == "tag"x ]]; then
#     deploywptag
# elif [[ "$typename"x == "branch"x ]]; then
#         #statements
# fi

# istag '0.1'
# if [[ $? -eq 0 ]]; then
#     echo '0.1 is tag'
# fi

# istag '5'
# if [[ $? -eq 0 ]]; then
#     echo '5 is tag'
# fi