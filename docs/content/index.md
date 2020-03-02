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

Templates are written in the Twig language, and can output to any format required.

There are a few variables available to templates:

1. `page` - An object representing the current page being rendered.
   It has the following members:
   * `page.body`: The unmodified body text,
     good for piping through filters such as `md2html` and `md2latex`.
   * `page.metadata`: All the metadata defined in the page's frontmatter.
   * `page.link(target)`: Creates a relative URL to another page.
2. `database` - The database,
   the most useful attribute of which is `database.query(sql)`.
3. `site` - The site configuration.

## Assets (stylesheets and scripts)

Every stylesheet and script in the `assets/` directory
will be copied to `output/assets/`. 
