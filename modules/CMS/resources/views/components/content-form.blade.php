{{-- CMS Content Form Component --}}
{{-- This component contains the main content form with title and tabs for CMS CRUD operations --}}
{{-- Note: Slug field should be added in the sidebar of each form that uses this component --}}
@props([
    'model' => null,
    'modelName' => 'content',
    'titlePlaceholder' => 'Enter title',
    'contentPlaceholder' => 'Enter detailed content',
    'metaRobotsOptions' => [],
    'seoSettingKey' => null,
    'showContentLabel' => true,
    'showExcerpt' => false
])

<div class="card">
    <div class="card-body">
        {{-- Title --}}
        <div class="row mb-3">
            <div class="col-12">
                <x-form-elements.input class="form-group" id="title" name="title"
                   :value="old('title', isset($model) ? $model->title : '')"
                    inputclass="form-control" label="Title" labelClass="form-label required"
                    :placeholder="$titlePlaceholder" :extraAttributes="['required' => 'required']" />
            </div>
        </div>

        @php
            $contentTabs = [
                ['name' => 'content', 'label' => 'Content'],
                ['name' => 'seo', 'label' => 'SEO'],
                ['name' => 'social', 'label' => 'Social'],
                ['name' => 'schema', 'label' => 'Schema'],
            ];
        @endphp

        <x-tabs param="content_tab" active="content" :card="false" :tabs="$contentTabs">
            <x-slot:content>
                <div class="row mb-3">
                    <div class="col-12">
                        @php
                            $contentLabel = $showContentLabel ? 'Content' : null;
                            $contentLabelClass = $showContentLabel ? 'form-label' : null;
                        @endphp
                        <x-form-elements.textarea class="form-group" id="content" name="content"
                           :value="old('content', isset($model) ? $model->content : '')"
                            inputclass="form-control" :label="$contentLabel" :labelClass="$contentLabelClass"
                            :placeholder="$contentPlaceholder" asteronote="true" rows="15" />
                    </div>
                </div>

                {{-- Excerpt (optional, inside content tab) --}}
                @if($showExcerpt)
                <div class="row mb-3">
                    <div class="col-12">
                        <x-form-elements.textarea class="form-group" id="excerpt" name="excerpt"
                           :value="old('excerpt', isset($model) ? $model->excerpt : '')"
                            inputclass="form-control" label="Excerpt (Optional)" labelClass="form-label"
                            placeholder="Enter brief excerpt" rows="3" />
                    </div>
                </div>
                @endif

                {{-- Additional content slot after excerpt --}}
                {{ $contentFooter ?? '' }}
            </x-slot:content>

            <x-slot:seo>
                <div class="row">
                    {{-- Meta Title --}}
                    <div class="col-12 mb-3">
                        <x-form-elements.input class="form-group" id="meta_title" name="meta_title"
                           :value="old('meta_title', isset($model) ? $model->meta_title : '')"
                            inputclass="form-control" label="Meta Title" labelClass="form-label"
                            placeholder="Enter meta title (50-60 characters)" />
                    </div>

                    {{-- Meta Description --}}
                    <div class="col-12 mb-3">
                        <x-form-elements.textarea class="form-group" id="meta_description"
                            name="meta_description"
                           :value="old('meta_description', isset($model) ? $model->meta_description : '')"
                            inputclass="form-control" label="Meta Description" labelClass="form-label"
                            placeholder="Enter meta description (150-160 characters)" rows="3" />
                    </div>

                    {{-- Meta Robots --}}
                    <div class="col-12 mb-3">
                        <x-form-elements.select class="form-group" id="meta_robots"
                            name="meta_robots"
                           :value="old('meta_robots', isset($model) ? $model->meta_robots : '')"
                            label="Meta Robots" labelClass="form-label" placeholder="Select meta robots"
                            :options="$metaRobotsOptions" />
                    </div>
                </div>
            </x-slot:seo>

            <x-slot:social>
                <div class="row">
                    {{-- OG Title --}}
                    <div class="col-12 mb-3">
                        <x-form-elements.input class="form-group" id="og_title"
                            name="og_title"
                           :value="old('og_title', isset($model) ? $model->og_title : '')"
                            inputclass="form-control" label="Open Graph Title" labelClass="form-label"
                            placeholder="Enter Open Graph title" />
                    </div>

                    {{-- OG Description --}}
                    <div class="col-12 mb-3">
                        <x-form-elements.textarea class="form-group" id="og_description"
                            name="og_description"
                           :value="old('og_description', isset($model) ? $model->og_description : '')"
                            inputclass="form-control" label="Open Graph Description" labelClass="form-label"
                            placeholder="Enter Open Graph description" rows="3" />
                    </div>

                    {{-- OG Image --}}
                    <div class="col-12 mb-3">
                        <x-media-picker.url-field class="form-group" id="og_image"
                            name="og_image"
                           :value="old('og_image', isset($model) ? $model->og_image : '')"
                           :valueUrl="old('og_image', isset($model) ? $model->og_image : '')"
                            label="Open Graph Image" labelClass="form-label"
                            placeholder="Select Open Graph image or enter URL"
                            :readonly="false" />
                    </div>

                    {{-- OG URL --}}
                    <div class="col-12 mb-3">
                        <x-form-elements.input class="form-group" id="og_url"
                            name="og_url"
                           :value="old('og_url', isset($model) ? $model->og_url : '')"
                            inputclass="form-control" label="Open Graph URL" labelClass="form-label"
                            type="url"
                            placeholder="Enter canonical URL" />
                    </div>
                </div>
            </x-slot:social>

            <x-slot:schema>
                <div class="row mb-3">
                    <div class="col-12 mb-3">
                        <div class="form-group">
                            @php
                                $schema_oldcontent = old('schema', isset($model->schema) && !empty($model->schema) ? $model->schema : '');
                            @endphp
                            <x-textarea-monaco syntax="html" height="650">
                                <textarea class="form-control form-control-lg @if ($errors->has('schema')) is-invalid @endif"
                                    id="schema" name="schema" rows="21">{{ $schema_oldcontent }}</textarea>
                            </x-textarea-monaco>
                            <div class="form-text">Add custom schema markup for this {{ $modelName }}</div>
                        </div>
                    </div>
                </div>
            </x-slot:schema>
        </x-tabs>
    </div>
</div>
