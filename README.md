# phpackage-json
Bundle Javascript &amp; Stylesheets from package.json (Yarn) with PHP.

```
$ composer require wbadrh/phpackage-json
```
Example: https://github.com/wbadrh/phpackage-json-example

```php
require __DIR__ . '/../vendor/autoload.php';

$assets = new PHPackage(
    __DIR__ . '/../package.json',  // yarn package
    __DIR__ . '/../node_modules/', // yarn vendor
    [
        // custom css
        __DIR__ . '/src/css/*'     // user stylesheets
    ],
    [
        // custom js
        __DIR__ . '/src/js/*'      // user javascript
    ]
);

$assets->fonts(__DIR__ . '/fonts');
```

```html
<link href="<?= $assets->css(__DIR__, '/css/bundle.min.css') ?>" rel="stylesheet">
```

```html
<script src="<?= $assets->js(__DIR__, '/js/bundle.min.js') ?>"></script>
```
