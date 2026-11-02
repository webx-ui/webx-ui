---
'@webx-ui/php': minor
---

`webx-ui/delivery-novaposhta` syncs the carrier's points from `getWarehouses`, the whole network page by page: branches and pick-up points are `branch`, parcel lockers are `locker`, with hours, limits, postcode and coordinates; the carrier's own drop-off points and points closed to choosing are left out and counted in the run's warnings. `module-delivery`: a point can be open to some people only (`delivery_points.restriction`, `PointRecord::$restriction`) — a locker for the residents of a block is kept, said in words beside it in the checkout and the panel, and listed after the others; a satellite can report what it left out (`Page::$skipped`); after a pass of points a locality without coordinates gets the middle of its points; the carrier contract accepts a directory of one page.
