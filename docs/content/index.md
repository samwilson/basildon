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

## Assets (stylesheets and scripts)

Every stylesheet and script in the `assets/` directory
will be copied to `output/assets/`.

Images should be in the `content/` directory;
for more information, see the [Content documentation page](content.html).

## Output

All output is in the `output/` directory of a site.
This directory is ready to be uploaded to a web server as the top level of the site.

The `output/` directory is emptied on every run of Basildon.
However, sometimes you need to be able to keep files or directories that persist.
For example, you might want `output/` to be its own Git repository for Github Pages,
or to add a `_redirects` file for Netlify, or any number of other things.
This is possible with the `output_exclude` config key,
which takes an array of regular expressions to be matched against relative paths
(these paths include the leading slash, similar to page IDs).
For example:

    output_exclude:
      - "|/_redirects|"
      - "|/\\.git.*|"
