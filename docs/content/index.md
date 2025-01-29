---
title: Basildon
subtitle: A static website generator
---

**Basildon** is a simple static website generator
written in PHP and supporting Markdown content, Twig templates,
SQLite, and outputs of HTML and PDF (via LaTeX).

This documentation is also available as [a PDF](basildon-docs.pdf).

## Quick start

Prerequisites: [PHP](https://www.php.net/) (version 7.4 or higher) and [Composer](https://getcomposer.org/).

1. `composer create-project samwilson/basildon-skeleton mysite`
2. `cd mysite`
3. `./vendor/bin/basildon build .`
4. Edit files in the `content/` and `templates/` directories (for more details, see below).

## Content

Content goes in the `content/` directory, in whatever structure is required.
Each file comprises two parts:

* a frontmatter block of Yaml-formatted metadata; and
* and a text body after the frontmatter, in any format
  (the file's extension should match this, e.g. the default `.md` for Markdown).

*[Read more about Content.](content.html)*

## Templates

Templates are written in the [Twig](https://twig.symfony.com/) language, and can output to any format required.
Usually HTML is the target format, but LaTeX, XML, or anything else is just as possible.
Formats do have to have a file extension though (that's how they're identified, in Basildon).

All templates live in the `templates/` directory of a site.
The structure within that directory can be anything.

*[Read more about Templates.](templates.html)*

## Assets (stylesheets, scripts, etc.)

All files (CSS, JS, images, etc.) in the `assets/` directory,
and all non-page files in the `content/` directory,
will be copied to `output/`.
"Non-page" means anything without a `.md` file extension
(or whatever your default is as defined by the `ext` key in `basildon.yaml`).

Images (and other files) can be in either the `assets/` or `content/` directories,
depending on how they're used in the site.
There is no real difference as far as how they end up in the `output/` directory.
This means that you must be careful to avoid name collisions.

## Output

All output is in the `output/` directory of a site.
This directory is ready to be uploaded to a web server as the top level of the site.

The `output/` directory is emptied on every run of Basildon.
