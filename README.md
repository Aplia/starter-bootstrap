# Aplia Starter Bootstrap

This package contains common code for bootstrapping eZ publish as it is used in Starter projects.
It sets up necessary global variables, loads the Composer autoloader.

It does not however start the eZ publish kernel, instead this bootstrap system should be included
in the `config.php` file as part of the project.


## Installation

Add this package to the project by running:

```
composer require aplia/starter-bootstrap
```

Then add the following lines to `config.php`:

```
// Bootstrap the system based on our configuration
$manager = require __DIR__ . '/vendor/aplia/starter-bootstrap/bootstrap.php';
// Initialize from env and global variables
$manager->configure(array(
    'wwwRoot' => __DIR__,
));
// Bootstrap the system
$manager->bootstrap();
```

## Optimizing for production

To cut down on the amount of files it needs to process during the bootstrap
when running in production mode the system can create an optimized
bootstrap file and config. This is done by running:

```
vendor/bin/bootstrap_build
```

This creates files in the `build` folder which will be used instead of
dynamically setting up the bootstrap process.
The deployment system for the site should be setup to always run
this command to get updated code and config.

The build files can be removed by running:

```
vendor/bin/bootstrap_clean
```

## Development

Adding new features to the package must be done with compatibility in mind.
It should be possible for any Starter project to update to the latest version
and still have it working.
New behaviours must only be activated using configuration (global var etc.)
which are off by default.
