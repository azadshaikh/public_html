import { Form, Head, Link, router } from '@inertiajs/react'
import { FileTextIcon, LayoutDashboardIcon, PencilIcon, PlusIcon, SearchIcon, SparklesIcon, Trash2Icon } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import AppLayout from '@/layouts/app-layout'
import { dashboard } from '@/routes/index'
import type { BreadcrumbItem } from '@/types'

type Option = {
  value: string
  label: string
}

type ModuleMeta = {
  name: string
  slug: string
  version: string
  description: string
}

type CmsPageListItem = {
  id: number
  title: string
  slug: string
  summary: string | null
  status: string
  published_at: string | null
  is_featured: boolean
}

type PaginatedData<T> = {
  data: T[]
  prev_page_url: string | null
  next_page_url: string | null
  total: number
  from: number | null
  to: number | null
}

type CmsIndexPageProps = {
  module: ModuleMeta
  filters: {
    search: string
    status: string
  }
  pages: PaginatedData<CmsPageListItem>
  stats: {
    total: number
    published: number
    draft: number
    featured: number
  }
  options: {
    statusOptions: Option[]
  }
  status?: string
}

export default function CmsIndex({ module, filters, pages, stats, options, status }: CmsIndexPageProps) {
  const cmsIndexUrl = '/cms'
  const cmsCreateUrl = '/cms/create'
  const cmsEditUrl = (id: number) => `/cms/${id}/edit`
  const cmsDestroyUrl = (id: number) => `/cms/${id}`

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: 'Dashboard',
      href: dashboard(),
    },
    {
      title: module.name,
      href: cmsIndexUrl,
    },
  ]

  const handleDelete = (page: CmsPageListItem) => {
    if (!window.confirm(`Delete ${page.title}?`)) {
      return
    }

    router.delete(cmsDestroyUrl(page.id), {
      preserveScroll: true,
    })
  }

  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title={`${module.name} pages`}
      description="Manage a starter page CRUD entirely from the module."
      headerActions={
        <div className="flex flex-wrap gap-3">
          <Button asChild variant="outline">
            <Link href={dashboard()}>
              <LayoutDashboardIcon />
              Back to dashboard
            </Link>
          </Button>
          <Button asChild>
            <Link href={cmsCreateUrl}>
              <PlusIcon />
              Create page
            </Link>
          </Button>
        </div>
      }
    >
      <Head title={`${module.name} pages`} />

      <section className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
        <Card className="border-none bg-gradient-to-br from-primary to-primary/80 text-primary-foreground shadow-none ring-0">
          <CardHeader className="space-y-4">
            <Badge variant="secondary" className="w-fit bg-primary-foreground/15 text-primary-foreground hover:bg-primary-foreground/15">
              Starter CRUD
            </Badge>
            <div className="space-y-2">
              <CardTitle className="text-3xl font-semibold tracking-tight md:text-4xl">
                Build content workflows from a clean page resource.
              </CardTitle>
              <CardDescription className="max-w-2xl text-primary-foreground/75">
                Use this sample CRUD as the foundation for page builders, knowledge bases, landing pages, or internal documentation flows.
              </CardDescription>
            </div>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <div>
              <div className="text-sm text-primary-foreground/70">Total pages</div>
              <div className="mt-1 text-xl font-semibold">{stats.total}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Published</div>
              <div className="mt-1 text-xl font-semibold">{stats.published}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Featured</div>
              <div className="mt-1 text-xl font-semibold">{stats.featured}</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>What ships with it</CardTitle>
            <CardDescription>
              A tiny but real CRUD that is easy to extend with revisions, blocks, SEO fields, or publishing workflows.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {[
              'Search and status filtering for content lists',
              'Draft, published, and archived states',
              'Featured flag and optional publish date',
            ].map((feature) => (
              <div key={feature} className="flex items-start gap-3 rounded-xl border bg-muted/40 p-4">
                <SparklesIcon className="mt-0.5 size-4 text-primary" />
                <span className="text-sm text-muted-foreground">{feature}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </section>

      {status ? (
        <Alert className="mt-6">
          <FileTextIcon className="size-4" />
          <AlertTitle>Saved</AlertTitle>
          <AlertDescription>{status}</AlertDescription>
        </Alert>
      ) : null}

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Filter pages</CardTitle>
          <CardDescription>Keep the list focused while you shape content models for the module.</CardDescription>
        </CardHeader>
        <CardContent>
          <Form action={cmsIndexUrl} method="get" options={{ preserveScroll: true }} className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
            <InputGroup className="w-full">
              <InputGroupAddon>
                <SearchIcon />
              </InputGroupAddon>
              <InputGroupInput name="search" defaultValue={filters.search} placeholder="Search title, slug, or summary" />
            </InputGroup>

            <NativeSelect className="w-full" name="status" defaultValue={filters.status}>
              <NativeSelectOption value="">All statuses</NativeSelectOption>
              {options.statusOptions.map((option) => (
                <NativeSelectOption key={option.value} value={option.value}>{option.label}</NativeSelectOption>
              ))}
            </NativeSelect>

            <div className="flex gap-2">
              <Button type="submit">Apply</Button>
              <Button asChild type="button" variant="outline">
                <Link href={cmsIndexUrl}>Reset</Link>
              </Button>
            </div>
          </Form>
        </CardContent>
      </Card>

      <section className="mt-6 grid gap-4 lg:grid-cols-2">
        {pages.data.length > 0 ? pages.data.map((page) => (
          <Card key={page.id}>
            <CardHeader>
              <div className="flex items-start justify-between gap-3">
                <div>
                  <CardTitle className="text-xl">{page.title}</CardTitle>
                  <CardDescription>/{page.slug}</CardDescription>
                </div>
                <div className="flex gap-2">
                  <Badge variant="outline">{page.status}</Badge>
                  {page.is_featured ? <Badge>Featured</Badge> : null}
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">{page.summary || 'No summary yet.'}</p>
              <div className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                <span>{page.published_at ? `Publishes ${page.published_at}` : 'No publish date set'}</span>
                <span>{module.version}</span>
              </div>
              <div className="flex flex-wrap justify-end gap-2">
                <Button asChild size="sm" variant="outline">
                  <Link href={cmsEditUrl(page.id)}>
                    <PencilIcon />
                    Edit
                  </Link>
                </Button>
                <Button size="sm" variant="destructive" onClick={() => handleDelete(page)}>
                  <Trash2Icon />
                  Delete
                </Button>
              </div>
            </CardContent>
          </Card>
        )) : (
          <Card className="lg:col-span-2">
            <CardContent className="p-6">
              <Empty>
                <EmptyHeader>
                  <EmptyMedia variant="icon">
                    <FileTextIcon />
                  </EmptyMedia>
                  <EmptyTitle>No pages yet</EmptyTitle>
                  <EmptyDescription>Create your first CMS record to test content authoring and publishing flows.</EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                  <Button asChild>
                    <Link href={cmsCreateUrl}>
                      <PlusIcon />
                      Create page
                    </Link>
                  </Button>
                </EmptyContent>
              </Empty>
            </CardContent>
          </Card>
        )}
      </section>

      {(pages.prev_page_url || pages.next_page_url) ? (
        <div className="mt-6 flex items-center justify-between gap-4 text-sm text-muted-foreground">
          <span>Showing {pages.from ?? 0} to {pages.to ?? 0} of {pages.total} pages</span>
          <div className="flex gap-2">
            <Button asChild variant="outline" size="sm" disabled={!pages.prev_page_url}>
              <Link href={pages.prev_page_url ?? cmsIndexUrl}>Previous</Link>
            </Button>
            <Button asChild variant="outline" size="sm" disabled={!pages.next_page_url}>
              <Link href={pages.next_page_url ?? cmsIndexUrl}>Next</Link>
            </Button>
          </div>
        </div>
      ) : null}
    </AppLayout>
  )
}
