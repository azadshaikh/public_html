<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Configure which pages to use for important site sections like homepage, blog, contact, and legal pages."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'CMS', 'href' => route('cms.posts.index', 'all')],
            ['label' => 'Default Pages', 'href' => null]
        ]"
    />

    <x-alert-container containerId="default-pages-alert-container" :showFlashMessages="false" :fieldLabels="[
        'home_page' => 'Homepage',
        'blogs_page' => 'Blog Page',
        'contact_page' => 'Contact Page',
        'about_page' => 'About Page',
        'privacy_policy_page' => 'Privacy Policy Page',
        'terms_of_service_page' => 'Terms of Service Page',
    ]" />

    <div class="row g-4">
        <!-- Settings Form -->
        <div class="col-lg-8">
            <form class="needs-validation" id="default-pages-form"
                action="{{ route('cms.settings.default-pages.update') }}"
                method="POST" novalidate>
                @csrf
                @method('PUT')

                <!-- Homepage & Blog Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-home-line me-2 text-primary"></i>
                            Homepage & Blog
                        </h5>
                    </div>
                    <div class="card-body pt-4">
                        <x-form-elements.select
                            layout="horizontal"
                            divclass="mb-4"
                            id="home_page"
                            name="home_page"
                            label="Homepage"
                            :options="json_encode($pageOptions)"
                            :value="$settings['home_page']"
                            infotext="Select a page to use as your site's homepage. Leave empty to show latest posts."
                        />

                        <x-form-elements.switch-input
                            layout="horizontal"
                            class="form-group mb-4"
                            id="blog_same_as_home"
                            name="blog_same_as_home"
                            labelclass="fw-medium"
                            label="Blog on Homepage"
                            :value="1"
                            :ischecked="$settings['blog_same_as_home'] ? 1 : 0"
                            infotext="When enabled, the homepage will also serve as the blog listing page."
                        />

                        <div id="blogs-page-container" style="{{ $settings['blog_same_as_home'] ? 'display: none;' : '' }}">
                            <x-form-elements.select
                                layout="horizontal"
                                divclass="mb-4"
                                id="blogs_page"
                                name="blogs_page"
                                label="Blog Page"
                                :options="json_encode($pageOptions)"
                                :value="$settings['blogs_page']"
                                infotext="Select a page to use as your blog listing page. This page will display all posts."
                            />

                            <x-form-elements.input
                                layout="horizontal"
                                divclass="mb-0"
                                id="blog_base_url"
                                name="blog_base_url"
                                label="Blog URL Slug"
                                :value="$settings['blog_base_url']"
                                placeholder="blog"
                                infotext="The URL slug for your blog archive (e.g., 'blog' = /blog, 'news' = /news). Only lowercase letters, numbers, and hyphens allowed."
                            />
                        </div>
                    </div>
                </div>

                <!-- Important Pages Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-file-info-line me-2 text-primary"></i>
                            Important Pages
                        </h5>
                    </div>
                    <div class="card-body pt-4">
                        <x-form-elements.select
                            layout="horizontal"
                            divclass="mb-4"
                            id="contact_page"
                            name="contact_page"
                            label="Contact Page"
                            :options="json_encode($pageOptions)"
                            :value="$settings['contact_page']"
                            infotext="Your contact page with contact form or information."
                        />

                        <x-form-elements.select
                            layout="horizontal"
                            divclass="mb-0"
                            id="about_page"
                            name="about_page"
                            label="About Page"
                            :options="json_encode($pageOptions)"
                            :value="$settings['about_page']"
                            infotext="Your about us or company information page."
                        />
                    </div>
                </div>

                <!-- Legal Pages Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-shield-check-line me-2 text-primary"></i>
                            Legal Pages
                        </h5>
                    </div>
                    <div class="card-body pt-4">
                        <x-form-elements.select
                            layout="horizontal"
                            divclass="mb-4"
                            id="privacy_policy_page"
                            name="privacy_policy_page"
                            label="Privacy Policy"
                            :options="json_encode($pageOptions)"
                            :value="$settings['privacy_policy_page']"
                            infotext="Your privacy policy page (required for GDPR compliance)."
                        />

                        <x-form-elements.select
                            layout="horizontal"
                            divclass="mb-0"
                            id="terms_of_service_page"
                            name="terms_of_service_page"
                            label="Terms of Service"
                            :options="json_encode($pageOptions)"
                            :value="$settings['terms_of_service_page']"
                            infotext="Your terms of service or terms and conditions page."
                        />
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit">
                        <i class="ri-save-line me-1"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Sidebar -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ri-question-line me-2"></i>
                        How It Works
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="fw-semibold">Homepage</h6>
                        <p class="text-muted small mb-0">
                            The page selected as homepage will be displayed when visitors access your site's root URL.
                            If no page is selected, the latest posts will be shown.
                        </p>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-semibold">Blog Page</h6>
                        <p class="text-muted small mb-0">
                            Enable "Blog on Homepage" to show posts on your homepage, or select a separate blog page.
                            The blog page displays your posts in a paginated list.
                        </p>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-semibold">Important & Legal Pages</h6>
                        <p class="text-muted small mb-0">
                            These pages are used in footer links and for legal compliance.
                            Make sure to create and assign proper pages for contact and legal information.
                        </p>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="ri-information-line me-1"></i>
                        <strong>Tip:</strong> Create your pages first in the
                        <a href="{{ route('cms.pages.index', 'all') }}">Pages</a> section,
                        then come back here to assign them.
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script data-up-execute>
        (function() {
            const blogSameAsHomeSwitch = document.getElementById('blog_same_as_home');
            const blogsPageContainer = document.getElementById('blogs-page-container');
            const blogsPageSelect = document.getElementById('blogs_page');

            if (blogSameAsHomeSwitch && blogsPageContainer) {
                blogSameAsHomeSwitch.addEventListener('change', function() {
                    if (this.checked) {
                        blogsPageContainer.style.display = 'none';
                        // Clear the blogs_page value when hidden
                        if (blogsPageSelect) {
                            blogsPageSelect.value = '';
                        }
                    } else {
                        blogsPageContainer.style.display = '';
                    }
                });
            }
        })();
    </script>
    @endpush
</x-app-layout>
