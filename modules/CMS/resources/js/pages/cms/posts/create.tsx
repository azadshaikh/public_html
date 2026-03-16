import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import PostForm from '../../../components/posts/post-form';
import type { PostCreatePageProps } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Posts', href: route('cms.posts.index') },
    { title: 'Create', href: route('cms.posts.create') },
];

export default function PostsCreate(props: PostCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Post"
            description="Add a new blog post and configure its publishing settings."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.posts.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Posts
                    </Link>
                </Button>
            }
        >
            <PostForm mode="create" {...props} />
        </AppLayout>
    );
}
