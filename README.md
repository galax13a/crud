# Starcho CRUD Generator Laravel / Livewire v2~ 

Modificado por Cristhian Ryu / DEV

A livewire CRUD Generation package to help scaffold basic site files. Package is autoloaded as per PSR-4 autoloading in any laravel version `^5.6` so no extra config required. However is has been tested on version `^7 & ^8 ~9 ~10`. It uses ***auth*** middleware thus installs `laravel/ui` just incase you don't have any other auth mechanism, this does not mean you have to use `laravel/ui`.

## Documentation >> Starcho V1.0.2

More detailed documentation can ne found at :
livewire v2
```bash
composer require livewire/livewire:^2.12
```
## Installation StarchoCRUD

You can install the package via [Composer](https://getcomposer.org/):

```bash
composer require galax13a/crud
```
## Otros Paquetes Usados
cybercog/laravel-ban
```bash
composer require cybercog/laravel-ban
```
laravel/folio
```bash
composer require laravel/folio
```
laravel/socialite
```bash
composer require laravel/socialite
```


## Usage

After running `composer require galax13a/crud` command just run:

```bash
php artisan crud:install
```
**This command will perfom below actions:

    * Compile css/js based on `bootstrap and bootstrap-icons`.
    * Run `npm install && run build`
    * Flush *node_modules* files from your folder.

If you choose to scaffold authentication this command will run `php artisan ui:auth`
to generate Auth scaffolds using `laravel/ui` package. You can skip this step if your app has authentication already.

Then generate Crud by:

```bash
php artisan crud:generate {table-name}
```
**This command will generate:

    * Livewire Component.
    * Model.
    * Views.    
    * Factory.
    
**Remember to customise your genertaed factories and migrations if you need to use them later

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email george@flightsadmin.com instead of using the issue tracker.

## Credits

- [George Chitechi](https://github.com/flightsadmin)
- [Cristhian Ryu](https://github.com/galax13a)
- [All Contributors](../../contributors)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.