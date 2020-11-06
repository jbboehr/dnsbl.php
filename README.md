# dnsbl.php

[![GitHub Build Status](https://github.com/jbboehr/dnsbl.php/workflows/ci/badge.svg)](https://github.com/jbboehr/dnsbl.php/actions?query=workflow%3Aci)

Simplified version of PEAR's Net_DNSBL with PEAR dependencies removed.


## Installation

With [composer](http://getcomposer.org)

```json
{
    "require": {
        "jbboehr/dnsbl": "0.1.*"
    }
}
```


## Usage

```php
$dnsbl = new \DNSBL\DNSBL(array(
    'blacklists' => array(
        'bl.spamcop.net'
    )
));
var_export($dnsbl->isListed('127.0.0.2')); echo ";\n";
var_export($dnsbl->getListingBlacklists('127.0.0.2')); echo ";\n";
```

```php
true;
array (
  0 => 'bl.spamcop.net',
);
```


## License

This project is licensed under the [PHP license](http://php.net/license/3_01.txt).
