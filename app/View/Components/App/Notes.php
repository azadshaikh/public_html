<?php

declare(strict_types=1);

namespace App\View\Components\App;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * Notes Component - Displays notes panel for any model with HasNotes trait
 *
 * Uses Unpoly for form submissions and server-rendered partial swaps.
 *
 * @example
 * <x-app.notes :model="$address" />
 * <x-app.notes :model="$user" :show-form="false" :read-only="true" />
 */
class Notes extends Component
{
    /**
     * The notes collection.
     */
    public Collection $notes;

    /**
     * Unique ID for the notes panel (for Unpoly targeting).
     */
    public string $panelId;

    /**
     * Unique ID for the notes list (for Unpoly targeting).
     */
    public string $listId;

    /**
     * Unique ID for the add note form (for UX hooks).
     */
    public string $formId;

    /**
     * Unique ID for the editor textarea (for UX hooks).
     */
    public string $editorId;

    /**
     * Create a new component instance.
     *
     * @param  Model  $model  The noteable model
     * @param  bool  $showForm  Whether to show the add note form
     * @param  bool  $readOnly  Disable all editing
     * @param  string|null  $visibility  Filter by visibility
     * @param  int  $perPage  Notes per page (0 = no pagination)
     * @param  bool  $collapsed  Initial collapsed state
     */
    public function __construct(
        public Model $model,
        public bool $showForm = true,
        public bool $readOnly = false,
        public ?string $visibility = null,
        public int $perPage = 0,
        public bool $collapsed = false,
    ) {
        $modelType = class_basename($this->model);
        $modelId = $this->model->getKey();
        $this->panelId = sprintf('notes-panel-%s-%s', $modelType, $modelId);
        $this->listId = sprintf('notes-list-%s-%s', $modelType, $modelId);
        $this->formId = sprintf('notes-form-%s-%s', $modelType, $modelId);
        $this->editorId = sprintf('note-editor-%s-%s', $modelType, $modelId);

        $this->loadNotes();
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('components.app.notes');
    }

    /**
     * Load notes from the model.
     */
    protected function loadNotes(): void
    {
        if (! method_exists($this->model, 'notes')) {
            $this->notes = collect();

            return;
        }

        $query = $this->model->notes()->with('author');

        if ($this->visibility) {
            $query->where('visibility', $this->visibility);
        }

        $this->notes = $query->get();
    }
}
