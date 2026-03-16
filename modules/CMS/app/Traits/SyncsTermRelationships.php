<?php

namespace Modules\CMS\Traits;

use Modules\CMS\Models\CmsPost;

trait SyncsTermRelationships
{
    /**
     * Sync term relationships (categories/tags) for a post.
     */
    protected function syncTerms(CmsPost $post, array $data): void
    {
        if (isset($data['categories'])) {
            $this->syncPostCategories($post, $data['categories']);
        }

        if (isset($data['tags'])) {
            $this->syncPostTags($post, $data['tags']);
        }
    }

    /**
     * Sync a specific term type for a post.
     */
    protected function syncTermType(CmsPost $post, array $termIds, string $relationshipName): void
    {
        $post->{$relationshipName}()->sync($termIds);
    }

    /**
     * Detach all terms from a post.
     */
    protected function detachAllTerms(CmsPost $post): void
    {
        $post->categories()->detach();
        $post->tags()->detach();
    }

    /**
     * Sync categories for a post with pivot data.
     */
    private function syncPostCategories(CmsPost $post, array $categoryIds): void
    {
        $validCategoryIds = array_filter($categoryIds, fn ($id): bool => $id !== null && $id !== '' && is_numeric($id));

        $syncData = [];
        foreach ($validCategoryIds as $categoryId) {
            $syncData[$categoryId] = ['term_type' => 'category'];
        }

        $post->categories()->sync($syncData);
    }

    /**
     * Sync tags for a post with pivot data.
     */
    private function syncPostTags(CmsPost $post, array $tagIds): void
    {
        $validTagIds = array_filter($tagIds, fn ($id): bool => $id !== null && $id !== '' && is_numeric($id));

        $syncData = [];
        foreach ($validTagIds as $tagId) {
            $syncData[$tagId] = ['term_type' => 'tag'];
        }

        $post->tags()->sync($syncData);
    }
}
