<?php

declare(strict_types=1);

namespace WebxUi\Media\Remote;

/**
 * The resolver of `module-admin`'s fetcher under its old name.
 *
 * Kept so that a test binding this class still answers for the library's fetches; bind
 * {@see \WebxUi\Admin\Remote\HostResolver} instead — it answers for every module.
 *
 * @deprecated use \WebxUi\Admin\Remote\HostResolver
 */
class HostResolver extends \WebxUi\Admin\Remote\HostResolver {}
