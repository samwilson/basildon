---
title: Basildon
subtitle: Templates
---

Templates in Basildon are all written in the [Twig](https://twig.symfony.com/) templating language.
They can output any format that's required.
Basildon provides a few variables functions, and filters for common website use cases;
these are explained on this page.

## Variables

1. `page` – An object representing the current page being rendered.
   It has the following members:
   * `page.body`: The unmodified body text,
     good for piping through filters such as `md2html` and `md2latex`.
   * `page.metadata`: All the metadata defined in the page's frontmatter.
   * `page.link(target)`: Creates a relative URL to another page.
2. `database` - The database,
   the most useful attribute of which is `database.query(sql)`.
3. `site` – The site object, mostly used to access configuration values, e.g. `site.config.title`.

## Functions

1. `commons(file_name)` – Get information about a [Wikimedia Commons](https://commons.wikimedia.org/) file.
2. `flickr(photo_id)` – Get information about a [Flickr](https://www.flickr.com/) photo.
   To use this, you need to set the `flickr.api_key` and `flickr.api_secret` values
   in your site's `config.local.yaml` file.
3. `qrcode(text)` – Returns an asset-directory path to a QR code SVG file,
   such as `/assets/8a482ae2afb51a1de85b7eb9087f7cc2.svg`.
   For example: `<img src="{{ page.link(qrcode('string')) }}" />`
4. `wikidata(qid)` – Returns information about the given [Wikidata](https://www.wikidata.org/) item.
   For example, `{{ wikidata('Q42').descriptions.en.value }}` will return something like "English writer and humorist".
   To get the full details of the returned structure,
   see e.g. [wikidata.org/wiki/Special:EntityData/Q42.json](https://www.wikidata.org/wiki/Special:EntityData/Q42.json).

## Filters and escapers

1. `md2html` – Filter markdown to HTML.
2. `md2latex` – Filter markdown to LaTeX.
3. `escape('tex')` – Escaper to use in TeX templates to escape characters that have special meaning in TeX, e.g. `{{ '$10'|e('tex') }}`.
   This is often used by wrapping the template in `{% autoescape 'tex' %}{% endautoescape %}`
