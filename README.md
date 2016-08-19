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
$manager = new Aplia\Bootstrap\Manager();
$manager->bootstrap(__DIR__);
```

## Development

Adding new features to the package must be done with compatibility in mind.
It should be possible for any Starter project to update to the latest version
and still have it working.
New behaviours must only be activated using configuration (global var etc.)
which are off by default.
