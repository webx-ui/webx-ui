<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Every address under the panel serves the same page: routing inside the admin belongs to the
 * front end, and the server only has to stop treating a deep link as a 404.
 */
final class ShellController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly Repository $config,
    ) {}

    public function __invoke(): View
    {
        return $this->views->make('webx-admin::shell', [
            'title' => (string) $this->config->get('webx-admin.title'),
            'manifestUrl' => '/'.ltrim((string) $this->config->get('webx-admin.api_path'), '/').'/manifest',
        ]);
    }
}
