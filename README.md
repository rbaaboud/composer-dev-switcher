# composer-dev-switcher

PHP CLI script to easily switch some vendor to local @dev version

## Usage

``` bash
$ php composer-dev-switcher.php vendor/name ../relative/path/to/repository
```

This will update and write in composer.json file with this kind of diff:

```
--- a/composer.json
+++ b/composer.json

...

     "description": "Some project description...",
+    "repositories": [
+        {
+            "type": "path",
+            "url": "../relative/path/to/repository/"
+        }
+    ],

...

     "require": [
         "some-other/vendor-bar": "^1.0",
-        "vendor/name": "^1.0",
+        "vendor/name": "@dev",
         "some-other/vendor": "^2.0",
         "some-other/vendor-foo": "^2.0",

...

```

## Help

``` bash
$ php composer-dev-switcher.php --help
```
