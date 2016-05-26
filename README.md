# Forum Module

[![Build Status](https://secure.travis-ci.org/silverstripe/silverstripe-forum.png?branch=master)](http://travis-ci.org/silverstripe/silverstripe-forum)

## Maintainer Contact

 * Sean Harvey (Nickname: sharvey, halkyon) <sean (at) silverstripe (dot) com>
 * Will Rossiter (Nickname: wrossiter, willr) <will (at) silverstripe (dot) com>
 * Cam Findlay (Nickname: camfindlay) <cam (at) silverstripe (dot) com>

## Requirements

 * SilverStripe 3.1.x+

## Installation & Documentation

Please see https://github.com/silverstripe/silverstripe-forum/tree/master/docs/en

## Contributing

### Where to make pull requests

For **bug fixes** of the latest stable, please raise your non-breaking pull requests against the latest stable branch.
For example: A bug fix for 0.8.1 should be raised on the `0.8` branch.

For **new features or breaking changes** please raise these against the `master` branch.

### Translations

Translations of the natural language strings are managed through a
third party translation interface, transifex.com.
Newly added strings will be periodically uploaded there for translation,
and any new translations will be merged back to the project source code.

Please use https://www.transifex.com/projects/p/silverstripe-forum/ to contribute translations,
rather than sending pull requests with YAML files.

See the ["i18n" topic](http://doc.silverstripe.org/framework/en/trunk/topics/i18n) on doc.silverstripe.org for more details.

### Note: Refresh of master in March 2016
In March 2016, master branch was refreshed with the current state of the 0.8 stable branch in order 
to allow contributors to make pull requests that are acceptable. Previous master had got into a 
incompatible state during the move to SEMVER for SilverStripe core 3.x that was considerably broken 
and not able to accept any pull requests to progress the module. The original master is still 
available in the archive/master-mar2016 branch in case users have forked or adapted this branch.