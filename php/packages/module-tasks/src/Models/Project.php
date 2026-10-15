<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Where work is done: the folder and repository an agent is started in, and the rules it is
 * handed with every stage.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $workdir
 * @property string|null $repo_url
 * @property string|null $default_branch
 * @property string|null $rules
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Project extends Model
{
    protected $table = 'tasks_projects';

    /** @var list<string> */
    protected $fillable = ['slug', 'title', 'workdir', 'repo_url', 'default_branch', 'rules', 'archived_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
