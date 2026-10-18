# Permissions

Every screen, panel route, MCP tool and file of the panel is behind a permission, and the server
checks it. This page is for whoever writes a module, or adds a route, a tool or a screen to one:
how to name what the module needs, where to check it, and the test that keeps it closed. How an
administrator gives permissions to people — roles, the matrix, the ready-made roles — is in
[Administrators](./admins#roles); the rules in full are in
[the permissions specification](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_PERMISSIONS.md).

## The model

- **A permission is a string `<module>.<action>`** — `faq.view`, `inbox.update`,
  `blog.taxonomy.manage`. The first part is the module's id (or a family inside it), the action is
  `view` or `manage`, or a word of its own where one of those would be too coarse: `delete`,
  `upload`, `run`, `reset`, `regions`.
- **Roles add up.** An administrator holds several roles, and may do whatever any of them grants.
  There are no negative permissions.
- **Nothing is allowed by default.** A route, a tool or a button without a permission is a bug —
  the test of the module says so (below). The few things open to every administrator on purpose
  (their own profile, the manifest, their own keys) are named in that test with the reason.
- **A super administrator passes every check.** They need no roles, and roles do not limit them.
- **`manage` does not imply `view`.** A read route lists both — `cms.can:faq.view,faq.manage` —
  so a role with only `manage` still reads what it writes.
- **One policy decides.** `WebxUi\Admin\Access\AccessPolicy` answers every "may they" — routes,
  MCP, the panel on the site. Hiding a button is a courtesy; the server answers 403 regardless.
- **Console commands run with full access**: whoever has a terminal on the server already has
  everything. A command that acts in an administrator's name (`webx:mcp:token --admin`) still goes
  through the rules about who gets what.
- **An MCP key is never wider than its owner**: what a call may do is the owner's permissions
  intersected with the key's scopes, decided at the moment of the call — a role taken away narrows
  every key of its owner at once.

Who manages access is separate from what modules declare: `admins.view`, `admins.manage`,
`roles.manage` and `roles.assign` belong to `module-auth`, are in no ready-made role, and come with
rules that hold even for their holders — nobody changes their own roles, hands out more than they
hold, or touches a super administrator without being one.

## Declaring permissions

A module lists what it checks in `permissions()`. Nothing else makes a permission exist: a role can
only hold a declared one, and the matrix of roles shows exactly these.

```php
final class FaqModule extends AbstractModule
{
    public function id(): string
    {
        return 'faq';
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return ['faq.view', 'faq.manage', 'faq.categories.manage'];
    }
}
```

- Start with `view` and `manage`. Add a separate action only where somebody would want one without
  the other: `inbox.update` (work the leads) apart from `inbox.manage` (change the forms),
  `media.upload` apart from `media.manage`, `catalog.delete` for the bin.
- A section inside a module that is shown in the menu on its own, with no permissions of its own —
  the catalogue's brands, the layout regions — says which permissions make it visible by
  implementing `WebxUi\Admin\Contracts\HasVisibility`:

  ```php
  public function visibleWith(): array
  {
      return ['catalog.view', 'catalog.manage'];
  }
  ```

- A module with no permissions and no `visibleWith()` is visible to every administrator. That is
  right for the history or "Connect an agent", and wrong for anything with data.
- The label in the matrix comes from the dictionary of `module-auth` (`webx-auth::permissions`): the
  action word is translated once for everybody, so `view`, `manage`, `delete` need nothing. A new
  action word gets its line there, in all ten languages.

## Checking them

### Panel routes

Every route of the panel goes into the `webx.panel` group and carries `cms.can` — any of the
permissions listed lets the caller through. Reading takes `view` or `manage`, writing takes
`manage`:

```php
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->group(function (): void {
        Route::middleware('cms.can:faq.view,faq.manage')->group(function (): void {
            Route::get('faq/questions', [QuestionController::class, 'index']);
            Route::get('faq/questions/{question}', [QuestionController::class, 'show'])->whereNumber('question');
        });

        Route::middleware('cms.can:faq.manage')->group(function (): void {
            Route::post('faq/questions', [QuestionController::class, 'store']);
            Route::put('faq/questions/{question}', [QuestionController::class, 'update'])->whereNumber('question');
        });
    });
```

- `cms.auth` and `cms.can` run before the route's models are bound: an administrator without the
  permission gets 403 for any id, and cannot learn from a 404 which records exist.
- A check that depends on what is sent — a field only some may change, a record of a particular
  kind — goes in the controller, through the policy:

  ```php
  if (! app(AccessPolicy::class)->allows($request->user(), 'catalog.delete', $product)) {
      abort(403);
  }
  ```

  Pass the record when you have it: the policy ignores it today and is the place where limits by
  record ("only their own articles") will go later, without touching the module again.

- The shared machinery takes the permission from the module that registers into it — categories
  (`CategoryKind` `view` / `manage`), item order (`CategoryRoutes::items(..., 'faq.manage')`),
  links, collections, relations, history types, notes, uploads
  (`UploadPurposes::register(..., permission:)`), the panel on the site
  (`SiteBar::entity(..., new EntityLink(permission:))`). Give each of them the narrowest permission
  that fits; there is no default to fall back on.
- **Private files of a module** — attachments of a lead, an export — are not in the file manager and
  never in a public folder: they are served by a route of the module behind the module's own
  permission.

### MCP tools

A tool is behind a scope of the key (`<id>:read` or `<id>:write`) **and** a permission of the
administrator the key acts for. Without `permission:` a read tool asks for `<id>.view` or
`<id>.manage`, and a mutating one for `<id>.manage`. Name it when the tool is not about the module
as a whole:

```php
Tool::read('list', 'The questions of the FAQ…', fn (array $arguments): array => $this->list($arguments),
    permission: ['faq.view', 'faq.manage'],
);

Tool::mutating('delete', 'Move a question to the bin…', $handler,
    permission: 'faq.manage',
);
```

A tool the administrator may not use is left out of `tools/list` and refused if called anyway. A
tool that changes who may do what (roles, keys) goes through the same rules as the panel; a check
in the panel's controller is not enough on its own.

### Screens and buttons

- A node of a JSON screen takes `can`: without that permission the node is not rendered
  (see [Screens](./screens)).
- A page of the module asks the panel's context:

  ```ts
  const canManage = computed(() => context.can('faq.manage'))
  ```

  and hides what the administrator may not do. The menu leaves out a section none of whose
  permissions they hold, and its address shows «No access».

Neither replaces the route's `cms.can`: the screen only spares the administrator a button that
would answer 403.

## The test every module carries

Every package with panel routes or MCP tools has `tests/AccessCoverageTest.php`, built on the trait
`WebxUi\Auth\Testing\AssertsPermissions`:

```php
use WebxUi\Auth\Testing\AssertsPermissions;

final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    #[Test]
    public function every_route_and_tool_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission(['WebxUi\\Faq\\', 'api/cms/faq/']);

        $this->assertToolsNeedPermission('faq', 'faq-categories');
    }
}
```

- `assertPanelRoutesNeedPermission()` finds every panel route served by these controller
  namespaces or under these addresses, and asks each one twice with a made-up id: nobody signed in
  must get 401, an administrator without permissions must get 403. Every permission a route checks
  must be declared by an installed module.
- `assertToolsNeedPermission()` takes the module ids of the tools: each must ask for a declared
  permission, be left out of the list for an administrator who holds none, and refuse the call.
- A route open to every administrator on purpose is named in the second argument with the reason:

  ```php
  $this->assertPanelRoutesNeedPermission('WebxUi\\Auth\\', [
      'GET api/cms/auth/me' => 'Everybody reads their own profile.',
  ]);
  ```

  A route not on that list and without `cms.can` fails the test, and so does a name on the list
  for which there is no route.

The test needs the module's providers, `module-auth`'s and, for the tools, the MCP server's, with the
database migrated. Beside it, a module tests what is particular to it — the extra action
(`catalog.delete` opens the bin and nothing else does), a field only some may change — with a
request as an administrator holding exactly that role.

### A module of a site

`php artisan webx:make-module <Name>` writes the module with `view` and `manage` declared, and
`tests/Feature/Cms/<Name>ModuleAccessTest.php` beside the budget test. The test is marked incomplete
until the module serves its first route and its first tool; then take the line out, and it holds
every route under `api/cms/<id>/` and every tool of the module.

## Check your work

- Every new route has `cms.can` (or a controller check through the policy), every new tool has the
  permission it needs, and every new action word is in `permissions()`.
- `vendor/bin/phpunit --filter AccessCoverageTest` is green, and nothing was added to the list of
  open routes without a reason a reviewer would accept.
- Sign in as an administrator with a role that has only `view`: the section opens, write buttons
  are gone, and a write request answers 403. With no role, the section is not in the menu.
- An agent's key without the permission does not see the tool in `tools/list`.
- The package's `AGENTS.md` lists the permissions under `### Permissions` (what each opens), so an
  agent working on a site knows which one a new route of the module should ask for.
