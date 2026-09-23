---
'@webx-ui/module-seo': minor
---

The sitemap in the SEO section: a card above the rules with its address, the number of
addresses in each file, when it was built, how many visible pages were left out and why, and a
**Rebuild** button for those who may manage SEO. **Check an address** now says whether the
address is in the sitemap, and if not, why. `createSeoApi()` gains `sitemap()` and
`rebuildSitemap()`; `SeoTestResult` gains `sitemap`.
