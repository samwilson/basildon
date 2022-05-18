---
title: Basildon
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

Because content is usually in [Markdown format](https://www.markdownguide.org/getting-started/),
there are some useful Markdown additions that can be used in content pages.
The rest of this page explains these.

All the metadata from content files is read into an [SQLite](https://www.sqlite.org) database when a site is built,
which can be queried in [templates](templates.html) (see that page for more information about how).
The database can also be modified and the changes [written back to the content files](writing.html).

## Images

Images should be stored in the `content/` directory,
and included with the normal Markdown syntax.
Their file paths should be relative to the content directory and start with a slash.

For example, an image file stored at `content/images/file.png`
should be referenced like this: `![Alt text](/images/file.png)`.

For information about other assets such as stylesheets and scripts,
see [the Assets section](index.html) of the documentation overview.

## Embeds

This section documents 'embeds', which are what we call a URL on its own line in a Markdown document.[^embed]
Embeds are simple ways to include images, videos, and summaries of other web pages.
For example, this is a photo from Wikimedia Commons:

https://commons.wikimedia.org/wiki/File:Co-Op,_Post_Office,_Courthouse.jpg

It is added to the source Markdown with this:

    https://commons.wikimedia.org/wiki/File:Co-Op,_Post_Office,_Courthouse.jpg

All of the other information (image URL, caption, etc.) is retrieved from the Commons API when the Markdown is rendered.

Embeds can be rendered to any output format; they're not limited to HTML.

[^embed]: The term 'embed' comes from WordPress,
which has a [similar function](https://wordpress.org/support/article/embeds/).
Basildon doesn't yet support the [oEmbed standard](https://oembed.com/).

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

### Example: Flickr

In `config.yaml`:

    embeds:
      flickr: "|https://www.flickr.com.*?([0-9]+).*|"

In `templates/embeds/flickr.html.twig`:

    {% set flickr = flickr(embed.matches.1) %}
    
    <figure itemscope itemtype="http://schema.org/ImageObject">
        <a href="{{ flickr.urls.photopage }}"><img alt="An image from Flickr." src="{{ flickr.urls.medium_image }}" /></a>
        <figcaption>
            <strong itemprop="name">{{ flickr.title }}{% if flickr.description %}:{% endif %}</strong>
            {% if flickr.description %}
                <span itemprop="description">{{ flickr.description|raw }}</span>
            {% endif %}
            <span class="meta">
                {% if flickr.dates.taken %}
                    {% set date = date_create(flickr.dates.taken) %}
                    <time datetime="{{ date.format('c') }}">{{ date.format('Y F j l, g:iA') }}</time>
                {% endif %}
                &middot; <a href="{{ flickr.urls.photopage }}">via Flickr</a>
                &middot; <a href="{{ flickr.license.url }}" rel="license" title="{{ flickr.license.name }}">&copy;</a>
            </span>
        </figcaption>
    </figure>

