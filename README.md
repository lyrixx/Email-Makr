Email-Makr
==========

With *Email-Makr* you design only one twig template and
a csv with translated block. Then *Email-Makr* will generate
all variants for your email.

See `exemple` for more information.

Usage
-----

`emailmakr template.html.twig datas.csv`

Options:

* `--output-directory`, default: `./emailings/`
* `--output-format`, default: `"mail_LANG.html`. `LANG` is a placeholder.
  It will be remplaced by the current language
