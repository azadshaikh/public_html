import { Head, Link } from '@inertiajs/react'
import { CheckSquareIcon, LayoutDashboardIcon, ListTodoIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import { dashboard } from '@/routes/index'
import type { BreadcrumbItem } from '@/types'

type TodosPageProps = {
  module: {
    name: string
    slug: string
    version: string
    description: string
    items: Array<{
      title: string
      status: string
    }>
  }
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
  },
  {
    title: 'Todos module',
    href: '/todos',
  },
]

export default function TodosIndex({ module }: TodosPageProps) {
  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title={`${module.name} module`}
      description={module.description}
      headerActions={
        <Button asChild>
          <Link href={dashboard()}>
            <LayoutDashboardIcon />
            Back to dashboard
          </Link>
        </Button>
      }
    >
      <Head title={`${module.name} module`} />

      <section className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
        <Card className="border-none bg-gradient-to-br from-primary to-primary/80 text-primary-foreground shadow-none ring-0">
          <CardHeader>
            <Badge variant="secondary" className="w-fit bg-primary-foreground/15 text-primary-foreground hover:bg-primary-foreground/15">
              Task board
            </Badge>
            <CardTitle className="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">
              A lightweight todo module delivered as a module.
            </CardTitle>
            <CardDescription className="text-primary-foreground/75">
              Test a third module with its own route and page while sharing the host shell and components.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <div>
              <div className="text-sm text-primary-foreground/70">Slug</div>
              <div className="mt-1 text-xl font-semibold">{module.slug}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Version</div>
              <div className="mt-1 text-xl font-semibold">{module.version}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Tasks</div>
              <div className="mt-1 text-xl font-semibold">{module.items.length}</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Sample use case</CardTitle>
            <CardDescription>Project tracking separated from the core application.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="rounded-xl border bg-muted/40 p-4">
              <div className="mb-2 flex items-center gap-2 font-medium">
                <ListTodoIcon className="size-4 text-primary" />
                Module-owned workflow
              </div>
              <p className="text-sm text-muted-foreground">
                A module can own its task pages and still use the main layout, auth, and UI primitives.
              </p>
            </div>
          </CardContent>
        </Card>
      </section>

      <section className="mt-6 grid gap-4 md:grid-cols-3">
        {module.items.map((item) => (
          <Card key={item.title}>
            <CardHeader>
              <div className="flex items-center justify-between gap-2">
                <CardTitle className="text-lg">{item.title}</CardTitle>
                <CheckSquareIcon className="size-4 text-primary" />
              </div>
            </CardHeader>
            <CardContent>
              <Badge variant="outline">{item.status}</Badge>
            </CardContent>
          </Card>
        ))}
      </section>
    </AppLayout>
  )
}
