---
title: Basildon Documentation
subtitle: Content
---

This page details the `content/` directory of a Basildon site.
All pages of a site live in the content directory:
each is a separate text file, and the file names and directory hierarchy are not prescribed.
Content files have two parts: one, a Yaml-formatted frontmatter, delimited by three hyphens; and two, a main body that can be in any format.
The file extension should match the format of the body; often this is Markdown (`.md`), but it doesn't have to be
— you could easily have all your content files be HTML if that suits your site better.

An example of a page file at `content/topics/goats.md`:

    ---
    title: Goats
    description: Goats are good example animals.
    ---
    
    This is the part where we explain more about the 'goat' topic.

Because content is usually in Markdown format, there are some useful Markdown additions that can be used in content pages.
The rest of this page explains these.

## Embeds

This section documents 'embeds', which are what we call a URL on its own line in a Markdown document.
Embeds are simple ways to include images, videos, and summaries of other web pages.
For example, this is a photo from Wikimedia Commons:

https://commons.wikimedia.org/wiki/File:Co-Op,_Post_Office,_Courthouse.jpg

It is added to the source Markdown with this:

    https://commons.wikimedia.org/wiki/File:Co-Op,_Post_Office,_Courthouse.jpg

All of the other information (image URL, caption, etc.) is retrieved from the Commons API when the Markdown is rendered.

Embeds can be rendered to any output format; they're not limited to HTML.

### Configuration

To configure a new embed, add a name and a URL pattern to your site's `config.yaml`, under the `embeds` key.

    embeds:
      embedName: "|embed-pattern|"

(The pipe character is used here as a pattern delimiter because forward slash, which is commonly used,
is going to occur frequently within the matched URLs, and escaping it in all cases is cumbersome.)

Then, set up `templates/embeds/<embedName>.<format>.twig` to contain the HTML or other output that should be output for the embed. 

The following variables are available for embed templates:

* `embed.name`: the name of the embed, which will always be the same as the template's name.
* `embed.matches`: the result of [`preg_match`](https://www.php.net/manual/en/function.preg-match.php) on the embed's pattern:
  `matches.0` will contain the URL that matched the full pattern,
  `matches.1` will have the text that matched the first captured parenthesized subpattern, and so on.

### Example: Wikimedia Commons

In `config.yaml`:

    embeds:
      commons: "|https://commons.wikimedia.org/wiki/File:(.*)|"

In `templates/embeds/commons.html.twig`:

    {% set commons = commons(embed.matches.1) %}
    <figure>
        <a href="{{ commons.imageinfo.0.descriptionurl }}">
            <img src="{{ image_url( commons.imageinfo.0.thumburl ) }}"
                 width="{{ commons.imageinfo.0.thumbwidth }}"
                 height="{{ commons.imageinfo.0.thumbheight }}"
                 alt="{{ commons.labels.en.value|escape('html_attr') }}"
            />
        </a>
        <figcaption>{{ commons.labels.en.value }}</figcaption>
    </figure>

Note that this is also using the `commons()` Twig function, which is [documented separately](./templates.html).

### Exapmle: Flickr


