---
'@webx-ui/php': minor
---

`delivery-novaposhta`: the waybill and the sender. The carrier's settings choose the sender and its contact person from the account (reading only) and the town and the branch of sending from the synced directories; each of the three types makes a waybill — the recipient checked before any request (names in Ukrainian letters, a `380…` phone), `InternetDocument.save` from a branch to a branch, a parcel locker with the sizes of its seat or a courier, who pays from the order's snapshot — and cancels it. No cash on delivery, no label to print; the carrier's refusals in words with its own text and codes; a read-only key makes no waybill and sends no request. `module-delivery`: `WaybillRefused::field()` marks the field of the dialog a refusal is about; `CarrierLocalities::localityId()` / `forLocalities()` and `Points::refOf()` / `externalIds()` read the base by the carrier's ids.
