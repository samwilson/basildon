---
title: Basildon
subtitle: A static website generator
---

**Basildon** is a simple static website generator
written in PHP and supporting Markdown content, Twig templates,
SQLite, and outputs of HTML and PDF (via LaTeX).

## Quick start

1. `mkdir mysite`
2. `cd mysite`
3. `composer require samwilson/basildon`
4. Add content and create templates (see below)
5. `./vendor/bin/basildon .`

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
