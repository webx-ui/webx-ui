<?php

/*
|-----------------------------------------------------------------------------------------------
| Public addresses
|-----------------------------------------------------------------------------------------------
|
| Empty, and worth keeping that way for as long as you can.
|
| Every public address of this site comes from the address registry: a page, an article, a tag
| and anything else with an address is a row in `routes`, and `Route::fallback()` is what
| answers them. A route declared here wins over the fallback for good, which means it takes an
| address the panel can then never hand out — and nothing says so at the time. `/` is the one
| that costs the most: the home page of the tree is an empty slug formatted to `/`, so a
| `Route::get('/')` here is a home page the panel cannot even save.
|
| What does belong here: things that are not pages. A webhook, a callback from a payment
| provider, a download that needs signing. Give them a prefix nobody would ever type as a page
| address and the two never meet.
|
*/
