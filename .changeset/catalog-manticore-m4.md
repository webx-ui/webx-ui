---
'@webx-ui/php': patch
'@webx-ui/module-catalog-manticore': patch
---

The Manticore engine accepted on the alfatech export (93 677 products: a rebuild in 44 s, a category with ten facets in 44 ms, a corrected typo in 51 ms). A question that falls into a rebuild's swap is asked once more instead of failing; «System → Search index» counts a rebuild started from the console and follows it; the stock status in a product's document is the one the filter shows, so a hidden status is counted by neither engine.
