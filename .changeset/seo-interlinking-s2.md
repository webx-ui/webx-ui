---
'@webx-ui/module-seo': minor
'@webx-ui/php': patch
---

`module-seo`: the Interlinking view of the SEO section, shown where the manifest says `meta.links` is on. Donors with their links and broken ones counted, search by donor address or anchor and a "with broken links" filter; a donor's block as one dialog — heading, state and the links "acceptor + anchor" in a sortable list, the acceptor suggested from the address registry, broken ones marked, 422 errors under the field they belong to, and the addresses a save replaced by their redirect targets said after it. Import of a CSV/XLSX brief with a preview (counts and per-row errors and warnings) before it is applied, export as CSV or XLSX, and one heading for the ticked donors or for every donor under an address prefix, previewed first. New API calls `links`, `link`, `createLink`, `updateLink`, `removeLink`, `importLinks`, `exportLinksUrl`, `linksHeading`, `linkAddresses`; `WxSeoLinksPage` and `seoFeature()` are exported. The panel words are the `links` group of the module's dictionary, in all ten languages.
