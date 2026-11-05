# Product feeds

`webx-ui/module-catalog-feeds` makes files for marketplaces and ad platforms out of the catalogue. A
feed is a template, a selection of products and a schedule; the file it builds is served at a
permanent address with a key. It is a satellite of the [catalogue](/guide/catalog): without the
brands, stock or properties satellites their parts of the form simply are not there.

The section is **Catalog → Feeds**. It has no permissions of its own: `catalog.view` reads,
`catalog.manage` writes and sees the address. The address carries the key, so whoever holds only
`catalog.view` is told it is hidden.

## A new feed

**New feed** asks for a preset: Google Merchant, plain JSON, XML or CSV, or an empty feed. The form
opens with a copy of it, and nothing exists until the first save. A feed holds its own copy: changing
it touches no other feed, and a later version of a preset touches no feed.

A site can register a file no template describes as a PHP class (see below): it is offered here
like a preset, and the **Template** tab of such a feed names the class instead of the editor.

A feed that would not make a valid file is not saved. The form stays open and the words go under the
part they are about, with the line. One language per feed: a second language is a second feed. The
currency is always the site's (`WEBX_CATALOG_CURRENCY`).

A CSV feed is written for programs, so its values are not protected against spreadsheet formulas: a
prefix would change the data the marketplace receives. If the file is opened in a spreadsheet, a
formula in a product description will run, and the form says so as soon as the format is CSV.

## The form

The screen is `catalog.feed-form`. Its node ids are a public contract, so a site can patch it:

| Tab          | Node ids                                                                                                                                   |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `main`       | `name`, `format`, `format-csv-warning`, `locale`, `currency`, `is-active`, `address-field`, `schedule`, `schedule-at`, `rebuild-on-change` |
| `template`   | `template-field`                                                                                                                           |
| `selection`  | `filter`                                                                                                                                   |
| `exclusions` | `exclusions-field`                                                                                                                         |
| `mappings`   | `stock-map`, `taxonomy`, `categories-field`                                                                                                |
| `labels`     | `labels-field`                                                                                                                             |
| `journal`    | `builds-field`                                                                                                                             |

The types are `wx-catalog-feed-locale`, `-address`, `-template`, `-filter`, `-exclusions`,
`-stock-map`, `-categories`, `-labels` and `-builds`. They read the page around them, so they work on
this screen only.

### Template

Three editors, the header, the product and the footer, and what stands between products. The
header and the footer are written once and have no product, so the suggestions differ by part. Under
the part being edited the same variables are buttons.

::: v-pre
Typing `{{` opens the list. A list goes as a section: `{{#barcodes}}…{{/barcodes}}`, with `first` and
`last` on every row.
:::

The preview renders as you type: the server sends the values of a few real products once, and the
browser fills the template in, escaping as the file will. **As a file** shows the whole sample;
switched off, one product of your choice. **Check on the server** runs what a save runs and marks the
line it complains about. The browser's own parsers only give a hint; the server decides.

### Selection and exclusions

The selection takes categories in and out, brands, a price, labels and any property, in the
storefront's filter or not. The number under it is what fits now, before products with no price,
picture or name in the feed's language are left out.

Exclusions take single products out of this feed and leave them in the others. They, the mappings of
categories and the journal live beside the feed, so they appear once it is saved, and each change is
kept at once. The file follows with the next build.

### Mappings and labels

Each stock status goes out as `in_stock`, `out_of_stock`, `preorder` or `backorder`. Google takes the
last two only with `availability_date`, which the catalogue does not have, so the form warns.

Categories are mapped to the marketplace's list: import it from its address or as a file, or type an
id by hand. A category with no value takes after its nearest ancestor, shown grey, so a branch is
mapped at its top.

`custom_label_0…4` each take one source: a product label, a price range, a property or a constant.
`mpn` comes from the product's field or from a property.

### Journal

Every build with what it wrote and why products stayed out. A row opens into the counters, the
warnings and the first products left out, each a link to its form. Below are the reissues of the key.

## The address

**Reissue the key** closes the old address at once, so it asks first. Paste the new address into
Merchant Center and everywhere else that reads the feed.

## Agents

An agent connected to the panel has the same feeds as `catalog_feeds_*` tools, behind the catalogue's
permissions and the scopes `catalog:read` and `catalog:write`. The usual path is
`catalog_feeds_presets`, `catalog_feeds_create`, `catalog_feeds_mappings_set`, `catalog_feeds_preview`,
`catalog_feeds_rebuild` and `catalog_feeds_builds`. It reads `catalog://feed-rules` and
`catalog://feed-variables` before writing a template.

Every tool that changes something takes `dry_run`. Creating, editing, mappings and exclusions go through
all their checks and keep nothing. `catalog_feeds_rebuild` counts the products instead,
`catalog_feeds_reissue_key` names the address that would stop working, and `catalog_feeds_delete` says what
would go.

The address holds the key, so it goes only to a connection that may write: an administrator with
`catalog.manage` who connected an agent read-only does not hand it the key.

## A format as a PHP class

For a structure a template cannot describe: nesting that depends on the data, an order of its own, a
binary file. It is the way out, not the main road: a template can be seen and changed without a
release. Implement `WebxUi\CatalogFeeds\Formats\FeedFormat` and register it from a provider of the
site:

```php
$this->callAfterResolving(FeedFormats::class, static function (FeedFormats $formats): void {
    $formats->register(new MarketplaceFormat);
});
```

The selection, the third layer, the variables, the jobs, the journal and the delivery stay the
build's own. The filter, the exclusions, the mappings and the labels of the form apply as they do to
a template.

- `variables()` lists the names to compute. A name the site does not have fails the build.
- A build is many jobs, so an instance keeps nothing between calls. `$out->written()` says how many
  products are in the file, which is how a comma finds its place. The feed's separator is not used.
- `open()` is called after the products, because it may speak about them; the file still reads open,
  products, close. `$context->values` holds what a header may say, by the same names.
- `validate()` reads the finished file and returns what is wrong with it, or `null`. It runs in one
  job. A refused file replaces nothing.
- `extension()` is the end of the feed's address, so changing it changes the address.

A feed is tied to its class when it is created, from the preset; no form or agent can set it. When a
class leaves the site, builds fail with a clear error and the last file goes on being served at the
same address, with the same type.
