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
