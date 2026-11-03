# webx-ui/delivery-novaposhta

Nova Poshta for [WebX UI](https://github.com/webx-ui/webx-ui) delivery (`webx-ui/module-delivery`):
the carrier `novaposhta` with its API key and sender settings, a thin client of its JSON API, and
three method types — a branch, a parcel locker and a courier to the door.

```bash
composer require webx-ui/delivery-novaposhta
php artisan migrate
```

The migration adds three methods switched off. In the panel, «Delivery → Carriers → Nova Poshta»:
enter the API key from the carrier's business account, «Test the connection», fill the settings,
then switch on the methods the shop needs in «Delivery → Methods».

| Variable               | What it does                                                              |
| ---------------------- | ------------------------------------------------------------------------- |
| `NOVAPOSHTA_READ_ONLY` | `true` — the client refuses every method that writes to the account       |
| `NOVAPOSHTA_TIMEOUT`   | seconds to wait for the API, 10 by default                                |
| `NOVAPOSHTA_JOURNAL`   | path of the journal of calls (model, method, outcome); empty turns it off |

The key is stored encrypted by `module-delivery` and never reaches a log, the journal or an answer
of the panel.

The shop's guide — connecting the carrier, its directories, waybills and a read-only key:
[Nova Poshta](https://webx-ui.github.io/webx-ui/guide/delivery-novaposhta) (`apps/docs/guide/delivery-novaposhta.md`).

Agents and contributors: read [`AGENTS.md`](AGENTS.md).

MIT licence.
