---
'@webx-ui/php': minor
---

`geography`: one carrier never lists a town twice — a locality another record of the same carrier is bound to is no candidate for its next record (`Matching\CarrierBindings`, answered by `module-delivery` from its bindings table). Two villages of one name in one region, told apart by the carrier only by a qualifier, are two towns for the leading carrier and the binding queue for the others, instead of one. `webx-ui/delivery-novaposhta` gives the district of the qualifier («Бориспільський р-н»), which the buyer's suggestions show beside namesakes.
