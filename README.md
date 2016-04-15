hexasys
=======

A PHP Framework to ease development

## What ?

This framework is the result of some years of development of a PHP server backend, started from scratch with out any external framework.

It has got several different components which ease Web application development. Here are some of the functionalities provided :

- Database connector.
- QPath utility : making SQL queries really simple, no bloat. Performances inspector : tells you which columns you retrieved but did not use, with the precise callstack involved.
- Calendar : manage periods of time in a simple way, although being very expressive (from to but not week ends and from to without mondays, ...). Periods descriptions are described in a polonese format to be parsed simply and efficiently.
- Log utility.
- CDI thing, very light.
- Secured access to back office pages, RBAC policy. Easily extensible.
- Database schema migration eased. You can in just one call upgrade your production database schema to your new development one.
- Tools to manage in DB lists and trees structures.
- I18N.
- Stored variables : you can store and load variable content to and from the disk.
- Proxy connectors : easily make calls between two or more instances of your server.
- Background jobs management.
- GWT connector (with a Java component that will be soon opensourced too).

## How to use ?

Just download the thing and add this in your code (for example in your index.php file) :

```php
// tell to HexaSys where you application folder is :
define( "APP_DIR", dirName( __FILE__ ) . '/app/' );

// include the bootstrapper file
include_once 'hexasys/hexasys.inc.php';
```

## QPath

One of the easiest components to deal with is QPath. It allows you to make powerful queries without writing any SQL. It is designed to solve 90% of the boring-to-write SQL queries. For the 10% left, just continue to make native SQL queries...

Soon to be continued...
