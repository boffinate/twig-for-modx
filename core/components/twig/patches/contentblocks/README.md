_These changes have been submitted for inclusion in ContentBlocks, so hopefully soon you will not have to manually patch it._


## Modify `repeaterinput.class.php`
Find
```php
        if (empty($wrapperTpl)) $wrapperTpl = '[[+rows]]';
        $data['rows'] = $rowsOutput;
```

and replace it with:
```php
        if (empty($wrapperTpl)) $wrapperTpl = '[[+rows]]';
        // TODO Keep the raw row data
        $data['row_data'] = $data['rows'];
        $data['rows'] = $rowsOutput;
```

Now choose one of the following:

## Option 1: proper but more difficult

### Create a new system event called `OnContentBlocksParse`

### Modify `contentblocks.class.php`
Find the lines
```php
        // Support for pdoTools @FILE syntax. Using @PDO_FILE to prevent conflicts with regular @FILE syntax.
        if ((strpos(trim($tpl), '@PDO_FILE ') === 0) && $this->pdoTools()) {
            // Replace @PDO_FILE with @FILE so pdoTools can handle it.
            $tpl = str_replace('@PDO_FILE ', '@FILE ', $tpl);
            return $this->pdoTools()->getChunk($tpl, $phs);
        }
```

Just above them, insert this if you created a new system event:
```php
        $tpl = $this->modx->invokeEvent('OnContentBlocksParse', ['tpl' => $tpl, 'phs' => $phs]);
```

### Create a plugin to invoke Twig
Create a plugin that listens to the event `OnContentBlocksParse` and give it the contents:
```php
        /** @var \Boffinate\Twig\Twig $twig */
        $twig = $this->modx->services->get('twigparser');
        $tpl = $twig->renderString($tpl, $phs);
```

## Option 2: quick 'n dirty

### Modify `contentblocks.class.php`
Find the lines
```php
        // Support for pdoTools @FILE syntax. Using @PDO_FILE to prevent conflicts with regular @FILE syntax.
        if ((strpos(trim($tpl), '@PDO_FILE ') === 0) && $this->pdoTools()) {
            // Replace @PDO_FILE with @FILE so pdoTools can handle it.
            $tpl = str_replace('@PDO_FILE ', '@FILE ', $tpl);
            return $this->pdoTools()->getChunk($tpl, $phs);
        }
```

Just above them, insert:
```php
        /** @var \Boffinate\Twig\Twig $twig */
        $twig = $this->modx->services->get('twigparser');
        $tpl = $twig->renderString($tpl, $phs);
```
