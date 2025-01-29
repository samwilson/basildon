---
title: Basildon
subtitle: Content
---

This page details the `content/` directory of a Basildon site.
All pages of a site live in the content directory:
each is a separate text file, and the file names and directory hierarchy are not prescribed.
Content files have two parts: one, a Yaml-formatted frontmatter, delimited by three hyphens; and two, a main body that can be in any format.
The file extension should match the format of the body; often this is Markdown (`.md`), but it doesn't have to be
— you could easily have all your content files be HTML if that suits your site better
(change it via the `ext` key in `basildon.yaml`).

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

Local images should be stored in the `content/` directory,
and included with the normal Markdown syntax.
Their file paths should be relative to their own location and not start with a slash.

For example, an image file stored at `content/images/file.png`
should be referenced like this:

* From `lorem.md` as `![Alt text](images/file.png)`.
* From `lorem/ipsum.md` as `![Alt text](../images/file.png)`.

For information about other assets such as stylesheets and scripts,
see [the *Assets* section](index.html) of the documentation overview.

## Shortcodes

This section documents 'shortcodes', which are what we call specific replacable parts in a Markdown document.
They are inline phrases or blocks of text such as `{foo}` or `{{{bar|id=123}}}` which get replaced
by the contents of templates such as `templates/shortcodes/foo.html.twig` or `templates/shortcodes/bar.tex.twig`.

* Inline shortcodes are delimited by single braces and can contain any number of attributes, e.g.:
  * Lorem `{foo}` ipsum with no parameters.
  * Lorem `{foo | bar=baz|bif="foo bar"}` ipsum with two parameters, the second of which contains a space.
  * Lorem `{foo bif}` ipsum with a parameter with no value.
* Block shortcodes are delimited by triple braces at the beggining of lines, e.g.:
  * A block of one line, with one parameter:
    ```
    {{{quotation | cite="Author name"
    Lorem ipsum
    }}}
    ```
  * A single-line block with one parameter:
    ```
    {{{linebreak num=10}}}
    ```

Shortcodes replace an earlier feature in Basildon called 'embeds'.
The functionality of embeds can be achieved with shortcodes, along with a lot more.

The term 'shortcode' (as well as the older 'embed') comes from WordPress,
which has a [similar function](https://codex.wordpress.org/shortcode).

Shortcodes are a simple way to include images, videos, and summaries of other web pages.
For example, this is a photo from Wikimedia Commons:

{{{commons|Co-Op,_Post_Office,_Courthouse.jpg}}}

It is added to the source Markdown with this:

    {{{commons|Co-Op,_Post_Office,_Courthouse.jpg}}}

All of the other information (image URL, caption, etc.) is retrieved from the Commons API when the Markdown is rendered.

Shortcodes can be rendered to any output format; they're not limited to HTML.

### Configuration

To configure a new shortcode, add a file to the templates' directory,
with a name matching what you want to use in the Markdown.

The file `templates/shortcodes/<shortcode-name>.<format>.twig` to contain the HTML or other output that should be output for the shortcode. 

The following variables are available in shortcode templates:

* `shortcode.name`: the name of the shortcode, which will always be the same as the template's name.
* `shortcode.attrs.foo`: fetches an attribute by name.
* `shortcode.attrs.1`: fetches an unnamed attribute by number (starting from 1).
* `shortcode.body`: for block shortcodes, fetches the entire body text.

### Example: Wikimedia Commons

In any Markdown file:

    {{{commons file=Example.jpg}}}

In `templates/shortcodes/commons.html.twig`:

    {% set commons = commons(shortcode.attr('file')) %}
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

In any Markdown file:

    {{{flickr|id=123456}}}

In `templates/shortcodes/flickr.html.twig`:

    {% set flickr = flickr(shortcode.attrs.id) %}
    
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
