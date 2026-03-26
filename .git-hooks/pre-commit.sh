#!/usr/bin/env bash

# Thanks to https://github.com/prism-php/prism for this

_header() {
    printf "\e[38;5;4m%s\e[0m\n" "$1"
}

main() {
    files_before_format=$(git diff --name-only --diff-filter=d)

    if [[ ! -x vendor/bin/pint ]]; then
        _header "pint is not installed, skipping..."
        exit 0
    fi

    _header "Running pint..."
    vendor/bin/pint --dirty

    files_after_format=$(git diff --name-only --diff-filter=d)

    # Find files fixed by pint by comparing file lists before and after pint run
    files_fixed_by_format=$(comm -13 <(sort <<<"$files_before_format") <(sort <<<"$files_after_format"))

    # Re-stage files fixed by pint
    _header "Re-staging changed files..."
    for f in $files_fixed_by_format; do echo "Re-staging: $f"; done || exit
    for f in $files_fixed_by_format; do git add "$f"; done || exit
}

main "$@"
