import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    ExternalLinkIcon,
    PanelsTopLeftIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import PostForm from '../../../components/posts/post-form';
import type { PostEditPageProps } from '../../../types/cms';

export default function PostsEdit({ post, ...props }: PostEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Posts', href: route('cms.posts.index') },
        { title: post.title, href: route('cms.posts.edit', post.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${post.title}`}
            description="Update the post content, publishing settings, and metadata."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    {post.permalink_url ? (
                        <Button variant="outline" asChild>
                            <a
                                href={post.permalink_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                View
                            </a>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <a
                            href={route('cms.builder.edit', post.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <PanelsTopLeftIcon data-icon="inline-start" />
                            Edit in Builder
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('cms.posts.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to Posts
                        </Link>
                    </Button>
                </div>
            }
        >
            <PostForm mode="edit" post={post} {...props} />
        </AppLayout>
    );
}
