Email-Makr
==========

With *Email-Makr* you design only one twig template and
a csv with translated block. Then *Email-Makr* will generate
all variants for your email.

*Email-Makr* takes as first argument a path to a twig template.
It could be any valid twig template. For exemple:

```
<li>lang : {{ lang }}</li>
<li>var1 : {{ var1 }}</li>
<li>var1 : {{ var2 }}</li>
```

*Email-Makr* takes as second argument a path to a csv file.
The csv file should contains a least :

* One header row, with variable name. Theses variable names
 will be uses in twig template
* Many row, with in the first column the target language. For exemple:

<table>
    <tr>
        <td></td>
        <td>var1</td>
        <td>var2</td>
    </tr>
    <tr>
        <td>en</td>
        <td>value en 1</td>
        <td>value en 2</td>
    </tr>
    <tr>
        <td>fr</td>
        <td>value fr 1</td>
        <td>value fr 2</td>
    </tr>
</table>

See `exemple` for more information.

Usage
-----

`emailmakr template.html.twig datas.csv`

Options:

* `--output-directory`, default: `./emailings/`
* `--output-format`, default: `"mail_LANG.html`. `LANG` is a placeholder.
  It will be remplaced by the current language

Ouput looks for:

```
$ ./emailmakr.php generate-email index.html.twig datas.csv
Generated "/var/www/dev/sensio/emailings/exemple/emailings/mail_fr.html"
Generated "/var/www/dev/sensio/emailings/exemple/emailings/mail_en.html"
Generated "/var/www/dev/sensio/emailings/exemple/emailings/mail_es.html"
Generated "/var/www/dev/sensio/emailings/exemple/emailings/mail_it.html"
Finished
```

Installation
------------

### Use it as a single file:

Downlaod [email-makr](https://raw.github.com/lyrixx/Email-Makr/master/build/emailmakr.php)
and run it: `php email-makr`.

### With Composer:

`composer create-project lyrixx/email-makr email-makr`
