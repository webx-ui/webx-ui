<?php

declare(strict_types=1);

/*
 * Pages of help, in Markdown.
 *
 * Read twice: the panel draws them in a dialog behind the `?` beside whatever they explain,
 * and `BlockResources` hands the same text to an agent over MCP. One explanation of a thing,
 * so the two cannot drift.
 *
 * Which is also why these read as prose rather than as interface labels — they are addressed
 * to somebody about to write a schema, whether that somebody is a person or not.
 */
return [
    'schema-title' => 'The fields of a block',

    'schema' => <<<'MD'
        A block type's schema is a list of **screen nodes** — the same nodes every described
        screen of the panel is built from. What you write here is the form an editor fills in,
        and the names of the variables your template prints.

        ## One node

        ```json
        {
          "id": "title",
          "type": "wx-input",
          "label": "Headline",
          "props": { "placeholder": "Pages made of blocks" }
        }
        ```

        - `id` is the whole of the contract with the template: this node becomes `$title`
          in the Blade, and `title` in the block's stored values. Change it and every page
          already using the block keeps the old key — rename deliberately.
        - `type` says what the node is. The list is below.
        - `label` is what the editor reads above the field.
        - `props` are that type's own settings, and every type has different ones.
        - `children` holds the nodes inside a layout node, or the fields of one record of a
          repeater.

        The schema itself is a list, so the outermost brackets are always `[ … ]`.

        ## What you can put in it

        Fields hold a value:

        - `wx-input` — one line of text. `props`: `placeholder`, `maxlength`.
        - `wx-textarea` — several lines. `props`: `rows`, `placeholder`.
        - `wx-rich-text` — a formatted document, stored as HTML: headings, lists, tables,
          links, pictures from the library. Print it with `{!! !!}` rather than `{{ }}`.
          `props`: `placeholder`, `minHeight`, `tools`.
        - `wx-input-number` — a number. `props`: `min`, `max`, `step`.
        - `wx-switch`, `wx-checkbox` — a boolean.
        - `wx-select`, `wx-radio-group` — one of `props.options`, written as
          `[{ "value": "left", "label": "Left" }]`.
        - `wx-date-picker` — a date, stored as `YYYY-MM-DD`.
        - `wx-color-picker` — a colour, stored as a CSS colour.
        - `wx-media`, `wx-file` — one picture or one document from the library. The value is
          `{ path, alt, title }`; the template also gets `url`, worked out when the block is
          printed.
        - `wx-gallery`, `wx-files` — several of them, in the order they were dragged into.
          Reach for these rather than a repeater wrapped around a `wx-media`.
        - `wx-repeater` — a list of records. Its `children` are the fields of one record.
          `props`: `itemLabel`, `min`, `max`.
        - `wx-blocks` — other blocks inside this one, which is what makes the type a
          container. `props`: `allow` (a list of type slugs), `max`. The template prints it
          with `@blocks('id')`.

        Layout nodes hold other nodes and no value of their own: `wx-card` (`props.title`),
        `wx-tabs` with `wx-tab` children (`props.label`), `wx-row` with `wx-col` children
        (`props.span`, out of 24), `wx-divider`. `wx-text` and `wx-alert` say something to the
        editor and store nothing.

        ## Fields in more than one language

        A field marked `localized` holds one value per language the site publishes in, rather
        than one value:

        ```json
        { "id": "title", "type": "wx-input", "label": "Headline", "localized": true }
        ```

        The form gives that field a language switcher, and the block stores a map —
        `{ "en": "Shoes", "uk": "Взуття" }`. The template does not see the map. It is handed
        the language the page is being read in, so `{{ $title }}` stays `{{ $title }}` and
        needs to know nothing about languages at all.

        A language nobody has written falls back: the one asked for, then the site's default,
        then its fallback. So a block half-translated prints the half that exists rather than
        an empty heading.

        Two things worth knowing before you mark a field:

        - **Only where the words differ.** A colour, a number of columns, a switch, an address
          — these are the same in every language, and marking them makes an editor fill the
          same value in three times.
        - **It changes what is stored.** A field that has content and then becomes `localized`
          has a string where a map is expected, and one that stops being localized has a map
          where a string is expected. Decide before the type is used on a page; afterwards it
          is a migration, not an edit.

        A picture is rarely one of them: the same photograph usually serves every language, and
        what differs is the words beside it. Which is why a field of files refuses `localized`
        outright and translates the captions inside it instead — `alt` and `title` have a
        language switcher each, on every picture, in all four of `wx-media`, `wx-file`,
        `wx-gallery` and `wx-files`. Those come out the same way a marked field does:
        `{{ $picture['alt'] }}` is the caption in the language the page is being read in, down
        the same fallback chain, so a template prints it without asking either.

        ## A worked example

        ```json
        [
          { "id": "title", "type": "wx-input", "label": "Headline" },
          {
            "id": "layout",
            "type": "wx-select",
            "label": "Layout",
            "props": {
              "options": [
                { "value": "wide", "label": "Wide" },
                { "value": "split", "label": "Two columns" }
              ]
            }
          },
          {
            "id": "cards",
            "type": "wx-repeater",
            "label": "Cards",
            "props": { "itemLabel": "Card", "max": 6 },
            "children": [
              { "id": "heading", "type": "wx-input", "label": "Heading" },
              { "id": "picture", "type": "wx-media", "label": "Picture" }
            ]
          }
        ]
        ```

        In the template that is `$title`, `$layout`, and `$cards` as a list of records with
        `heading` and `picture` in each.

        ## While you are writing it

        The form on the right is built from this schema as you type, and the sample values
        under it are what the preview is drawn with. A field that does not appear there is a
        field this file does not actually describe — check the JSON is still valid, since a
        schema that will not parse leaves the last good one on screen.

        Anything the template prints should exist here, and anything here should be printed
        or used: the checks beside the editor say so in both directions.
        MD,
];
