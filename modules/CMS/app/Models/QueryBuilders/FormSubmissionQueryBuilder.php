<?php

namespace Modules\CMS\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class FormSubmissionQueryBuilder extends Builder
{
    public function search(?string $search): self
    {
        if (! $search) {
            return $this;
        }

        return $this->where(function ($query) use ($search): void {
            $query->where('cms_form_submissions.data', 'ilike', sprintf('%%%s%%', $search));
        });
    }

    public function filterByForm(string|array|null $formId): self
    {
        if (! $formId) {
            return $this;
        }

        if (is_array($formId)) {
            return $this->whereIn('cms_form_submissions.form_id', $formId);
        }

        return $this->where('cms_form_submissions.form_id', $formId);
    }

    public function filterByDate(?array $date): self
    {
        if (! $date) {
            return $this;
        }

        if (isset($date['from'])) {
            $this->whereDate('cms_form_submissions.created_at', '>=', $date['from']);
        }

        if (isset($date['to'])) {
            $this->whereDate('cms_form_submissions.created_at', '<=', $date['to']);
        }

        return $this;
    }

    public function filterBySortable(string|array|null $sortable): self
    {
        if (! $sortable) {
            return $this;
        }

        if ($sortable === 'latest') {
            return $this->latest('cms_form_submissions.created_at');
        }

        if ($sortable === 'oldest') {
            return $this->oldest('cms_form_submissions.created_at');
        }

        if ($sortable === 'latest_updated') {
            return $this->latest('cms_form_submissions.updated_at');
        }

        if ($sortable === 'oldest_updated') {
            return $this->oldest('cms_form_submissions.updated_at');
        }

        return $this;
    }

    public function sortBy(?string $sortBy): self
    {
        if (! $sortBy) {
            return $this;
        }

        $sortFields = [
            'form_id' => 'form_id',
            'created' => 'created_at',
            'updated' => 'updated_at',
        ];

        if (isset($sortFields[$sortBy])) {
            return $this->orderBy($sortFields[$sortBy]);
        }

        return $this;
    }

    public function orderResults(string|array|null $order): self
    {
        if (! $order) {
            return $this->latest('cms_form_submissions.created_at');
        }

        if (is_array($order)) {
            foreach ($order as $field => $direction) {
                $this->orderBy($field, $direction);
            }
        }

        return $this;
    }

    public function paginateResults(?array $pagination): LengthAwarePaginator
    {
        $perPage = $pagination['per_page'] ?? 15;
        $page = $pagination['page'] ?? 1;

        return $this->paginate($perPage, ['*'], 'page', $page);
    }
}
